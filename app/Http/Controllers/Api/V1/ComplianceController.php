<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignFinanceSetting;
use App\Models\ComplianceAlert;
use App\Services\ComplianceService;
use App\Services\EncryptedExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        private ComplianceService $complianceService,
    ) {}

    public function dashboard(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewCompliance', [CampaignFinanceSetting::class, $campaign]);

        $data = $this->complianceService->getDashboardData($campaign);

        return response()->json($data);
    }

    public function alerts(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewCompliance', [CampaignFinanceSetting::class, $campaign]);

        $query = ComplianceAlert::where('campaign_id', $campaign->id)
            ->orderByDesc('created_at');

        if ($request->has('resolved')) {
            $query->where('is_resolved', $request->boolean('resolved'));
        }

        if ($request->filled('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->filled('severity')) {
            $query->bySeverity($request->input('severity'));
        }

        $alerts = $query->paginate($request->integer('per_page', 20));

        return response()->json($alerts);
    }

    public function resolveAlert(Request $request, Campaign $campaign, ComplianceAlert $alert): JsonResponse
    {
        $this->authorize('resolveAlerts', [CampaignFinanceSetting::class, $campaign]);

        if ($alert->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Alert does not belong to this campaign.'], 403);
        }

        $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $alert->update([
            'is_resolved' => true,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json(['message' => 'Alert resolved.', 'alert' => $alert->fresh()]);
    }

    public function settings(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewCompliance', [CampaignFinanceSetting::class, $campaign]);

        $settings = CampaignFinanceSetting::firstOrCreate(
            ['campaign_id' => $campaign->id],
            ['approval_limits' => [
                'finance-officer' => 50000,
                'finance-director' => 500000,
                'campaign-director' => 1000000,
            ]]
        );

        return response()->json($settings);
    }

    public function updateSettings(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('manageSettings', [CampaignFinanceSetting::class, $campaign]);

        $validated = $request->validate([
            'spending_cap' => ['nullable', 'numeric', 'min:0'],
            'individual_donation_cap' => ['nullable', 'numeric', 'min:0'],
            'corporate_donation_cap' => ['nullable', 'numeric', 'min:0'],
            'disclosure_threshold' => ['nullable', 'numeric', 'min:0'],
            'approval_limits' => ['nullable', 'array'],
            'approval_limits.*' => ['numeric', 'min:0'],
            'election_date' => ['nullable', 'date'],
            'reporting_period' => ['nullable', 'in:monthly,quarterly,annual'],
            'require_receipts_above' => ['nullable', 'numeric', 'min:0'],
            'require_segregation_of_duties' => ['nullable', 'boolean'],
            'alert_at_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'critical_at_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $settings = CampaignFinanceSetting::updateOrCreate(
            ['campaign_id' => $campaign->id],
            $validated
        );

        return response()->json(['message' => 'Compliance settings updated.', 'settings' => $settings]);
    }

    public function iebcReport(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('generateReports', [CampaignFinanceSetting::class, $campaign]);

        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $report = $this->complianceService->generateIEBCReport(
            $campaign,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    public function donorReport(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('generateReports', [CampaignFinanceSetting::class, $campaign]);

        $donations = $campaign->donations()
            ->where('status', 'completed')
            ->where('requires_disclosure', true)
            ->orderByDesc('donated_at')
            ->get();

        return response()->json(['data' => $donations]);
    }

    public function alertCount(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewCompliance', [CampaignFinanceSetting::class, $campaign]);

        $count = ComplianceAlert::where('campaign_id', $campaign->id)
            ->unresolved()
            ->count();

        return response()->json(['count' => $count]);
    }
}
