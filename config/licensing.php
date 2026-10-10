<?php

/*
|--------------------------------------------------------------------------
| Licensing
|--------------------------------------------------------------------------
|
| An organisation owns one or more workspaces (campaigns). Features are sold
| as modules and granted to the organisation for a term (12 months by default).
| Grants live in the organisation_modules table; this file is the catalogue of
| what can be granted and the plans that bundle modules together.
|
| The "core" feature set (auth, workspaces, team, media, audit log, license
| view) is always available and has no module key.
|
*/

return [

    // Turn off to skip all module checks (tests, local hacking).
    'enforced' => env('LICENSING_ENFORCED', true),

    // Length of one licence term, in months.
    'term_months' => 12,

    'modules' => [
        'site' => [
            'name' => 'Public Site & Content',
            'description' => 'Public site, manifesto, events, news, gallery, projects, volunteers and contact messages.',
        ],
        'field_ops' => [
            'name' => 'Field Operations',
            'description' => 'Field agents, schedules, check-ins, canvassing, voters and the live map.',
            'limits' => ['agents' => 'Maximum field agents across all workspaces'],
        ],
        'field_reports' => [
            'name' => 'Field Reports',
            'description' => 'Offline field reports with photo and media evidence.',
        ],
        'surveys' => [
            'name' => 'Surveys & Polls',
            'description' => 'Survey builder, responses, results and polls.',
        ],
        'intelligence' => [
            'name' => 'Intelligence & Strategy',
            'description' => 'Opponent research, strategy notes and ward targets.',
        ],
        'messaging' => [
            'name' => 'Messaging',
            'description' => 'SMS, WhatsApp and email templates and campaigns.',
        ],
        'finance' => [
            'name' => 'Finance',
            'description' => 'Budgets, expenses, donations and M-Pesa collection.',
        ],
        'ledger' => [
            'name' => 'Ledger & Compliance',
            'description' => 'Double-entry ledger, financial statements, compliance alerts and encrypted exports.',
        ],
        'election_day' => [
            'name' => 'Election Day',
            'description' => 'Polling stations, tallies, result forms, incidents and the situation room.',
        ],
        'analytics' => [
            'name' => 'Reports & Analytics',
            'description' => 'Generated reports and analytics dashboards.',
        ],
    ],

    /*
    | Plans are named bundles of modules, with optional limits per module.
    | The numbers below are placeholders: adjust them to your real pricing.
    | A null / missing limit means unlimited.
    */
    'plans' => [
        'fieldos' => [
            'name' => 'FieldOS',
            'description' => 'Field operations for distributed teams.',
            'modules' => ['field_ops', 'field_reports', 'analytics'],
            'limits' => ['field_ops' => ['agents' => 50]],
        ],
        'impactos' => [
            'name' => 'ImpactOS',
            'description' => 'Project monitoring and accountability.',
            'modules' => ['site', 'field_reports', 'surveys', 'finance', 'analytics'],
            'limits' => [],
        ],
        'surveyfield' => [
            'name' => 'SurveyField',
            'description' => 'Field research and survey operations.',
            'modules' => ['surveys', 'field_ops', 'field_reports', 'analytics'],
            'limits' => ['field_ops' => ['agents' => 25]],
        ],
        'commandos' => [
            'name' => 'CommandOS',
            'description' => 'Operations command centre for events and large operations.',
            'modules' => ['election_day', 'field_ops', 'analytics'],
            'limits' => ['field_ops' => ['agents' => 100]],
        ],
        'campaignos' => [
            'name' => 'CampaignOS',
            'description' => 'Everything: the full campaign and advocacy platform.',
            'modules' => [
                'site', 'field_ops', 'field_reports', 'surveys', 'intelligence',
                'messaging', 'finance', 'ledger', 'election_day', 'analytics',
            ],
            'limits' => [],
        ],
    ],
];
