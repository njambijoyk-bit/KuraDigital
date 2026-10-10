# Kura Digital — Project Summary

_Snapshot of the repository as of 2026-10-10, branch `claude/brave-albattani-qw9dvl`. Based on reading the code and running it; items marked **planned** are described in the docs but not implemented._

## What it is

A white-label, multi-tenant campaign platform for Kenyan political candidates. It has two halves:

1. **Public campaign site** per candidate at `/:slug` (bilingual EN/SW, themed, mobile-first).
2. **Campaign-management admin app** (React SPA) backed by a Laravel API: field operations, election-day monitoring, finance with ledger and compliance, messaging, surveys, analytics and reporting.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 10, PHP 8.1+ (tested on 8.3), Sanctum auth, spatie/laravel-permission |
| Frontend | React (package.json pins 19.x), React Router 7, Zustand, Tailwind, Vite 5 |
| Maps / realtime | Leaflet (+ heatmap, clustering), Laravel Echo / Pusher client |
| Auth extras | TOTP MFA, WebAuthn (biometric) via `@simplewebauthn/browser` |
| Offline / PWA | Service worker (`public/sw.js`), manifest, IndexedDB offline sync |
| Database | **MySQL 8 today** (config default and `.env.example`). PostgreSQL also works (migrations, seeders and tests verified on 16; planned as the main database in `docs/TECH_DECISIONS.md`, Decision 4). SQLite does not work (a migration alters a column, which needs `doctrine/dbal`). |
| Integrations in code | Africa's Talking SMS/USSD, Twilio SMS (fallback), M-Pesa Daraja, Google Vision, OpenAI Whisper, Cloudflare R2 storage settings, Mailgun/log mail |

## Public site

Routes under `/:slug`: Home, About, Manifesto, Projects, Events (+ detail), News (+ detail), Gallery, Contact, Volunteer, Register, Donate, Results, Surveys (+ fill form). The theme, logo, colours, slogan, WhatsApp button and social links come from the `sites` record. A `PlatformHomePage` exists for the root.

Public API (`/api/sites/{slug|id}/...`): site, manifesto, events, news, gallery, projects, contact, volunteers, supporter registration.

## Admin app (pages)

Dashboard, Campaigns, Team, Site Settings, Manifesto, Events, News, Gallery, Projects, Media, Contacts, Volunteers, Voters, Canvassing, Field Ops, Field Reports (+ capture), Surveys, Messaging, Strategy, Opponents, Map, Analytics, Reports, Election Day, Situation Room, Finance, Ledger, Compliance Dashboard, Audit Log, Login/Register. Supporting pieces: permission gates, biometric challenge/setup modals, encrypted-export modal, offline indicator, install prompt, schedule calendar and coverage view, map layers.

## Backend

