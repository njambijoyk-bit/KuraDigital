<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentCheckIn;
use App\Models\Campaign;
use App\Models\CheckIn;
use App\Models\FieldReport;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\VoterInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [\App\Models\FieldAgent::class, $campaign]);

        $requestedLayers = $request->input('layers')
            ? explode(',', $request->input('layers'))
            : ['check_ins', 'field_reports', 'polling_stations', 'incidents', 'interactions'];

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $membership = $request->user()->membershipFor($campaign);

        $data = [];

        if (in_array('check_ins', $requestedLayers)) {
            $data['check_ins'] = $this->getCheckIns($campaign, $membership, $dateFrom, $dateTo);
        }

        if (in_array('field_reports', $requestedLayers)) {
            $data['field_reports'] = $this->getFieldReports($campaign, $membership, $dateFrom, $dateTo);
        }

        if (in_array('polling_stations', $requestedLayers)) {
            $data['polling_stations'] = $this->getPollingStations($campaign, $membership);
        }

        if (in_array('incidents', $requestedLayers)) {
            $data['incidents'] = $this->getIncidents($campaign, $membership, $dateFrom, $dateTo);
        }

        if (in_array('interactions', $requestedLayers)) {
            $data['interactions'] = $this->getInteractions($campaign, $membership, $dateFrom, $dateTo);
        }

        return response()->json(['map_data' => $data]);
    }

    private function getCheckIns(Campaign $campaign, $membership, ?string $dateFrom, ?string $dateTo): array
    {
        $query = CheckIn::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('user:id,name');

        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        $agentCheckIns = AgentCheckIn::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('user:id,name');

        if ($dateFrom) $agentCheckIns->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $agentCheckIns->whereDate('created_at', '<=', $dateTo);

        $checkIns = $query->orderByDesc('created_at')->limit(500)->get();
        $agentCIs = $agentCheckIns->orderByDesc('created_at')->limit(500)->get();

        $combined = $checkIns->map(fn ($ci) => [
            'id' => 'ci-' . $ci->id,
            'lat' => (float) $ci->latitude,
            'lng' => (float) $ci->longitude,
            'label' => $ci->user?->name ?? 'Agent',
            'status' => $ci->status,
            'ward' => $ci->ward,
            'notes' => $ci->notes,
            'time' => $ci->created_at?->toIso8601String(),
        ])->concat($agentCIs->map(fn ($ci) => [
            'id' => 'aci-' . $ci->id,
            'lat' => (float) $ci->latitude,
            'lng' => (float) $ci->longitude,
            'label' => $ci->user?->name ?? 'Agent',
            'status' => $ci->check_in_type ?? 'check_in',
            'ward' => $ci->ward,
            'location_name' => $ci->location_name,
            'notes' => $ci->notes,
            'time' => $ci->created_at?->toIso8601String(),
        ]))->values()->toArray();

        return $combined;
    }

    private function getFieldReports(Campaign $campaign, $membership, ?string $dateFrom, ?string $dateTo): array
    {
        $query = FieldReport::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('user:id,name');

        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        return $query->orderByDesc('created_at')->limit(500)->get()->map(fn ($r) => [
            'id' => $r->id,
            'lat' => (float) $r->latitude,
            'lng' => (float) $r->longitude,
            'label' => $r->title ?: ucfirst($r->type) . ' Report',
            'type' => $r->type,
            'status' => $r->status,
            'ward' => $r->ward,
            'agent' => $r->user?->name,
            'time' => $r->created_at?->toIso8601String(),
        ])->toArray();
    }

    private function getPollingStations(Campaign $campaign, $membership): array
    {
        $query = PollingStation::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['assignedAgent:id,name']);

        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        return $query->get()->map(fn ($ps) => [
            'id' => $ps->id,
            'lat' => (float) $ps->latitude,
            'lng' => (float) $ps->longitude,
            'label' => $ps->name,
            'code' => $ps->code,
            'ward' => $ps->ward,
            'registered_voters' => $ps->registered_voters,
            'assigned_agent' => $ps->assignedAgent?->name,
            'status' => $ps->status,
        ])->toArray();
    }

    private function getIncidents(Campaign $campaign, $membership, ?string $dateFrom, ?string $dateTo): array
    {
        $query = Incident::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('reporter:id,name');

        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        return $query->orderByDesc('created_at')->limit(500)->get()->map(fn ($i) => [
            'id' => $i->id,
            'lat' => (float) $i->latitude,
            'lng' => (float) $i->longitude,
            'label' => $i->title,
            'category' => $i->category,
            'severity' => $i->severity,
            'status' => $i->status,
            'ward' => $i->ward,
            'reporter' => $i->reporter?->name,
            'time' => $i->created_at?->toIso8601String(),
        ])->toArray();
    }

    private function getInteractions(Campaign $campaign, $membership, ?string $dateFrom, ?string $dateTo): array
    {
        $query = VoterInteraction::where('campaign_id', $campaign->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['agent:id,name', 'voter:id,first_name,last_name']);

        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo) $query->whereDate('created_at', '<=', $dateTo);

        return $query->orderByDesc('created_at')->limit(1000)->get()->map(fn ($v) => [
            'id' => $v->id,
            'lat' => (float) $v->latitude,
            'lng' => (float) $v->longitude,
            'label' => trim(($v->voter?->first_name ?? '') . ' ' . ($v->voter?->last_name ?? '')) ?: 'Voter',
            'type' => $v->interaction_type,
            'outcome' => $v->outcome,
            'ward' => $v->ward,
            'agent' => $v->agent?->name,
            'time' => $v->created_at?->toIso8601String(),
        ])->toArray();
    }
}
