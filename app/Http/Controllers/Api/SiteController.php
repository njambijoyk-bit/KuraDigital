<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\MpesaTransaction;
use App\Models\Site;
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
}
