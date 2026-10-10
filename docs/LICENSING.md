# Licensing

Kura Digital is sold as **modules**. An **organisation** (the paying client) owns one or more **workspaces** (campaigns, programmes) and is granted modules for a term. Billing is **annual**: a grant lasts 12 months and is renewed by a platform admin.

Licensing is enforced **server-side** from the database. There are no licence keys to type in or leak.

## Concepts

| Term | Meaning |
|---|---|
| Organisation | The client. Table `organisations`. Has a status (`active` / `suspended`) and an optional `max_workspaces`. |
| Workspace | A campaign (`campaigns` row). Belongs to one organisation via `campaigns.organisation_id`. Child workspaces share their parent's organisation. |
| Module | A sellable feature set, defined in `config/licensing.php`. |
| Grant | A module licensed to an organisation for a term. Table `organisation_modules` (`starts_at`, `expires_at`, `status`, `limits`). One row per organisation and module. |
| Plan | A named bundle of modules (FieldOS, ImpactOS, SurveyField, CommandOS, CampaignOS) with optional limits. Plans are a shortcut for granting several modules at once; the grants themselves are stored per module. |

A licence covers **every workspace** of the organisation. Limits (agent seats) are shared across them.

## Modules

`core` has no key and is always available: sign-in, workspace CRUD, team and invitations, media, audit log, and the licence view.

| Module key | Covers (under `/api/v1/campaigns/{campaign}/...`) |
|---|---|
| `site` | `site`, `manifesto`, `events`, `news`, `gallery`, `projects`, `volunteers`, `contacts` |
| `field_ops` | `voters`, `field/*`, `field-agents`, `agent-schedules`, `map-data`, `check-ins` |
| `field_reports` | `field-reports` |
| `surveys` | `surveys`, `strategy/polls` |
| `intelligence` | `opponents`, `strategy/notes`, `strategy/ward-targets` |
| `messaging` | `messaging/*` |
| `finance` | `finance/*` (budgets, expenses, donations, M-Pesa) |
| `ledger` | `ledger/*`, `compliance/*` |
| `election_day` | `election-day/*` |
| `analytics` | `reports`, `analytics/*` |

The mapping lives in `routes/api.php` as `Route::middleware('module:<key>')` groups. A test (`test_every_gated_route_names_a_real_module`) fails if a route names a module that isn't in the catalogue.

Not gated yet: the **public site API** (`/api/sites/...`) and the M-Pesa / USSD webhooks. Those serve the public and external services and don't have a workspace user.

## What a blocked request looks like

`403` with a machine-readable `code`:

```json
{ "message": "Finance is not included in your licence.",
  "code": "module_not_licensed", "module": "finance", "expires_at": null }
```

| `code` | Meaning |
|---|---|
| `module_not_licensed` | The organisation never had this module |
| `module_expired` | The term ended; renew to restore access (data is kept) |
| `module_revoked` | Withdrawn by a platform admin |
| `organisation_suspended` | The whole organisation is suspended |
| `no_organisation` | The workspace isn't linked to an organisation |
| `agent_limit` | Field-agent seat limit reached (`limit` is included) |
| `workspace_limit` | Organisation has reached `max_workspaces` |

Platform staff (`platform-owner`, `platform-support`) are never blocked by licensing. Feature policies are still membership-based, so a platform admin must be a member of a workspace to use its features (the seeded admin joins the demo workspace).

## Terms and renewal

* A new grant lasts `config('licensing.term_months')` months (12) from today.
* **Renewing** adds a term to the current end date, so renewing early loses no days. If the grant had already lapsed, the new term starts today. Renewing also reinstates a revoked grant.
* **Revoking** keeps the row (`status = revoked`) so there is history; **re-granting** replaces it with a fresh term.
* There is no grace period. Expiry blocks access immediately; nothing is deleted.

## Limits

Set per grant in `organisation_modules.limits`, e.g. `{"agents": 50}` on `field_ops`. Missing or `null` means unlimited.

* **Agent seats** count field agents across all the organisation's workspaces. Agents with status `inactive` don't use a seat.
* **Workspaces**: `organisations.max_workspaces`.

The numbers in the plans in `config/licensing.php` are **placeholders**. Set them to your real pricing.

## Creating workspaces

`POST /api/v1/campaigns` decides the organisation like this:

1. an explicit `organisation_id` (the user must lead a workspace in that organisation, or be platform-owner);
2. otherwise the `parent_id` workspace's organisation;
3. otherwise a **new organisation with no modules** is created. The creator is locked to core features until a platform admin grants modules.

## Platform admin API

Reads: platform-owner and platform-support. Writes: platform-owner.

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/platform/catalogue` | Modules and plans |
| GET | `/api/v1/platform/organisations?q=` | List / search |
| POST | `/api/v1/platform/organisations` | Create (`name`, optional `plan`, `months`, `max_workspaces`, contact) |
| GET / PUT | `/api/v1/platform/organisations/{id}` | Detail / update (`status` = `active` or `suspended`) |
| POST | `/api/v1/platform/organisations/{id}/modules` | Grant a `module` or a `plan` (`months`, `limits.agents`, `notes`) |
| POST | `/api/v1/platform/organisations/{id}/modules/{module}/renew` | Renew one more term (`months` optional) |
| DELETE | `/api/v1/platform/organisations/{id}/modules/{module}` | Revoke |
| POST | `/api/v1/platform/organisations/{id}/campaigns` | Link a workspace (and its children) |
| DELETE | `/api/v1/platform/organisations/{id}/campaigns/{campaign}` | Unlink |

Workspace members read their own licence at `GET /api/v1/campaigns/{campaign}/license` (module states, expiry, limits, seat usage) so the UI can lock or hide features.

Grants, renewals and revocations are written to the audit log.

## Setup

```bash
php artisan migrate --seed
```

* The migration that adds `campaigns.organisation_id` **grandfathers existing installs**: every existing workspace hierarchy gets its own organisation with all modules for one term, so upgrading doesn't lock anyone out. Fresh installs have no campaigns yet, so nothing is granted.
* `LicensingSeeder` creates the platform admin (`PLATFORM_ADMIN_EMAIL`, default `admin@kuradigital.test`) and a **Demo Organisation** with every module and a demo workspace. Set `PLATFORM_ADMIN_PASSWORD` in `.env` to choose the password; otherwise a random one is printed once. Re-running never changes an existing admin's password or re-grants demo modules.
* `LICENSING_ENFORCED=false` switches every module check off (the PHPUnit config does this so the older suites are unaffected; `LicensingTest` turns it on).

## Not built yet

* Frontend: the admin sidebar doesn't read `/license` yet, and there is no platform-admin screen. Use the API for now.
* Expiry reminders (e.g. an email 30 days before a term ends) and a list of grants expiring soon.
* Automatic activation from payment (M-Pesa) rather than manual grants.
* Gating of the public site API.
