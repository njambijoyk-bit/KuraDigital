<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\MpesaTransaction;
use App\Services\MpesaDarajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    // =====================================================================
    // Budgets
    // =====================================================================

    public function budgetsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Budget::class, $campaign]);

        $query = $campaign->budgets()->with('creator:id,name');

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $budgets = $query->orderBy('name')->paginate(20);

        return response()->json($budgets);
    }

    public function budgetsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Budget::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:operations,media,events,field,personnel,logistics,other'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'period' => ['nullable', 'in:monthly,quarterly,total'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $budget = Budget::create($validated);
        $budget->load('creator:id,name');

        return response()->json([
            'message' => 'Budget created.',
            'budget' => $budget,
        ], 201);
    }

    public function budgetsShow(Request $request, Campaign $campaign, Budget $budget): JsonResponse
    {
        $this->authorize('view', [Budget::class, $campaign]);

        if ($budget->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->load(['creator:id,name', 'expenses' => function ($q) {
            $q->where('status', 'approved')->orderByDesc('expense_date');
        }]);

        return response()->json(['budget' => $budget]);
    }

    public function budgetsUpdate(Request $request, Campaign $campaign, Budget $budget): JsonResponse
    {
        $this->authorize('update', [Budget::class, $campaign]);

        if ($budget->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'in:operations,media,events,field,personnel,logistics,other'],
            'allocated_amount' => ['sometimes', 'numeric', 'min:0'],
            'period' => ['nullable', 'in:monthly,quarterly,total'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $budget->update($validated);

        return response()->json([
            'message' => 'Budget updated.',
            'budget' => $budget,
        ]);
    }

    public function budgetsDestroy(Request $request, Campaign $campaign, Budget $budget): JsonResponse
    {
        $this->authorize('delete', [Budget::class, $campaign]);

        if ($budget->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget deleted.']);
    }

    // =====================================================================
    // Expenses
    // =====================================================================

    public function expensesIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Expense::class, $campaign]);

        $query = $campaign->expenses()->with(['creator:id,name', 'approver:id,name', 'budget:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('expense_date')->paginate(20);

        return response()->json($expenses);
    }

    public function expensesStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Expense::class, $campaign]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'category' => ['required', 'in:operations,media,events,field,personnel,logistics,other'],
            'payment_method' => ['nullable', 'in:cash,mpesa,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'budget_id' => ['nullable', 'exists:budgets,id'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['budget_id'])) {
            $budget = Budget::find($validated['budget_id']);
            if ($budget && $budget->campaign_id !== $campaign->id) {
                return response()->json(['message' => 'Budget does not belong to this campaign.'], 422);
            }
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $expense = Expense::create($validated);
        $expense->load(['creator:id,name', 'budget:id,name']);

        return response()->json([
            'message' => 'Expense logged.',
            'expense' => $expense,
        ], 201);
    }

    public function expensesShow(Request $request, Campaign $campaign, Expense $expense): JsonResponse
    {
        $this->authorize('view', [Expense::class, $campaign]);

        if ($expense->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Expense not found.'], 404);
        }

        $expense->load(['creator:id,name', 'approver:id,name', 'budget:id,name']);

        return response()->json(['expense' => $expense]);
    }

    public function expensesUpdate(Request $request, Campaign $campaign, Expense $expense): JsonResponse
    {
        $this->authorize('update', [Expense::class, $campaign]);

        if ($expense->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Expense not found.'], 404);
        }

        if ($expense->status === 'approved') {
            return response()->json(['message' => 'Cannot edit an approved expense.'], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'category' => ['sometimes', 'in:operations,media,events,field,personnel,logistics,other'],
            'payment_method' => ['nullable', 'in:cash,mpesa,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['sometimes', 'date'],
            'budget_id' => ['nullable', 'exists:budgets,id'],
        ]);

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated.',
            'expense' => $expense,
        ]);
    }

    public function expensesApprove(Request $request, Campaign $campaign, Expense $expense): JsonResponse
    {
        $this->authorize('approve', [Expense::class, $campaign]);

        if ($expense->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Expense not found.'], 404);
        }

        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Only pending expenses can be approved.'], 422);
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($expense->budget_id) {
            $expense->budget->recalculateSpent();
        }

        $expense->load(['creator:id,name', 'approver:id,name']);

        return response()->json([
            'message' => 'Expense approved.',
            'expense' => $expense,
        ]);
    }

    public function expensesReject(Request $request, Campaign $campaign, Expense $expense): JsonResponse
    {
        $this->authorize('approve', [Expense::class, $campaign]);

        if ($expense->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Expense not found.'], 404);
        }

        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Only pending expenses can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $expense->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Expense rejected.',
            'expense' => $expense,
        ]);
    }

    public function expensesDestroy(Request $request, Campaign $campaign, Expense $expense): JsonResponse
    {
        $this->authorize('delete', [Expense::class, $campaign]);

        if ($expense->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Expense not found.'], 404);
        }

        if ($expense->status === 'approved') {
            return response()->json(['message' => 'Cannot delete an approved expense.'], 422);
        }

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    public function expensesExport(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('export', [Expense::class, $campaign]);

        $query = $campaign->expenses()->with(['creator:id,name', 'approver:id,name', 'budget:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('from')) {
            $query->where('expense_date', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('expense_date', '<=', $request->input('to'));
        }

        $expenses = $query->orderByDesc('expense_date')->get();

        return response()->json([
            'expenses' => $expenses,
            'summary' => [
                'total' => $expenses->sum('amount'),
                'approved' => $expenses->where('status', 'approved')->sum('amount'),
                'pending' => $expenses->where('status', 'pending')->sum('amount'),
                'rejected' => $expenses->where('status', 'rejected')->sum('amount'),
                'count' => $expenses->count(),
            ],
        ]);
    }

    // =====================================================================
    // Donations
    // =====================================================================

    public function donationsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Donation::class, $campaign]);

        $query = $campaign->donations();

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_phone', 'like', "%{$search}%")
                    ->orWhere('mpesa_receipt', 'like', "%{$search}%");
            });
        }

        $donations = $query->orderByDesc('donated_at')->paginate(20);

        return response()->json($donations);
    }

    public function donationsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Donation::class, $campaign]);

        $validated = $request->validate([
            'donor_name' => ['nullable', 'string', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'max:20'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'channel' => ['required', 'in:mpesa,bank_transfer,cash,online'],
            'mpesa_receipt' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_anonymous' => ['nullable', 'boolean'],
            'donated_at' => ['nullable', 'date'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['status'] = 'completed';
        $validated['donated_at'] = $validated['donated_at'] ?? now();

        $donation = Donation::create($validated);

        return response()->json([
            'message' => 'Donation recorded.',
            'donation' => $donation,
        ], 201);
    }

    public function donationsShow(Request $request, Campaign $campaign, Donation $donation): JsonResponse
    {
        $this->authorize('view', [Donation::class, $campaign]);

        if ($donation->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Donation not found.'], 404);
        }

        $donation->load('mpesaTransaction');

        return response()->json(['donation' => $donation]);
    }

    public function donationsExport(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('export', [Donation::class, $campaign]);

        $query = $campaign->donations();

        if ($request->has('from')) {
            $query->where('donated_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('donated_at', '<=', $request->input('to'));
        }

        $donations = $query->orderByDesc('donated_at')->get();

        return response()->json([
            'donations' => $donations,
            'summary' => [
                'total' => $donations->where('status', 'completed')->sum('amount'),
                'count' => $donations->count(),
                'by_channel' => $donations->where('status', 'completed')->groupBy('channel')
                    ->map(fn ($group) => ['count' => $group->count(), 'total' => $group->sum('amount')]),
            ],
        ]);
    }

    public function financeSummary(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Budget::class, $campaign]);

        $budgets = $campaign->budgets;
        $totalAllocated = $budgets->sum('allocated_amount');
        $totalSpent = $budgets->sum('spent_amount');

        $expenses = $campaign->expenses();
        $pendingExpenses = (clone $expenses)->where('status', 'pending')->sum('amount');
        $approvedExpenses = (clone $expenses)->where('status', 'approved')->sum('amount');

        $totalDonations = $campaign->donations()->where('status', 'completed')->sum('amount');
        $donationCount = $campaign->donations()->where('status', 'completed')->count();

        return response()->json([
            'summary' => [
                'total_budget' => (float) $totalAllocated,
                'total_spent' => (float) $totalSpent,
                'budget_remaining' => (float) $totalAllocated - (float) $totalSpent,
                'pending_expenses' => (float) $pendingExpenses,
                'approved_expenses' => (float) $approvedExpenses,
                'total_donations' => (float) $totalDonations,
                'donation_count' => $donationCount,
                'net_position' => (float) $totalDonations - (float) $approvedExpenses,
            ],
            'budgets_by_category' => $budgets->groupBy('category')->map(fn ($group) => [
                'allocated' => $group->sum('allocated_amount'),
                'spent' => $group->sum('spent_amount'),
            ]),
        ]);
    }

    // =====================================================================
    // M-Pesa STK Push (initiate donation)
    // =====================================================================

    public function mpesaStkPush(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Donation::class, $campaign]);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:15'],
            'amount' => ['required', 'numeric', 'min:1', 'max:150000'],
            'donor_name' => ['nullable', 'string', 'max:255'],
        ]);

        $mpesa = app(MpesaDarajaService::class);

        if (!$mpesa->isConfigured()) {
            return response()->json(['message' => 'M-Pesa is not configured. Set MPESA_* environment variables.'], 503);
        }

        $accountRef = 'KURA-' . $campaign->id;
        $result = $mpesa->stkPush($validated['phone_number'], $validated['amount'], $accountRef);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 502);
        }

        $transaction = MpesaTransaction::create([
            'campaign_id' => $campaign->id,
            'transaction_type' => 'stk_push',
            'merchant_request_id' => $result['merchant_request_id'],
            'checkout_request_id' => $result['checkout_request_id'],
            'amount' => $validated['amount'],
            'phone_number' => $validated['phone_number'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'STK Push initiated. Check your phone.',
            'transaction_id' => $transaction->id,
            'checkout_request_id' => $result['checkout_request_id'],
        ]);
    }

    public function mpesaStkQuery(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Donation::class, $campaign]);

        $validated = $request->validate([
            'checkout_request_id' => ['required', 'string'],
        ]);

        $mpesa = app(MpesaDarajaService::class);

        if (!$mpesa->isConfigured()) {
            return response()->json(['message' => 'M-Pesa is not configured.'], 503);
        }

        $result = $mpesa->stkQuery($validated['checkout_request_id']);

        return response()->json($result);
    }
}
