<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\MpesaTransaction;
use App\Models\Site;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\TallyResult;
use App\Models\Voter;
use App\Services\MpesaDarajaService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $query = Site::where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('candidate_name', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('constituency', 'like', "%{$search}%")
                  ->orWhere('county', 'like', "%{$search}%")
                  ->orWhere('party', 'like', "%{$search}%");
            });
        }

        if ($county = $request->query('county')) {
            $query->where('county', $county);
        }

        if ($position = $request->query('position')) {
            $query->where('position', $position);
        }

        $sites = $query->orderBy('candidate_name')
            ->select('id', 'slug', 'candidate_name', 'position', 'constituency', 'county', 'party', 'slogan', 'primary_color', 'portrait_url', 'logo_url')
            ->get();

        return response()->json(['data' => $sites]);
    }

    public function show(string $slug)
    {
        $site = Site::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json(['data' => $site]);
    }

    public function manifesto(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->manifestoPillars,
        ]);
    }

    public function events(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->events()->where('date', '>=', now())->get(),
        ]);
    }

    public function news(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->newsArticles,
        ]);
    }

    public function gallery(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->galleryItems,
        ]);
    }

    public function projects(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->projects,
        ]);
    }

    public function showNewsArticle(string $siteId, string $articleId)
    {
        $site = Site::findOrFail($siteId);

        $article = $site->newsArticles()
            ->where('id', $articleId)
            ->firstOrFail();

        $article->load('author:id,name');

        return response()->json(['data' => $article]);
    }

    public function showEvent(string $siteId, string $eventId)
    {
        $site = Site::findOrFail($siteId);

        $event = $site->events()
            ->where('id', $eventId)
            ->firstOrFail();

        return response()->json(['data' => $event]);
    }

    public function rsvpEvent(Request $request, string $siteId, string $eventId)
    {
        $site = Site::findOrFail($siteId);
        $event = $site->events()->where('id', $eventId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $event->rsvps()->create($validated);

        return response()->json(['message' => 'RSVP confirmed! We look forward to seeing you.'], 201);
    }

    public function storeContact(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ]);

        $site->contactMessages()->create($validated);

        return response()->json(['message' => 'Message sent successfully'], 201);
    }

    public function storeVolunteer(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'ward' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'skills' => 'nullable|string|max:2000',
        ]);

        $site->volunteers()->create($validated);

        return response()->json(['message' => 'Thank you for volunteering!'], 201);
    }

    public function subscribeNewsletter(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $existing = $site->newsletterSubscribers()->where('email', $validated['email'])->first();
        if ($existing) {
            if (!$existing->is_active) {
                $existing->update(['is_active' => true]);
                return response()->json(['message' => 'Welcome back! You have been re-subscribed.']);
            }
            return response()->json(['message' => 'You are already subscribed!']);
        }

        $site->newsletterSubscribers()->create($validated);

        return response()->json(['message' => 'Thank you for subscribing!'], 201);
    }

    public function registerSupporter(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'national_id' => 'nullable|string|max:50',
            'county' => 'nullable|string|max:255',
            'constituency' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:255',
            'polling_station' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:50',
        ]);

        $campaign = $site->campaign;
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not configured for this site.'], 422);
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['supporter_status'] = 'supporter';
        $validated['source'] = 'online';

        Voter::create($validated);

        return response()->json(['message' => 'Thank you for registering your support!'], 201);
    }

    public function donationStats(string $siteId)
    {
        $site = Site::findOrFail($siteId);
        $campaign = Campaign::where('site_id', $site->id)->first();

        $totalRaised = 0;
        $donorCount = 0;
        $recentDonations = [];

        if ($campaign) {
            $totalRaised = $campaign->donations()
                ->where('status', 'completed')
                ->sum('amount');

            $donorCount = $campaign->donations()
                ->where('status', 'completed')
                ->count();

            $recentDonations = $campaign->donations()
                ->where('status', 'completed')
                ->orderByDesc('donated_at')
                ->limit(10)
                ->get()
                ->map(function ($d) {
                    return [
                        'amount' => $d->amount,
                        'channel' => $d->channel,
                        'donor_name' => $d->is_anonymous ? 'Anonymous' : ($d->donor_name ?: 'Supporter'),
                        'donated_at' => $d->donated_at,
                    ];
                });
        }

        return response()->json([
            'data' => [
                'donation_goal' => $site->donation_goal,
                'donations_enabled' => (bool) $site->donations_enabled,
                'total_raised' => (float) $totalRaised,
                'donor_count' => $donorCount,
                'recent_donations' => $recentDonations,
            ],
        ]);
    }

    public function donate(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        if (!$site->donations_enabled) {
            return response()->json(['message' => 'Donations are not enabled for this campaign.'], 422);
        }

        $campaign = Campaign::where('site_id', $site->id)->first();
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not configured for this site.'], 422);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:15'],
            'amount' => ['required', 'numeric', 'min:10', 'max:150000'],
            'donor_name' => ['nullable', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        $mpesa = app(MpesaDarajaService::class);

        if (!$mpesa->isConfigured()) {
            // Record as manual donation when M-Pesa is not configured
            $donation = Donation::create([
                'campaign_id' => $campaign->id,
                'donor_name' => $validated['is_anonymous'] ?? false ? null : ($validated['donor_name'] ?? null),
                'donor_phone' => $validated['phone_number'],
                'donor_email' => $validated['donor_email'] ?? null,
                'amount' => $validated['amount'],
                'currency' => 'KES',
                'channel' => 'mpesa',
                'status' => 'completed',
                'is_anonymous' => $validated['is_anonymous'] ?? false,
                'donated_at' => now(),
                'notes' => 'Public site donation (M-Pesa not configured — recorded directly)',
            ]);

            return response()->json([
                'message' => 'Thank you for your donation!',
                'donation_id' => $donation->id,
            ], 201);
        }

        $accountRef = 'KURA-' . $campaign->id;
        $result = $mpesa->stkPush($validated['phone_number'], $validated['amount'], $accountRef);

        if (!$result['success']) {
            return response()->json(['message' => 'Payment initiation failed. Please try again.'], 502);
        }

        MpesaTransaction::create([
            'campaign_id' => $campaign->id,
            'transaction_type' => 'stk_push',
            'merchant_request_id' => $result['merchant_request_id'],
            'checkout_request_id' => $result['checkout_request_id'],
            'amount' => $validated['amount'],
            'phone_number' => $validated['phone_number'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Check your phone to complete the M-Pesa payment.',
            'checkout_request_id' => $result['checkout_request_id'],
        ]);
    }

    public function publicSurveys(string $siteId)
    {
        $site = Site::findOrFail($siteId);
        $campaign = Campaign::where('site_id', $site->id)->first();

        if (!$campaign) {
            return response()->json(['data' => []]);
        }

        $surveys = $campaign->surveys()
            ->where('status', 'active')
            ->select('id', 'title', 'description', 'questions', 'ends_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $surveys]);
    }

    public function publicSurveyShow(string $siteId, int $surveyId)
    {
        $site = Site::findOrFail($siteId);
        $campaign = Campaign::where('site_id', $site->id)->first();

        if (!$campaign) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $survey = Survey::where('campaign_id', $campaign->id)
            ->where('id', $surveyId)
            ->where('status', 'active')
            ->select('id', 'title', 'description', 'questions', 'ends_at')
            ->firstOrFail();

        return response()->json(['data' => $survey]);
    }

    public function publicSurveySubmit(Request $request, string $siteId, int $surveyId)
    {
        $site = Site::findOrFail($siteId);
        $campaign = Campaign::where('site_id', $site->id)->first();

        if (!$campaign) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        $survey = Survey::where('campaign_id', $campaign->id)
            ->where('id', $surveyId)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'string'],
            'answers.*.value' => ['required'],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'submitted_by' => null,
            'answers' => $validated['answers'],
            'ward' => $validated['ward'] ?? null,
            'constituency' => $validated['constituency'] ?? null,
            'county' => $validated['county'] ?? null,
        ]);

        return response()->json(['message' => 'Thank you! Your response has been recorded.'], 201);
    }

    public function electionResults(string $siteId)
    {
        $site = Site::findOrFail($siteId);
        $campaign = Campaign::where('site_id', $site->id)->first();

        if (!$campaign) {
            return response()->json(['data' => ['candidates' => [], 'overview' => null, 'stations' => [], 'by_ward' => [], 'by_constituency' => []]]);
        }

        $results = $campaign->tallyResults()
            ->with('pollingStation:id,name,code,ward,constituency,county,registered_voters,latitude,longitude,status')
            ->where('status', '!=', 'disputed')
            ->get();

        $candidateTotals = $results->groupBy('candidate_name')->map(function ($group, $name) {
            return [
                'candidate_name' => $name,
                'party' => $group->first()->party,
                'total_votes' => $group->sum('votes'),
                'stations_reported' => $group->pluck('polling_station_id')->unique()->count(),
                'verified_count' => $group->where('status', 'verified')->count(),
            ];
        })->sortByDesc('total_votes')->values();

        $totalStations = $campaign->pollingStations()->count();
        $reportedStations = $results->pluck('polling_station_id')->unique()->count();
        $totalVotesCast = $results->groupBy('polling_station_id')
            ->map(fn ($g) => $g->max('total_votes_cast'))->sum();
        $totalRegistered = $campaign->pollingStations()->sum('registered_voters');

        $stations = $campaign->pollingStations()
            ->select('id', 'name', 'code', 'ward', 'constituency', 'county', 'registered_voters', 'latitude', 'longitude', 'status')
            ->get();

        $byWard = $results->filter(fn ($r) => $r->pollingStation?->ward)
            ->groupBy(fn ($r) => $r->pollingStation->ward)
            ->map(function ($wardResults, $ward) use ($campaign) {
                $wardStations = $campaign->pollingStations()->where('ward', $ward);
                $totalWardStations = $wardStations->count();
                $reportedWardStations = $wardResults->pluck('polling_station_id')->unique()->count();
                $registeredInWard = $wardStations->sum('registered_voters');
                $castInWard = $wardResults->groupBy('polling_station_id')
                    ->map(fn ($g) => $g->max('total_votes_cast'))->sum();

                $candidates = $wardResults->groupBy('candidate_name')->map(fn ($g, $n) => [
                    'candidate_name' => $n,
                    'votes' => $g->sum('votes'),
                ])->sortByDesc('votes')->values();

                return [
                    'ward' => $ward,
                    'total_stations' => $totalWardStations,
                    'reported_stations' => $reportedWardStations,
                    'reporting_percentage' => $totalWardStations > 0 ? round(($reportedWardStations / $totalWardStations) * 100, 1) : 0,
                    'registered_voters' => $registeredInWard,
                    'votes_cast' => $castInWard,
                    'turnout_percentage' => $registeredInWard > 0 ? round(($castInWard / $registeredInWard) * 100, 1) : 0,
                    'candidates' => $candidates,
                    'leading_candidate' => $candidates->first()['candidate_name'] ?? null,
                ];
            })->sortByDesc(fn ($w) => $w['votes_cast'])->values();

        $byConstituency = $results->filter(fn ($r) => $r->pollingStation?->constituency)
            ->groupBy(fn ($r) => $r->pollingStation->constituency)
            ->map(function ($cResults, $constituency) use ($campaign) {
                $cStations = $campaign->pollingStations()->where('constituency', $constituency);
                $totalCStations = $cStations->count();
                $reportedCStations = $cResults->pluck('polling_station_id')->unique()->count();

                $candidates = $cResults->groupBy('candidate_name')->map(fn ($g, $n) => [
                    'candidate_name' => $n,
                    'votes' => $g->sum('votes'),
                ])->sortByDesc('votes')->values();

                return [
                    'constituency' => $constituency,
                    'total_stations' => $totalCStations,
                    'reported_stations' => $reportedCStations,
                    'reporting_percentage' => $totalCStations > 0 ? round(($reportedCStations / $totalCStations) * 100, 1) : 0,
                    'candidates' => $candidates,
                    'leading_candidate' => $candidates->first()['candidate_name'] ?? null,
                ];
            })->sortByDesc(fn ($c) => $c['reported_stations'])->values();

        return response()->json([
            'data' => [
                'candidates' => $candidateTotals,
                'overview' => [
                    'total_stations' => $totalStations,
                    'reported_stations' => $reportedStations,
                    'reporting_percentage' => $totalStations > 0 ? round(($reportedStations / $totalStations) * 100, 1) : 0,
                    'total_votes_cast' => $totalVotesCast,
                    'total_registered' => $totalRegistered,
                    'turnout_percentage' => $totalRegistered > 0 ? round(($totalVotesCast / $totalRegistered) * 100, 1) : 0,
                ],
                'by_ward' => $byWard,
                'by_constituency' => $byConstituency,
                'stations' => $stations,
                'last_updated' => $results->max('updated_at'),
                'campaign_id' => $campaign->id,
                'site' => [
                    'candidate_name' => $site->candidate_name,
                    'position' => $site->position,
                    'constituency' => $site->constituency,
                    'county' => $site->county,
                    'party' => $site->party,
                    'primary_color' => $site->primary_color,
                    'secondary_color' => $site->secondary_color,
                    'logo_url' => $site->logo_url,
                ],
            ],
        ]);
    }
}
