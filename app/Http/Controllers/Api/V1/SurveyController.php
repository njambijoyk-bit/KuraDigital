<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Survey::class, $campaign]);

        $query = $campaign->surveys()->withCount('responses');

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $surveys = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($surveys);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Survey::class, $campaign]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:text,number,select,multiselect,boolean'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.required' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,active,closed'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $survey = Survey::create($validated);

        return response()->json([
            'message' => 'Survey created.',
            'survey' => $survey,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('view', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($survey)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $survey->load('creator:id,name');
        $survey->loadCount('responses');

        return response()->json(['survey' => $survey]);
    }

    public function update(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('update', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'questions' => ['sometimes', 'array', 'min:1'],
            'questions.*.id' => ['required_with:questions', 'string'],
            'questions.*.type' => ['required_with:questions', 'in:text,number,select,multiselect,boolean'],
            'questions.*.text' => ['required_with:questions', 'string'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.required' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,active,closed'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $survey->update($validated);

        return response()->json([
            'message' => 'Survey updated.',
            'survey' => $survey,
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('delete', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $survey->delete();

        return response()->json(['message' => 'Survey deleted.']);
    }

    public function submit(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('submit', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        if ($survey->status !== 'active') {
            return response()->json(['message' => 'This survey is not accepting responses.'], 422);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'string'],
            'answers.*.value' => ['required'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'submitted_by' => $request->user()->id,
            'answers' => $validated['answers'],
            'ward' => $validated['ward'] ?? null,
            'constituency' => $validated['constituency'] ?? null,
            'county' => $validated['county'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return response()->json([
            'message' => 'Survey response submitted.',
            'response' => $response,
        ], 201);
    }

    public function responses(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('viewReports', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $query = $survey->responses()->with('submitter:id,name');

        // ABAC geographic filtering on responses
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $responses = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($responses);
    }

    public function results(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('viewReports', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $query = $survey->responses();
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $allResponses = $query->get();
        $totalResponses = $allResponses->count();
        $questions = $survey->questions ?? [];

        $questionResults = [];
        foreach ($questions as $q) {
            $qId = $q['id'];
            $qType = $q['type'];
            $result = ['id' => $qId, 'text' => $q['text'], 'type' => $qType, 'total_answers' => 0];

            $answers = $allResponses->pluck('answers')->flatten(1)
                ->where('question_id', $qId)->pluck('value');
            $result['total_answers'] = $answers->count();

            if ($qType === 'select' || $qType === 'multiselect') {
                $flat = $answers->flatten();
                $counts = $flat->countBy()->sortDesc();
                $result['distribution'] = $counts->map(function ($count, $option) use ($flat) {
                    return [
                        'option' => $option,
                        'count' => $count,
                        'percentage' => $flat->count() > 0 ? round(($count / $flat->count()) * 100, 1) : 0,
                    ];
                })->values();
            } elseif ($qType === 'number') {
                $nums = $answers->map(fn ($v) => (float) $v)->filter(fn ($v) => !is_nan($v));
                $result['stats'] = [
                    'min' => $nums->min(),
                    'max' => $nums->max(),
                    'average' => $nums->count() > 0 ? round($nums->avg(), 2) : null,
                    'median' => $nums->count() > 0 ? round($nums->sort()->values()->median(), 2) : null,
                ];
            } elseif ($qType === 'boolean') {
                $yes = $answers->filter(fn ($v) => $v === true || $v === 'true' || $v === 1 || $v === '1')->count();
                $no = $result['total_answers'] - $yes;
                $result['distribution'] = [
                    ['option' => 'Yes', 'count' => $yes, 'percentage' => $result['total_answers'] > 0 ? round(($yes / $result['total_answers']) * 100, 1) : 0],
                    ['option' => 'No', 'count' => $no, 'percentage' => $result['total_answers'] > 0 ? round(($no / $result['total_answers']) * 100, 1) : 0],
                ];
            } elseif ($qType === 'text') {
                $result['sample_answers'] = $answers->take(10)->values();
            }

            $questionResults[] = $result;
        }

        $geographic = $allResponses->groupBy('ward')->map(fn ($g) => $g->count())->sortDesc();

        return response()->json([
            'survey' => $survey->only(['id', 'title', 'description', 'status', 'questions']),
            'total_responses' => $totalResponses,
            'question_results' => $questionResults,
            'geographic_breakdown' => $geographic,
            'response_timeline' => $allResponses->groupBy(fn ($r) => $r->created_at->format('Y-m-d'))
                ->map(fn ($g) => $g->count())->sortKeys(),
        ]);
    }

    public function duplicate(Request $request, Campaign $campaign, Survey $survey): JsonResponse
    {
        $this->authorize('create', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $clone = $survey->replicate(['id', 'created_at', 'updated_at']);
        $clone->title = $survey->title . ' (Copy)';
        $clone->status = 'draft';
        $clone->created_by = $request->user()->id;
        $clone->save();

        return response()->json([
            'message' => 'Survey duplicated.',
            'survey' => $clone,
        ], 201);
    }

    public function exportCsv(Request $request, Campaign $campaign, Survey $survey): StreamedResponse
    {
        $this->authorize('viewReports', [Survey::class, $campaign]);

        if ($survey->campaign_id !== $campaign->id) {
            abort(404, 'Survey not found.');
        }

        $query = $survey->responses()->with('submitter:id,name');
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $responses = $query->orderBy('created_at')->get();
        $questions = $survey->questions ?? [];

        return response()->streamDownload(function () use ($responses, $questions) {
            $handle = fopen('php://output', 'w');

            $headers = ['#', 'Submitted By', 'Ward', 'Constituency', 'County', 'Date'];
            foreach ($questions as $q) {
                $headers[] = $q['text'] ?? $q['id'];
            }
            fputcsv($handle, $headers);

            foreach ($responses as $i => $resp) {
                $row = [
                    $i + 1,
                    $resp->submitter->name ?? 'Unknown',
                    $resp->ward ?? '',
                    $resp->constituency ?? '',
                    $resp->county ?? '',
                    $resp->created_at->format('Y-m-d H:i'),
                ];

                $answerMap = collect($resp->answers ?? [])->keyBy('question_id');
                foreach ($questions as $q) {
                    $answer = $answerMap->get($q['id']);
                    if (!$answer) {
                        $row[] = '';
                    } elseif (is_array($answer['value'])) {
                        $row[] = implode('; ', $answer['value']);
                    } else {
                        $row[] = (string) $answer['value'];
                    }
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, "survey-{$survey->id}-responses.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