- **API:** `routes/api.php` (~490 lines, ~296 route definitions) under `/api/v1`. 33 controllers in `app/Http/Controllers/Api/V1`. Campaign-scoped routes live under `campaigns/{campaign}` behind `auth:sanctum`, `account.active` and `campaign.access` middleware. Auth endpoints and campaign creation/invites are throttled. Webhooks: M-Pesa and USSD.
- **Models:** ~56 Eloquent models and 33 policies (one per resource).
- **Services:** ledger and auto-posting, chart of accounts, financial reports, finance encryption and key management, ABAC and role hierarchy, compliance, encrypted export, SMS gateways (Africa's Talking / Twilio), M-Pesa, TOTP, WebAuthn, Google Vision, Whisper, sentiment analysis, thumbnails, outreach email.
- **Events and jobs:** `IncidentReported`, `SituationRoomUpdate`, `TallyResultSubmitted/Verified`; jobs for field-media processing and message-campaign sending.
- **Console commands:** `BackfillCampaignMemberRoles`, `BackfillLedger`, `ImportIebcStations`.
- **Middleware:** `EnsureCampaignAccess`, `EnsureAccountActive`, `RequireBiometricVerification` (`biometric:high` / `biometric:standard`).

## Feature areas

| Area | What exists |
|---|---|
| **Access control** | 26 seeded RBAC roles (136 permissions) in tiers (platform-owner/support, campaign-owner/director, function directors, coordinators, agents, volunteers, observers, donors, auditors); role hierarchy limits who can assign which roles; team invitations; clearance levels on media/content; ABAC for finance; audit logging via an `Auditable` trait |
| **Field operations** | Field agents, schedules/coverage, check-ins, canvassing assignments, voters and voter interactions, ward targets, field reports with media, geographic map and heatmap |
| **Election day** | Polling stations (IEBC import command), tally results and result forms (34A/34B/34C), incidents, activity feed, live situation room over broadcasting |
| **Finance** | Donations, expenses, budgets, funds, M-Pesa transactions, **double-entry ledger** (accounts, journal entries/lines, unbalanced-entry guard), financial reports, compliance alerts and settings, **field-level encryption**, biometric step-up for sensitive actions, encrypted exports |
| **Messaging** | Message templates and campaigns, SMS/WhatsApp/email sending via queued job, message logs |
| **Intelligence** | Opponents and research (with sentiment), strategy notes, surveys and responses, polls, analytics and reports |
| **Content/CMS** | Manifesto pillars, events and RSVPs, news (EN/SW), gallery, projects, newsletter subscribers, contact messages, volunteers |

## Data

55 migrations (all run cleanly on MariaDB 10.11 and PostgreSQL 16). The seeder creates roles/permissions, a **demo site** (`demo-candidate`, "John Kamau, MP Starehe"), election-day sample data and surveys. `LicensingSeeder` adds a platform admin (see `docs/LICENSING.md`) and a Demo Organisation with every module.

## Testing

`php artisan test` (PHPUnit): 9 feature test files, 258 tests (37 cover licensing).

Last run (MariaDB and PostgreSQL, separate test databases): **252 passed, 6 failed** on each.

| Failing test | Area |
|---|---|
| `FinanceTest` > finance director can record donation | donation row not found after POST |
| `FinanceTest` > c2b confirmation creates donation | donation row not found |
| `RbacAbacTest` > high clearance user can create restricted … | not investigated |
| `RbacAbacTest` > research officer can add research | not investigated |
| `ReportsAnalyticsTest` > can generate campaign overview … | not investigated |
| `StrategyMessagingTest` > comms director can send sms … | expects status `sending`, finds `sent` (queue is `sync`, so the job completes inline) |

Not yet root-caused; the messaging one looks like a test expectation vs. `QUEUE_CONNECTION=sync`. Note: tests use `RefreshDatabase` against whatever `DB_DATABASE` is set, and `phpunit.xml` has its SQLite override commented out, so point tests at a separate database.

Frontend has no automated tests. `.agents/skills/` holds manual testing guides for the API, field reports and finance compliance.

## Running it locally

```bash
composer install
npm install --legacy-peer-deps
cp .env.example .env && php artisan key:generate
# create DB, set DB_* in .env
php artisan migrate --seed
npm run build            # or `npm run dev` for hot reload
php artisan serve        # http://localhost:8000/demo-candidate
```

## Licensing

Features are sold as annual **modules** granted to an **organisation** that owns one or more workspaces (campaigns). Enforcement is server-side (`module:<key>` middleware on 224 routes across 10 modules, plus agent and workspace limits), with a platform-admin API for organisations, grants, renewals and revocations. Core features (auth, workspaces, team, media, audit log) are always available. Details: `docs/LICENSING.md`. The 5 sellable bundles (FieldOS, ImpactOS, SurveyField, CommandOS, CampaignOS) are plans in `config/licensing.php`.

## Known issues / notes

- **Fixed on this branch:** `result_forms` migration ran before `polling_stations` existed, breaking `migrate` on any fresh database. Renamed to `2026_05_03_210001_create_result_forms_table.php`. Databases that already ran the old name need their `migrations` table row updated.
- The 6 failing tests above.
- The frontend bundle has chunks over 500 kB (Vite warning); no code-splitting yet.
- `docs/TECH_DECISIONS.md` describes a roadmap beyond the code: Tauri desktop app, React Native mobile app, PostgreSQL/PostGIS, Laravel Reverb, FCM, Resend, Meilisearch, Cloudflare. Only the Laravel API and React web dashboard exist in this repo. Real-time currently uses Pusher-style broadcasting (`BROADCAST_DRIVER=log` by default).
- `.env.example` has no secrets; all third-party keys (SMS, M-Pesa, OpenAI, Vision, R2) are empty, so those integrations are unexercised here.

## Recent history

129 commits. Latest merged PRs: #59 situation-room NaN lat/lng fix, #60 finance compliance (encryption, biometric, ABAC/RBAC), #61 receipt-number migration fix, #62 testing-skill docs, #63 double-entry ledger.
