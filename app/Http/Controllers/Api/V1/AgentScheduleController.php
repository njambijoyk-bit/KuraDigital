<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentSchedule;
use App\Models\Campaign;
use App\Models\FieldAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentScheduleController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldAgent::class, $campaign]);

        $query = $campaign->agentSchedules()
            ->with(['fieldAgent.user:id,name,email,phone', 'assignedByUser:id,name']);

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('field_agent_id')) {
            $query->where('field_agent_id', $request->input('field_agent_id'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->input('date_from'), $request->input('date_to')]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('shift_type')) {
            $query->where('shift_type', $request->input('shift_type'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        $schedules = $query->orderBy('date')->orderBy('start_time')->paginate(50);

        return response()->json($schedules);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('assignStations', [FieldAgent::class, $campaign]);

        $validated = $request->validate([
            'field_agent_id' => ['required', 'exists:field_agents,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'shift_type' => ['required', 'in:morning,afternoon,evening,night,full_day,custom'],
            'date' => ['required', 'date'],
            'start_time' => ['required_if:shift_type,custom', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:shift_type,custom', 'nullable', 'date_format:H:i'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $agent = FieldAgent::where('id', $validated['field_agent_id'])
            ->where('campaign_id', $campaign->id)
            ->firstOrFail();

        $times = $this->resolveShiftTimes($validated['shift_type'], $validated);

        $warnings = $this->checkConflicts($campaign, $agent->id, $validated['date'], $times['start'], $times['end']);

        $schedule = AgentSchedule::create([
            'campaign_id' => $campaign->id,
            'field_agent_id' => $agent->id,
            'title' => $validated['title'] ?? null,
            'shift_type' => $validated['shift_type'],
            'date' => $validated['date'],
            'start_time' => $times['start'],
            'end_time' => $times['end'],
            'ward' => $validated['ward'] ?? null,
            'constituency' => $validated['constituency'] ?? null,
            'county' => $validated['county'] ?? null,
            'polling_station' => $validated['polling_station'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'assigned_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $schedule->load(['fieldAgent.user:id,name,email,phone', 'assignedByUser:id,name']);

        $response = [
            'message' => 'Schedule created.',
            'schedule' => $schedule,
        ];

        if (!empty($warnings)) {
            $response['warnings'] = $warnings;
        }

        return response()->json($response, 201);
    }

    public function bulk(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('assignStations', [FieldAgent::class, $campaign]);

        $validated = $request->validate([
            'field_agent_ids' => ['required', 'array', 'min:1'],
            'field_agent_ids.*' => ['exists:field_agents,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'shift_type' => ['required', 'in:morning,afternoon,evening,night,full_day,custom'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['date'],
            'start_time' => ['required_if:shift_type,custom', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:shift_type,custom', 'nullable', 'date_format:H:i'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $times = $this->resolveShiftTimes($validated['shift_type'], $validated);

        $agents = FieldAgent::whereIn('id', $validated['field_agent_ids'])
            ->where('campaign_id', $campaign->id)
            ->get();

        $created = [];
        $allWarnings = [];

        foreach ($agents as $agent) {
            foreach ($validated['dates'] as $date) {
                $warnings = $this->checkConflicts($campaign, $agent->id, $date, $times['start'], $times['end']);

                $schedule = AgentSchedule::create([
                    'campaign_id' => $campaign->id,
                    'field_agent_id' => $agent->id,
                    'title' => $validated['title'] ?? null,
                    'shift_type' => $validated['shift_type'],
                    'date' => $date,
                    'start_time' => $times['start'],
                    'end_time' => $times['end'],
                    'ward' => $validated['ward'] ?? null,
                    'constituency' => $validated['constituency'] ?? null,
                    'county' => $validated['county'] ?? null,
                    'polling_station' => $validated['polling_station'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'assigned_by' => $request->user()->id,
                    'status' => 'scheduled',
                ]);

                $created[] = $schedule;

                if (!empty($warnings)) {
                    $allWarnings[] = [
                        'agent_id' => $agent->id,
                        'agent_name' => $agent->user?->name ?? $agent->agent_code,
                        'date' => $date,
                        'warnings' => $warnings,
                    ];
                }
            }
        }

        $response = [
            'message' => count($created) . ' schedule(s) created.',
            'count' => count($created),
        ];

        if (!empty($allWarnings)) {
            $response['warnings'] = $allWarnings;
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, Campaign $campaign, AgentSchedule $agentSchedule): JsonResponse
    {
        $this->authorize('viewAny', [FieldAgent::class, $campaign]);

        if ($agentSchedule->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $agentSchedule->load(['fieldAgent.user:id,name,email,phone', 'assignedByUser:id,name']);

        return response()->json(['schedule' => $agentSchedule]);
    }

    public function update(Request $request, Campaign $campaign, AgentSchedule $agentSchedule): JsonResponse
    {
        $this->authorize('assignStations', [FieldAgent::class, $campaign]);

        if ($agentSchedule->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'shift_type' => ['nullable', 'in:morning,afternoon,evening,night,full_day,custom'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled,no_show'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['shift_type']) && $validated['shift_type'] !== 'custom') {
            $times = $this->resolveShiftTimes($validated['shift_type'], $validated);
            $validated['start_time'] = $times['start'];
            $validated['end_time'] = $times['end'];
        }

        $agentSchedule->update($validated);
        $agentSchedule->load(['fieldAgent.user:id,name,email,phone', 'assignedByUser:id,name']);

        return response()->json([
            'message' => 'Schedule updated.',
            'schedule' => $agentSchedule,
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, AgentSchedule $agentSchedule): JsonResponse
    {
        $this->authorize('assignStations', [FieldAgent::class, $campaign]);

        if ($agentSchedule->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $agentSchedule->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    public function calendar(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldAgent::class, $campaign]);

        $dateFrom = $request->input('date_from', now()->startOfWeek()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfWeek()->toDateString());

        $query = $campaign->agentSchedules()
            ->with(['fieldAgent.user:id,name', 'assignedByUser:id,name'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['scheduled', 'in_progress', 'completed']);

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $schedules = $query->orderBy('date')->orderBy('start_time')->get();

        $grouped = $schedules->groupBy(function ($s) {
            return $s->date->toDateString();
        });

        return response()->json(['calendar' => $grouped]);
    }

    public function coverage(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldAgent::class, $campaign]);

        $dateFrom = $request->input('date_from', now()->toDateString());
        $dateTo = $request->input('date_to', now()->addDays(7)->toDateString());

        $allWards = $campaign->fieldAgents()
            ->whereNotNull('ward')
            ->distinct()
            ->pluck('ward')
            ->toArray();

        $scheduledWards = $campaign->agentSchedules()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNotNull('ward')
            ->select('ward')
            ->selectRaw('COUNT(DISTINCT field_agent_id) as agent_count')
            ->selectRaw('COUNT(*) as schedule_count')
            ->groupBy('ward')
            ->get()
            ->keyBy('ward');

        $coverage = collect($allWards)->map(function ($ward) use ($scheduledWards) {
            $data = $scheduledWards->get($ward);
            return [
                'ward' => $ward,
                'agent_count' => $data ? $data->agent_count : 0,
                'schedule_count' => $data ? $data->schedule_count : 0,
                'has_coverage' => $data !== null,
            ];
        })->sortBy('agent_count')->values();

        $totalWards = count($allWards);
        $coveredWards = $scheduledWards->count();

        return response()->json([
            'coverage' => $coverage,
            'summary' => [
                'total_wards' => $totalWards,
                'covered_wards' => $coveredWards,
                'uncovered_wards' => $totalWards - $coveredWards,
                'coverage_percentage' => $totalWards > 0
                    ? round(($coveredWards / $totalWards) * 100, 1)
                    : 0,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function checkIn(Request $request, Campaign $campaign, AgentSchedule $agentSchedule): JsonResponse
    {
        if ($agentSchedule->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $agent = $agentSchedule->fieldAgent;
        if ($agent->user_id !== $request->user()->id) {
            $this->authorize('assignStations', [FieldAgent::class, $campaign]);
        }

        $agentSchedule->update([
            'checked_in_at' => now(),
            'status' => 'in_progress',
        ]);

        $agentSchedule->load(['fieldAgent.user:id,name,email,phone', 'assignedByUser:id,name']);

        return response()->json([
            'message' => 'Checked in successfully.',
            'schedule' => $agentSchedule,
        ]);
    }

    private function resolveShiftTimes(string $shiftType, array $data): array
    {
        if ($shiftType === 'custom') {
            return [
                'start' => $data['start_time'],
                'end' => $data['end_time'],
            ];
        }

        $preset = AgentSchedule::SHIFT_PRESETS[$shiftType] ?? AgentSchedule::SHIFT_PRESETS['full_day'];

        return [
            'start' => $preset['start'],
            'end' => $preset['end'],
        ];
    }

    private function checkConflicts(Campaign $campaign, int $agentId, string $date, string $startTime, string $endTime): array
    {
        $conflicts = $campaign->agentSchedules()
            ->where('field_agent_id', $agentId)
            ->whereDate('date', $date)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($inner) use ($startTime, $endTime) {
                    $inner->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                });
            })
            ->get();

        $warnings = [];
        foreach ($conflicts as $conflict) {
            $warnings[] = "Agent already scheduled on {$date} from {$conflict->start_time} to {$conflict->end_time}"
                . ($conflict->ward ? " at {$conflict->ward}" : '')
                . " (#{$conflict->id})";
        }

        return $warnings;
    }
}
