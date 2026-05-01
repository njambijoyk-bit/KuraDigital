<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
