<?php

namespace App\Console\Commands;

use App\Models\CampaignMember;
use Illuminate\Console\Command;

class BackfillCampaignMemberRoles extends Command
{
    protected $signature = 'campaigns:backfill-roles {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill campaign_members.role from users\' global Spatie roles for existing memberships';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $members = CampaignMember::whereNull('role')->with('user.roles')->get();

        if ($members->isEmpty()) {
            $this->info('No campaign members with null roles found.');
            return self::SUCCESS;
        }

        $this->info("Found {$members->count()} member(s) with null roles.");

        $updated = 0;
        $skipped = 0;

        foreach ($members as $member) {
            $user = $member->user;
            if (!$user) {
                $this->warn("Member ID {$member->id}: user not found, skipping.");
                $skipped++;
                continue;
            }

            $roles = $user->getRoleNames()->reject(fn ($r) => str_starts_with($r, 'platform-'));

            if ($roles->isEmpty()) {
                $this->warn("Member ID {$member->id} (user: {$user->email}): no campaign-level role found, skipping.");
                $skipped++;
                continue;
            }

            $role = $roles->first();

            if ($dryRun) {
                $this->line("  [DRY RUN] Member ID {$member->id} (user: {$user->email}, campaign: {$member->campaign_id}) → {$role}");
            } else {
                $member->update(['role' => $role]);
                $this->line("  Updated Member ID {$member->id} (user: {$user->email}, campaign: {$member->campaign_id}) → {$role}");
            }

            $updated++;
        }

        $action = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$action} {$updated} member(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
