<?php

namespace App\Services;

class RoleHierarchy
{
    /**
     * Role tiers from seeder, lower number = higher privilege.
     * Platform-level roles (tier 1) can assign any campaign role.
     * Campaign-level roles can only assign roles at a lower tier than their own.
     */
    private const TIERS = [
        // Tier 1 — Platform (not assignable via invite)
        'platform-owner' => 1,
        'platform-support' => 1,

        // Tier 2 — Campaign Leadership
        'campaign-owner' => 2,
        'campaign-director' => 2,
        'deputy-campaign-director' => 2,

        // Tier 3 — Department Heads
        'field-director' => 3,
        'communications-director' => 3,
        'digital-director' => 3,
        'finance-director' => 3,
        'strategy-director' => 3,
        'voter-outreach-director' => 3,
        'legal-compliance-officer' => 3,

        // Tier 4 — Operational
        'content-editor' => 4,
        'field-coordinator' => 4,
        'regional-coordinator' => 4,
        'data-analyst' => 4,
        'research-officer' => 4,
        'finance-officer' => 4,
        'sms-whatsapp-operator' => 4,

        // Tier 5 — Field (mobile app)
        'polling-station-agent' => 5,
        'campaign-agent' => 5,
        'volunteer' => 5,
        'election-observer' => 5,

        // Tier 6 — External
        'coalition-partner' => 6,
        'donor' => 6,
        'auditor' => 6,
    ];

    /**
     * Platform-level roles that cannot be assigned via campaign invite.
     */
    private const PLATFORM_ROLES = ['platform-owner', 'platform-support'];

    public static function getTier(string $role): ?int
    {
        return self::TIERS[$role] ?? null;
    }

    public static function isPlatformRole(string $role): bool
    {
        return in_array($role, self::PLATFORM_ROLES, true);
    }

    /**
     * Check if the assigner's role can assign the target role.
     *
     * Rules:
     * - Platform roles cannot be assigned via campaign invite
     * - Platform-owner can assign any campaign role
     * - Campaign roles can only assign roles at a strictly lower tier (higher number)
     * - campaign-owner is an exception: can assign roles at the same tier (other leadership roles)
     */
    public static function canAssign(string $assignerRole, string $targetRole): bool
    {
        if (self::isPlatformRole($targetRole)) {
            return false;
        }

        $assignerTier = self::getTier($assignerRole);
        $targetTier = self::getTier($targetRole);

        if ($assignerTier === null || $targetTier === null) {
            return false;
        }

        // Platform-owner can assign any campaign role
        if ($assignerRole === 'platform-owner') {
            return true;
        }

        // campaign-owner can assign same-tier or lower roles
        if ($assignerRole === 'campaign-owner') {
            return $targetTier >= $assignerTier;
        }

        // All other roles can only assign strictly lower-tier roles
        return $targetTier > $assignerTier;
    }
}
