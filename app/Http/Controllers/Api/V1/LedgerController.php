<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Fund;
use App\Models\JournalEntry;
use App\Services\AutoPostingService;
use App\Services\ChartOfAccountsService;
use App\Services\FinancialReportService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    private LedgerService $ledger;
    private FinancialReportService $reports;
    private ChartOfAccountsService $chartOfAccounts;

    public function __construct(
        LedgerService $ledger,
        FinancialReportService $reports,
        ChartOfAccountsService $chartOfAccounts
    ) {
        $this->ledger = $ledger;
        $this->reports = $reports;
        $this->chartOfAccounts = $chartOfAccounts;
    }

    public function chartOfAccounts(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->chartOfAccounts->seedForCampaign($campaign);

        $accounts = Account::where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id', 'is_system']);

        return response()->json(['data' => $accounts]);
    }

    public function journalEntries(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = JournalEntry::where('campaign_id', $campaign->id)
            ->where('is_posted', true)
            ->with(['lines.account:id,code,name', 'poster:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('date', '<=', $request->to);
        }

        $entries = $query->paginate($request->input('per_page', 20));

        return response()->json($entries);
    }

    public function journalEntryShow(Request $request, Campaign $campaign, JournalEntry $journalEntry): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($journalEntry->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $journalEntry->load(['lines.account:id,code,name', 'lines.fund:id,code,name', 'poster:id,name']);

        return response()->json(['data' => $journalEntry]);
    }

    public function manualEntry(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('ledger.manual-entry')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.fund_id' => 'nullable|exists:funds,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);

        foreach ($validated['lines'] as $line) {
            $account = Account::find($line['account_id']);
            if (!$account || $account->campaign_id !== $campaign->id) {
                return response()->json(['message' => 'Account does not belong to this campaign'], 422);
            }
        }

        foreach ($validated['lines'] as $line) {
            if ((float) $line['debit'] === 0.0 && (float) $line['credit'] === 0.0) {
                return response()->json(['message' => 'Each line must have either a debit or credit amount'], 422);
            }
            if ((float) $line['debit'] > 0 && (float) $line['credit'] > 0) {
                return response()->json(['message' => 'A line cannot have both debit and credit'], 422);
            }
        }

        try {
            $entry = $this->ledger->post([
                'campaign_id' => $campaign->id,
                'date' => $validated['date'],
                'description' => $validated['description'],
                'reference_type' => 'manual',
                'posted_by' => $request->user()->id,
                'metadata' => ['source' => 'manual_entry'],
                'lines' => $validated['lines'],
            ]);

            return response()->json(['data' => $entry, 'message' => 'Journal entry posted successfully'], 201);
        } catch (\App\Exceptions\UnbalancedEntryException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reverseEntry(Request $request, Campaign $campaign, JournalEntry $journalEntry): JsonResponse
    {
        if (!$request->user()->can('ledger.manual-entry')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($journalEntry->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($journalEntry->is_reversed) {
            return response()->json(['message' => 'Entry is already reversed'], 422);
        }

        try {
            $reversal = $this->ledger->reverse($journalEntry, $request->user()->id);
            return response()->json(['data' => $reversal, 'message' => 'Entry reversed successfully']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function trialBalance(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->ledger->getTrialBalance($campaign, $request->input('as_of'));

        return response()->json(['data' => $data]);
    }

    public function incomeStatement(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->reports->incomeStatement($campaign, $request->input('from'), $request->input('to'));

        return response()->json(['data' => $data]);
    }

    public function balanceSheet(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->reports->balanceSheet($campaign, $request->input('as_of'));

        return response()->json(['data' => $data]);
    }

    public function cashFlow(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->reports->cashFlow($campaign, $request->input('from'), $request->input('to'));

        return response()->json(['data' => $data]);
    }

    public function budgetVsActual(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view-budget')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->reports->budgetVsActual($campaign, $request->input('from'), $request->input('to'));

        return response()->json(['data' => $data]);
    }

    public function integrity(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->ledger->verifyIntegrity($campaign);

        return response()->json(['data' => $data]);
    }

    public function funds(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $funds = Fund::where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code', 'description', 'is_restricted', 'is_default']);

        return response()->json(['data' => $funds]);
    }

    public function createFund(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('ledger.manage-funds')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
            'description' => 'nullable|string|max:500',
            'is_restricted' => 'required|boolean',
        ]);

        $exists = Fund::where('campaign_id', $campaign->id)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Fund code already exists for this campaign'], 422);
        }

        $fund = Fund::create(array_merge($validated, [
            'campaign_id' => $campaign->id,
            'is_default' => false,
            'is_active' => true,
        ]));

        return response()->json(['data' => $fund, 'message' => 'Fund created successfully'], 201);
    }

    public function fundSummary(Request $request, Campaign $campaign): JsonResponse
    {
        if (!$request->user()->can('finance.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $this->reports->fundSummary($campaign);

        return response()->json(['data' => $data]);
    }
}
