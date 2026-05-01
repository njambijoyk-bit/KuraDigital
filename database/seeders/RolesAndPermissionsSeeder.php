<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions to prevent stale cache from
        // causing the seeder to silently skip role/permission creation.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        app('cache')->forget('spatie.permission.cache');

        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = $this->getRolesWithPermissions();

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        // Flush cache again so the application picks up the newly seeded data.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function getPermissions(): array
    {
        return [
            // Site management
            'site.view', 'site.edit', 'site.manage-settings', 'site.publish',

            // Content management
            'content.view', 'content.create', 'content.edit', 'content.delete',
            'content.publish', 'content.schedule',

            // Manifesto
            'manifesto.view', 'manifesto.create', 'manifesto.edit', 'manifesto.delete', 'manifesto.publish',

            // Events
            'events.view', 'events.create', 'events.edit', 'events.delete', 'events.publish',

            // News
            'news.view', 'news.create', 'news.edit', 'news.delete', 'news.publish',

            // Gallery
            'gallery.view', 'gallery.create', 'gallery.edit', 'gallery.delete',

            // Projects
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',

            // Volunteers
            'volunteers.view', 'volunteers.create', 'volunteers.edit', 'volunteers.delete',
            'volunteers.export', 'volunteers.assign', 'volunteers.import',

            // Contacts
            'contacts.view', 'contacts.respond', 'contacts.archive', 'contacts.export',

            // Voter CRM
            'voters.view', 'voters.create', 'voters.edit', 'voters.delete',
            'voters.import', 'voters.export', 'voters.segment', 'voters.assign',

            // Opponent intelligence
            'opponents.view', 'opponents.create', 'opponents.edit', 'opponents.delete',
            'opponents.view-research', 'opponents.add-research', 'opponents.edit-research',
            'opponents.delete-research', 'opponents.view-confidential',

            // Strategy
            'strategy.view', 'strategy.edit', 'strategy.view-electoral-math',
            'strategy.edit-ward-targets', 'strategy.view-polls',

            // Messaging & communications
            'messaging.view', 'messaging.create', 'messaging.edit', 'messaging.approve',
            'comms.create-sms', 'comms.approve-sms', 'comms.send-sms',
            'comms.create-whatsapp', 'comms.send-whatsapp',
            'comms.create-email', 'comms.send-email',

            // Finance
            'finance.view', 'finance.edit', 'finance.log-expense', 'finance.approve-expense',
            'finance.view-budget', 'finance.edit-budget', 'finance.view-donations',
            'finance.export',

            // Field operations
            'field.view', 'field.manage-agents', 'field.view-agent-locations',
            'field.assign-stations', 'field.submit-survey', 'field.submit-checkin',
            'field.view-reports', 'field.create-reports',

            // Election day
            'eday.view', 'eday.submit-results', 'eday.view-tallies', 'eday.report-incidents',
            'eday.command-centre',

            // Team management
            'team.view', 'team.invite', 'team.assign-roles', 'team.deactivate',
            'team.transfer-ownership',

            // Media library
            'media.view', 'media.upload', 'media.delete', 'media.manage',

            // Audit
            'audit.view', 'audit.export',

            // Reports & analytics
            'reports.view', 'reports.create', 'reports.export',
            'analytics.view', 'analytics.view-dashboards',

            // Campaign management
            'campaign.view', 'campaign.edit', 'campaign.create-child',
            'campaign.delete', 'campaign.manage-settings',
            'campaign.view-all-children',

            // Platform administration
            'platform.manage-tenants', 'platform.view-all-tenants',
            'platform.manage-billing', 'platform.manage-features',
            'platform.view-platform-analytics', 'platform.impersonate',
        ];
    }

    private function getRolesWithPermissions(): array
    {
        $allPermissions = $this->getPermissions();

        return [
            // Tier 1 — Platform
            'platform-owner' => $allPermissions,

            'platform-support' => [
                'platform.view-all-tenants', 'platform.view-platform-analytics',
                'campaign.view', 'team.view', 'audit.view', 'reports.view',
            ],

            // Tier 2 — Campaign Leadership
            'campaign-owner' => array_filter($allPermissions, fn ($p) => !str_starts_with($p, 'platform.')),

            'campaign-director' => array_filter($allPermissions, fn ($p) =>
                !str_starts_with($p, 'platform.') &&
                $p !== 'campaign.delete' &&
                $p !== 'team.transfer-ownership'
            ),

            'deputy-campaign-director' => array_filter($allPermissions, fn ($p) =>
                !str_starts_with($p, 'platform.') &&
                $p !== 'campaign.delete' &&
                $p !== 'team.transfer-ownership'
            ),

            // Tier 3 — Department Heads
            'field-director' => [
                'field.view', 'field.manage-agents', 'field.view-agent-locations',
                'field.assign-stations', 'field.view-reports', 'field.create-reports',
                'volunteers.view', 'volunteers.assign', 'volunteers.export',
                'voters.view', 'voters.segment',
                'eday.view', 'eday.command-centre', 'eday.view-tallies',
                'reports.view', 'reports.create',
                'team.view', 'team.invite',
                'media.view', 'media.upload',
                'analytics.view',
            ],

            'communications-director' => [
                'content.view', 'content.create', 'content.edit', 'content.delete', 'content.publish', 'content.schedule',
                'news.view', 'news.create', 'news.edit', 'news.delete', 'news.publish',
                'messaging.view', 'messaging.create', 'messaging.edit', 'messaging.approve',
                'comms.create-sms', 'comms.approve-sms', 'comms.send-sms',
                'comms.create-whatsapp', 'comms.send-whatsapp',
                'comms.create-email', 'comms.send-email',
                'events.view', 'events.create', 'events.edit', 'events.publish',
                'gallery.view', 'gallery.create', 'gallery.edit',
                'media.view', 'media.upload', 'media.manage',
                'team.view', 'team.invite',
                'reports.view',
                'analytics.view',
            ],

            'digital-director' => [
                'site.view', 'site.edit', 'site.manage-settings', 'site.publish',
                'content.view', 'content.create', 'content.edit', 'content.publish', 'content.schedule',
                'news.view', 'news.create', 'news.edit', 'news.publish',
                'events.view', 'events.create', 'events.edit', 'events.publish',
                'gallery.view', 'gallery.create', 'gallery.edit', 'gallery.delete',
                'manifesto.view', 'manifesto.edit',
                'projects.view', 'projects.edit',
                'comms.create-email', 'comms.send-email',
                'media.view', 'media.upload', 'media.manage',
                'analytics.view', 'analytics.view-dashboards',
                'reports.view',
            ],

            'finance-director' => [
                'finance.view', 'finance.edit', 'finance.log-expense', 'finance.approve-expense',
                'finance.view-budget', 'finance.edit-budget', 'finance.view-donations', 'finance.export',
                'reports.view', 'reports.create', 'reports.export',
                'audit.view',
                'team.view',
            ],

            'strategy-director' => [
                'strategy.view', 'strategy.edit', 'strategy.view-electoral-math',
                'strategy.edit-ward-targets', 'strategy.view-polls',
                'opponents.view', 'opponents.create', 'opponents.edit',
                'opponents.view-research', 'opponents.add-research', 'opponents.edit-research',
                'opponents.view-confidential',
                'voters.view', 'voters.segment',
                'messaging.view', 'messaging.create', 'messaging.edit',
                'reports.view', 'reports.create',
                'analytics.view', 'analytics.view-dashboards',
            ],

            'voter-outreach-director' => [
                'voters.view', 'voters.create', 'voters.edit', 'voters.import',
                'voters.export', 'voters.segment', 'voters.assign',
                'comms.create-sms', 'comms.approve-sms', 'comms.send-sms',
                'comms.create-whatsapp', 'comms.send-whatsapp',
                'volunteers.view', 'volunteers.edit', 'volunteers.assign', 'volunteers.export',
                'field.view', 'field.view-reports',
                'reports.view', 'reports.create',
                'analytics.view',
                'team.view', 'team.invite',
            ],

            'legal-compliance-officer' => [
                'finance.view', 'finance.view-budget', 'finance.view-donations', 'finance.export',
                'opponents.view-research',
                'audit.view', 'audit.export',
                'reports.view', 'reports.export',
                'eday.view', 'eday.view-tallies', 'eday.report-incidents',
                'campaign.view',
            ],

            // Tier 4 — Operational
            'content-editor' => [
                'content.view', 'content.create', 'content.edit',
                'news.view', 'news.create', 'news.edit',
                'events.view', 'events.create', 'events.edit',
                'gallery.view', 'gallery.create', 'gallery.edit',
                'manifesto.view', 'manifesto.edit',
                'projects.view', 'projects.edit',
                'media.view', 'media.upload',
            ],

            'field-coordinator' => [
                'field.view', 'field.manage-agents', 'field.view-agent-locations',
                'field.submit-survey', 'field.submit-checkin',
                'field.view-reports', 'field.create-reports',
                'volunteers.view', 'volunteers.assign',
                'voters.view',
                'media.view', 'media.upload',
            ],

            'regional-coordinator' => [
                'field.view', 'field.manage-agents', 'field.view-agent-locations',
                'field.assign-stations', 'field.view-reports', 'field.create-reports',
                'volunteers.view', 'volunteers.assign', 'volunteers.export',
                'voters.view', 'voters.segment',
                'reports.view', 'reports.create',
                'team.view', 'team.invite',
                'media.view', 'media.upload',
                'analytics.view',
            ],

            'data-analyst' => [
                'voters.view', 'voters.segment',
                'strategy.view', 'strategy.view-electoral-math', 'strategy.view-polls',
                'reports.view', 'reports.create', 'reports.export',
                'analytics.view', 'analytics.view-dashboards',
                'field.view-reports',
            ],

            'research-officer' => [
                'opponents.view', 'opponents.create', 'opponents.edit',
                'opponents.view-research', 'opponents.add-research', 'opponents.edit-research',
                'media.view', 'media.upload',
            ],

            'finance-officer' => [
                'finance.view', 'finance.log-expense', 'finance.view-budget',
                'finance.view-donations',
                'media.view', 'media.upload',
            ],

            'sms-whatsapp-operator' => [
                'comms.create-sms', 'comms.create-whatsapp',
                'comms.create-email',
                'voters.view', 'voters.segment',
                'messaging.view', 'messaging.create',
            ],

            // Tier 5 — Field (mobile app)
            'polling-station-agent' => [
                'eday.submit-results', 'eday.report-incidents', 'eday.view',
                'voters.view',
                'media.upload',
            ],

            'campaign-agent' => [
                'field.submit-survey', 'field.submit-checkin',
                'voters.view',
                'media.upload',
            ],

            'volunteer' => [
                'events.view',
                'content.view',
            ],

            'election-observer' => [
                'eday.view', 'eday.view-tallies', 'eday.report-incidents',
            ],

            // Tier 6 — External
            'coalition-partner' => [
                'events.view',
                'content.view',
                'messaging.view',
                'campaign.view',
            ],

            'donor' => [
                'finance.view-donations',
                'reports.view',
            ],

            'auditor' => [
                'finance.view', 'finance.view-budget', 'finance.view-donations', 'finance.export',
                'audit.view', 'audit.export',
                'reports.view', 'reports.export',
            ],
        ];
    }
}
