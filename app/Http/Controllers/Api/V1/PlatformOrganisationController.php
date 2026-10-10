<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Organisation;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Platform admin: organisations and their module licences.
 * Reads are open to platform-owner and platform-support; writes to platform-owner only.
 */
class PlatformOrganisationController extends Controller
{
    public function __construct(private LicenseService $license)
    {
    }

    public function catalogue(Request $request): JsonResponse
    {
        $this->staff($request);

        return response()->json([
            'term_months' => (int) config('licensing.term_months', 12),
            'modules' => collect($this->license->catalogue())
                ->map(fn ($m, $key) => ['key' => $key] + $m)->values(),
            'plans' => collect($this->license->plans())
                ->map(fn ($p, $key) => ['key' => $key] + $p)->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->staff($request);

        $organisations = Organisation::query()
            ->withCount('campaigns')
            ->when($request->query('q'), function ($query, $q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('contact_email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(20);

        return response()->json($organisations);
    }

    public function store(Request $request): JsonResponse
    {
        $this->owner($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:organisations,slug'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'max_workspaces' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string'],
            'plan' => ['nullable', Rule::in(array_keys($this->license->plans()))],
            'months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $organisation = Organisation::create([
            'slug' => $validated['slug'] ?? Str::slug($validated['name']) . '-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ] + collect($validated)->except(['plan', 'months', 'slug'])->all());

        if (!empty($validated['plan'])) {
            $this->license->grantPlan($organisation, $validated['plan'], $validated['months'] ?? null, $request->user());
        }

        return response()->json([
            'message' => 'Organisation created.',
        ] + $this->detail($organisation), 201);
    }

    public function show(Request $request, Organisation $organisation): JsonResponse
    {
        $this->staff($request);

        return response()->json($this->detail($organisation));
    }

    public function update(Request $request, Organisation $organisation): JsonResponse
    {
        $this->owner($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'max_workspaces' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $organisation->update($validated);

        return response()->json(['message' => 'Organisation updated.'] + $this->detail($organisation->fresh()));
    }

    /** Grant a single module or a whole plan for a fresh term. */
    public function grant(Request $request, Organisation $organisation): JsonResponse
    {
        $this->owner($request);

        $validated = $request->validate([
            'module' => ['required_without:plan', 'nullable', Rule::in(array_keys($this->license->catalogue()))],
            'plan' => ['required_without:module', 'nullable', Rule::in(array_keys($this->license->plans()))],
            'months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'limits' => ['nullable', 'array'],
            'limits.agents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!empty($validated['plan'])) {
            $this->license->grantPlan(
                $organisation, $validated['plan'], $validated['months'] ?? null,
                $request->user(), $validated['notes'] ?? null,
            );
        } else {
            $this->license->grant(
                $organisation, $validated['module'], $validated['months'] ?? null,
                $validated['limits'] ?? null, null, $request->user(), $validated['notes'] ?? null,
            );
        }

        return response()->json(['message' => 'Licence granted.'] + $this->detail($organisation), 201);
    }

    public function renew(Request $request, Organisation $organisation, string $module): JsonResponse
    {
        $this->owner($request);

        $validated = $request->validate(['months' => ['nullable', 'integer', 'min:1', 'max:120']]);

        $grant = $organisation->modules()->where('module', $module)->first();
        if (!$grant) {
            return response()->json(['message' => 'This organisation has no licence for that module.'], 404);
        }

        $this->license->renew($grant, $validated['months'] ?? null, $request->user());

        return response()->json(['message' => 'Licence renewed.'] + $this->detail($organisation));
    }

    public function revoke(Request $request, Organisation $organisation, string $module): JsonResponse
    {
        $this->owner($request);

        $grant = $organisation->modules()->where('module', $module)->first();
        if (!$grant) {
            return response()->json(['message' => 'This organisation has no licence for that module.'], 404);
        }

        $this->license->revoke($grant);

        return response()->json(['message' => 'Licence revoked.'] + $this->detail($organisation));
    }

    /** Link an existing workspace (and its child workspaces) to this organisation. */
    public function attachCampaign(Request $request, Organisation $organisation): JsonResponse
    {
        $this->owner($request);

        $validated = $request->validate(['campaign_id' => ['required', 'integer', 'exists:campaigns,id']]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        $this->assignTree($campaign, $organisation->id);

        return response()->json(['message' => 'Workspace linked.'] + $this->detail($organisation));
    }

    public function detachCampaign(Request $request, Organisation $organisation, Campaign $campaign): JsonResponse
    {
        $this->owner($request);

        if ($campaign->organisation_id !== $organisation->id) {
            return response()->json(['message' => 'That workspace does not belong to this organisation.'], 404);
        }

        $this->assignTree($campaign, null);

        return response()->json(['message' => 'Workspace unlinked.'] + $this->detail($organisation));
    }

    private function assignTree(Campaign $campaign, ?int $organisationId): void
    {
        $campaign->update(['organisation_id' => $organisationId]);

        foreach ($campaign->children as $child) {
            $this->assignTree($child, $organisationId);
        }
    }

    private function detail(Organisation $organisation): array
    {
        return [
            'organisation' => $organisation->fresh(),
            'license' => $this->license->summary($organisation),
            'campaigns' => $organisation->campaigns()
                ->get(['id', 'name', 'slug', 'level', 'parent_id', 'is_active']),
        ];
    }

    private function staff(Request $request): void
    {
        abort_unless($request->user()?->hasRole(['platform-owner', 'platform-support']), 403, 'Platform staff only.');
    }

    private function owner(Request $request): void
    {
        abort_unless($request->user()?->hasRole('platform-owner'), 403, 'Platform owner only.');
    }
}
