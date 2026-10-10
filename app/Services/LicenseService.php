<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\FieldAgent;
use App\Models\Organisation;
use App\Models\OrganisationModule;
use App\Models\User;
use InvalidArgumentException;

class LicenseService
{
    public const OK = 'ok';
    public const NO_ORGANISATION = 'no_organisation';
    public const ORGANISATION_SUSPENDED = 'organisation_suspended';
    public const NOT_LICENSED = 'module_not_licensed';
    public const EXPIRED = 'module_expired';
    public const REVOKED = 'module_revoked';

    public function enforced(): bool
    {
        return (bool) config('licensing.enforced', true);
    }

    /** @return array<string, array> */
    public function catalogue(): array
    {
        return config('licensing.modules', []);
    }

    /** @return array<string, array> */
    public function plans(): array
    {
        return config('licensing.plans', []);
    }

    public function moduleExists(string $module): bool
    {
        return array_key_exists($module, $this->catalogue());
    }

    /** Platform staff are never blocked by licensing. */
    public function bypasses(?User $user): bool
    {
        return $user !== null && $user->hasRole(['platform-owner', 'platform-support']);
    }

    /**
     * Is this module usable in this workspace right now? Returns one of the
     * status constants above; only OK means the request may proceed.
     */
    public function check(Campaign $campaign, string $module): string
    {
        $organisation = $campaign->organisation;

        if (!$organisation) {
            return self::NO_ORGANISATION;
        }

        if ($organisation->isSuspended()) {
            return self::ORGANISATION_SUSPENDED;
        }

        $grant = $organisation->modules()->where('module', $module)->first();

        if (!$grant) {
            return self::NOT_LICENSED;
        }

        return match ($grant->state()) {
            OrganisationModule::ACTIVE => self::OK,
            OrganisationModule::REVOKED => self::REVOKED,
            default => self::EXPIRED,
        };
    }

    public function grantFor(Campaign $campaign, string $module): ?OrganisationModule
    {
        return $campaign->organisation?->modules()->where('module', $module)->first();
    }

    /**
     * Grant (or re-grant) a module for a fresh term starting now. Re-granting
     * replaces an expired or revoked grant; use renew() to extend a live one.
     */
    public function grant(
        Organisation $organisation,
        string $module,
        ?int $months = null,
        ?array $limits = null,
        ?string $plan = null,
        ?User $by = null,
        ?string $notes = null,
    ): OrganisationModule {
        $this->assertModule($module);
        $months = $this->months($months);

        return $organisation->modules()->updateOrCreate(
            ['module' => $module],
            [
                'plan' => $plan,
                'status' => 'active',
                'limits' => $limits ?: null,
                'starts_at' => now(),
                'expires_at' => now()->addMonths($months),
                'granted_by' => $by?->id,
                'notes' => $notes,
            ],
        );
    }

    /** @return OrganisationModule[] */
    public function grantPlan(
        Organisation $organisation,
        string $plan,
        ?int $months = null,
        ?User $by = null,
        ?string $notes = null,
    ): array {
        $definition = $this->plans()[$plan] ?? throw new InvalidArgumentException("Unknown plan '{$plan}'.");

        $grants = [];
        foreach ($definition['modules'] as $module) {
            $limits = $definition['limits'][$module] ?? null;
            $grants[] = $this->grant($organisation, $module, $months, $limits, $plan, $by, $notes);
        }

        return $grants;
    }

    /**
     * Extend a grant by one more term. A live grant is extended from its
     * current end date (no days are lost by renewing early); a lapsed one is
     * extended from today. Renewing also reinstates a revoked grant.
     */
    public function renew(OrganisationModule $grant, ?int $months = null, ?User $by = null): OrganisationModule
    {
        $months = $this->months($months);
        $base = $grant->expires_at->isFuture() ? $grant->expires_at : now();

        $grant->forceFill([
            'status' => 'active',
            'expires_at' => $base->copy()->addMonths($months),
            'granted_by' => $by?->id ?? $grant->granted_by,
        ])->save();

        return $grant->refresh();
    }

    public function revoke(OrganisationModule $grant): OrganisationModule
    {
        $grant->forceFill(['status' => 'revoked'])->save();

        return $grant->refresh();
    }

    /** Limit value for a module in this workspace's organisation; null = unlimited. */
    public function limit(Campaign $campaign, string $module, string $key): ?int
    {
        return $this->grantFor($campaign, $module)?->limit($key);
    }

    /** Field agents across every workspace of the organisation (inactive ones free their seat). */
    public function agentCount(Organisation $organisation): int
    {
        return FieldAgent::whereIn('campaign_id', $organisation->campaigns()->pluck('id'))
            ->where('status', '!=', 'inactive')
            ->count();
    }

    public function agentLimitReached(Campaign $campaign): bool
    {
        if (!$this->enforced() || !$campaign->organisation) {
            return false;
        }

        $limit = $this->limit($campaign, 'field_ops', 'agents');

        return $limit !== null && $this->agentCount($campaign->organisation) >= $limit;
    }

    public function workspaceLimitReached(Organisation $organisation): bool
    {
        return $organisation->max_workspaces !== null
            && $organisation->campaigns()->count() >= $organisation->max_workspaces;
    }

    /** Everything the UI needs about an organisation's licence. */
    public function summary(Organisation $organisation): array
    {
        $grants = $organisation->modules()->get()->keyBy('module');

        $modules = [];
        foreach ($this->catalogue() as $key => $definition) {
            $grant = $grants->get($key);
            $modules[] = [
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'state' => $grant ? $grant->state() : 'not_licensed',
                'plan' => $grant?->plan,
                'limits' => $grant?->limits,
                'starts_at' => $grant?->starts_at,
                'expires_at' => $grant?->expires_at,
            ];
        }

        return [
            'organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'status' => $organisation->status,
                'max_workspaces' => $organisation->max_workspaces,
                'workspaces' => $organisation->campaigns()->count(),
                'field_agents' => $this->agentCount($organisation),
            ],
            'modules' => $modules,
        ];
    }

    private function months(?int $months): int
    {
        $months ??= (int) config('licensing.term_months', 12);

        if ($months < 1 || $months > 120) {
            throw new InvalidArgumentException('Term must be between 1 and 120 months.');
        }

        return $months;
    }

    private function assertModule(string $module): void
    {
        if (!$this->moduleExists($module)) {
            throw new InvalidArgumentException("Unknown module '{$module}'.");
        }
    }
}
