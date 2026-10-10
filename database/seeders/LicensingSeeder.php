<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Platform admin + a demo organisation with every module. Safe to re-run:
 * it never changes an existing admin's password or re-grants demo modules.
 *
 * Set PLATFORM_ADMIN_EMAIL / PLATFORM_ADMIN_PASSWORD in .env to choose the
 * login. If no password is set, a random one is generated and printed once.
 */
class LicensingSeeder extends Seeder
{
    public function run(LicenseService $license): void
    {
        $this->seedPlatformAdmin();
        $this->seedDemoOrganisation($license);
    }

    private function seedPlatformAdmin(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL', 'admin@kuradigital.test');

        $admin = User::where('email', $email)->first();
        if ($admin) {
            $admin->assignRole('platform-owner');
            $this->command?->info("Platform admin already exists: {$email}");

            return;
        }

        $password = env('PLATFORM_ADMIN_PASSWORD');
        $generated = !$password;
        $password ??= Str::password(16, symbols: false);

        $admin = User::create([
            'name' => 'Platform Admin',
            'email' => $email,
            'password' => $password,
            'account_status' => 'active',
            'clearance_level' => 'top_secret',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole('platform-owner');

        $this->command?->info("Platform admin created: {$email}");
        if ($generated) {
            $this->command?->warn("Generated password (shown once, change it after logging in): {$password}");
        }
    }

    private function seedDemoOrganisation(LicenseService $license): void
    {
        $site = Site::where('slug', 'demo-candidate')->first();

        $organisation = Organisation::firstOrCreate(
            ['slug' => 'demo-organisation'],
            ['name' => 'Demo Organisation', 'status' => 'active', 'notes' => 'Seeded demo data.'],
        );

        if ($organisation->wasRecentlyCreated) {
            foreach (array_keys($license->catalogue()) as $module) {
                $license->grant($organisation, $module, notes: 'Demo seed');
            }
        }

        $campaign = Campaign::firstOrCreate(
            ['slug' => 'demo-candidate'],
            [
                'organisation_id' => $organisation->id,
                'site_id' => $site?->id,
                'name' => $site ? "{$site->candidate_name} Campaign" : 'Demo Campaign',
                'level' => 'constituency',
                'election_type' => 'parliamentary',
                'county' => $site?->county,
                'constituency' => $site?->constituency,
                'party' => $site?->party,
                'is_active' => true,
            ],
        );

        // Feature policies are membership-based, so the platform admin joins the demo workspace.
        $admin = User::where('email', env('PLATFORM_ADMIN_EMAIL', 'admin@kuradigital.test'))->first();
        if ($admin) {
            CampaignMember::firstOrCreate(
                ['user_id' => $admin->id, 'campaign_id' => $campaign->id],
                ['role' => 'campaign-owner', 'is_active' => true, 'visibility_scope' => 'national'],
            );
        }
    }
}
