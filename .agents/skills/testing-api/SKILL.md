# KuraDigital API Testing

## Overview
KuraDigital is a Laravel 10 campaign manager platform with a React frontend. The API lives under `/api/v1/` and uses Sanctum token auth.

## Local Dev Setup

### Prerequisites
- PHP 8.3+ (from `ppa:ondrej/php`)
- Composer 2.x
- Node.js (for frontend, not needed for API-only testing)
- MySQL 8 (default) or SQLite
- `spatie/laravel-permission` package (install via `composer require spatie/laravel-permission` if missing)

### Setup Steps
```bash
cd /home/ubuntu/repos/KuraDigital
composer install --no-interaction

# .env.example already defaults to MAIL_MAILER=log (fixed in PR #27).
# If your .env still has MAIL_MAILER=smtp, update it:
sed -i 's/MAIL_MAILER=smtp/MAIL_MAILER=log/' .env

php artisan migrate:fresh --seed
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000 &
```

The `migrate:fresh --seed` command runs `RolesAndPermissionsSeeder` which creates 26 roles and 126 permissions via Spatie Laravel-Permission.

### Seeding Reliability
The seeder now includes explicit Spatie permission cache flushes before and after seeding (fixed in PR #27). Verify roles were created:
```bash
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM roles;"
# Should return 26. If 0 (unlikely after the cache flush fix), run:
php artisan db:seed --class=RolesAndPermissionsSeeder
```
If you encounter 0 roles after seeding, the Spatie permission cache might still be causing issues — try `php artisan cache:clear` before re-seeding.

### PHPUnit and Live API Testing Interaction
**Critical:** Running `php artisan test` (PHPUnit) uses the `RefreshDatabase` trait which wipes the SQLite database. If you are running live API tests (curl) in parallel with PHPUnit tests, the PHPUnit run will destroy all your test users, tokens, campaigns, and data. Always run PHPUnit tests BEFORE setting up live API test data, or re-seed after PHPUnit completes.

## API Testing Patterns

All API requests must include:
- `Accept: application/json` header (without this, Laravel returns HTML error pages)
- `Content-Type: application/json` for POST/PUT requests with JSON body
- `Authorization: Bearer {token}` for authenticated endpoints

### Auth Flow
```bash
# Register
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"Password1","password_confirmation":"Password1"}'

# Login (returns token)
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Password1"}'

# Use token for authenticated requests
curl -s http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

### Rate Limiting on Auth Endpoints
The register and login endpoints have rate limiting. If you hit "Too Many Attempts" (429), clear the cache:
```bash
php artisan cache:clear
```
Alternatively, create test users via tinker to avoid rate limits entirely:
```bash
php artisan tinker --execute="
\$user = \App\Models\User::create(['name'=>'Test','email'=>'test@example.com','password'=>bcrypt('Password1'),'clearance_level'=>'public','account_status'=>'active']);
echo 'TOKEN=' . \$user->createToken('test')->plainTextToken;
"
```

### Campaign Creation
When creating a campaign, `election_type` must be one of: `presidential`, `gubernatorial`, `senatorial`, `woman_rep`, `parliamentary`, `mca`, `other`. The `level` must be: `national`, `county`, `constituency`, or `ward`.

**Important:** `CampaignPolicy::create` might require the user to have an existing leadership membership (campaign-owner, campaign-director, deputy-campaign-director) or be a `platform-owner`. If new user registration + campaign creation fails with 403, you may need to assign `platform-owner` role via tinker:
```bash
php artisan tinker --execute="\App\Models\User::find(1)->assignRole('platform-owner');"
```

### Campaign-Scoped Routes
Routes under `/api/v1/campaigns/{campaign}/...` require the authenticated user to be a member of the campaign. Creating a campaign auto-adds the creator as `campaign-owner`.

### Multi-Tenant Testing
To test tenant isolation, register two separate users. User A creates a campaign, User B should get 403 on all campaign-scoped routes until invited. The error message is: "You are not a member of this campaign."

### Team Invite Flow
1. User A: `POST /campaigns/{id}/invite` with `{email, role, assigned_wards}`
2. Extract `token` from invitation response (this is the **raw** token)
3. User B: `POST /invitations/accept` with `{token}` (the raw token from step 2)
4. User B can now access campaign-scoped routes

**Note:** The mail driver must be set to `log` (not `smtp`) for local testing. Otherwise invite requests fail with SMTP connection errors (`mailpit:1025` not available). The `.env.example` now defaults to `MAIL_MAILER=log`. The raw token is returned in the API response; the DB stores an HMAC hash.

### RBAC Testing Patterns
All 13 controllers enforce RBAC via `$this->authorize()` calls. To test:
1. Create users with different roles via tinker (faster than API registration)
2. Create campaign memberships with specific roles
3. Verify authorized roles get 200/201 and unauthorized roles get 403

Key role-permission boundaries to test:
- `volunteer` has zero opponent permissions → expect 403 on all opponent endpoints
- `research-officer` can create opponents and research but lacks `opponents.view-confidential`
- `content-editor` can create events/news/gallery but cannot delete events
- `finance-director` cannot create events
- `donor` cannot view events

### ABAC / Clearance-Level Testing
Some features (e.g., opponent research, media) use clearance levels (`public`, `internal`, `confidential`, `restricted`/`top_secret`). To test ABAC:
1. Create resources at different clearance levels as `campaign-owner` (has all permissions)
2. Invite a lower-privilege role (e.g., `research-officer`) — they lack `opponents.view-confidential`
3. Set the user's `clearance_level` via DB: `php artisan tinker --execute="\App\Models\User::find(N)->update(['clearance_level' => 'public']);"`
4. Verify the lower role cannot create items above their clearance (expect 403 with "Cannot classify above your clearance level")
5. Verify the lower role CAN see/create items at or below their clearance (expect 200/201)

**Registration does not set `clearance_level`** — it defaults to null. You must set it via DB for clearance testing. The `hasClearance()` method treats null as level 0 (public).

Valid `clearance_level` values: `public`, `internal`, `confidential`, `top_secret`. The `visibility_scope` on campaign_members must be one of: `own_campaign`, `county`, `national`.

### Role Hierarchy Testing
The `RoleHierarchy` service (in `app/Services/RoleHierarchy.php`) defines 6 tiers. To test:
1. As `volunteer` (tier 5), try to invite someone as `campaign-owner` (tier 2) → expect 403 "You cannot assign a role at or above your own level."
2. As `campaign-owner` (tier 2), invite as `volunteer` (tier 5) → expect 201
3. Owner cannot modify own role → `PUT /campaigns/{id}/members/{own_id}` with `{"role": "volunteer"}` → expect 403

### Geographic ABAC Testing
Some resources (events, projects, contact messages) have geographic fields (ward, constituency, county). To test:
1. Create a resource with a specific ward/constituency/county
2. Invite a user with `assigned_wards: ["ward-a"]` (geographic restriction)
3. User should get 403 when accessing resources in `ward-b`
4. The 403 message is: "You do not have access to this geographic area."

Geographic ABAC is enforced on `show`, `update`, `destroy` (individual actions), not just `index` (list).

### Nested Resource Testing
For resources nested under others (e.g., `/opponents/{id}/research/{id}`), always verify:
- Ownership checks: resource must belong to the specified parent (expect 404 if mismatched)
- Cascade deletes: deleting a parent removes all children (verify via overview/list endpoints)
- Cross-resource endpoints (e.g., `/opponents/research/recent`) return data across all parents

### Media Upload
Use `multipart/form-data` (not JSON) for file uploads:
```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/campaigns/{id}/media \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/file.txt" \
  -F "collection=documents" \
  -F "tags[]=tag1"
```

### Security Fix Testing Patterns

**Self-role modification:** Owner tries `PUT /campaigns/{id}/members/{own_member_id}` with `{"role": "volunteer"}` → expect 403.

**Email mismatch on invite:** Create invite for `alice@test.com`, accept as `bob@test.com` → expect 403 "different email address".

**Suspended account:** Set `account_status` to `suspended` via DB, then access any campaign endpoint → expect 403 "Your account has been suspended." (not "not a member").

**Members list format:** `GET /campaigns/{id}/members` returns paginated response with `data` array (not `members`).

### Opponent Threat-Level Sorting
The opponents list endpoint (`GET /campaigns/{id}/opponents`) sorts by threat level using a `CASE WHEN` statement that works on both MySQL and SQLite. Expected sort order: `critical` (1) → `high` (2) → `medium` (3) → `low` (4) → other (5). Verify by creating opponents with different threat levels and checking the response order.

## Response Format Notes

- **Audit logs** use Laravel pagination: top-level keys are `current_page`, `data`, `total`, etc. The actual log entries are in the `data` array, not `audit_logs`.
- **SWOT entries** are returned grouped by type: `{swot: {strengths: [], weaknesses: [], opportunities: [], threats: []}, total: N}`
- **Content endpoints** (manifesto, events, news, etc.) require a site to exist first — create one via `POST /campaigns/{id}/site` before testing content CRUD.

## Known Issues

- **Audit log `campaign_id` for Campaign model**: Fixed — Campaign model has `getAuditCampaignId()` returning `$this->id`. All models with `Auditable` trait should have this method if they don't have a direct `campaign_id` column.
- **Vite manifest error on non-API routes**: The frontend build might not exist locally. This only affects non-API routes. API routes work fine with `Accept: application/json`.
- **MFA in dev**: MFA verification accepts any 6-digit code in non-production environments.
- **Frontend field name alignment**: Frontend forms may use different field names than the API expects. When testing content forms (events, site settings, manifesto), verify field names match the controller's validation rules.
- **CampaignPolicy::create might be too restrictive**: If new users can't create campaigns, check whether `CampaignPolicy::create` requires existing leadership memberships. This might need a fix to allow first-time campaign creation.

### Resolved Issues (for reference)
- **FIELD() MySQL function on SQLite** — Fixed in PR #27. `OpponentController.php` now uses `CASE WHEN` instead of MySQL-specific `FIELD()`. The PHPUnit geographic filter test for opponents no longer needs to be skipped.
- **MAIL_MAILER=smtp default** — Fixed in PR #27. `.env.example` now defaults to `MAIL_MAILER=log` so local dev invite endpoints work without mailpit.
- **Seeder cache inconsistency** — Fixed in PR #27. `RolesAndPermissionsSeeder` now flushes Spatie permission cache before and after seeding, preventing intermittent empty roles table.

## Password Requirements
- Minimum 8 characters
- Must contain uppercase and lowercase letters
- Must contain at least one number
- Must include `password_confirmation` field

## Roles Available for Testing
Common roles: `platform-owner`, `campaign-owner`, `campaign-director`, `strategy-director`, `research-officer`, `field-coordinator`, `content-editor`, `volunteer`, `polling-station-agent`. Full list of 26 roles in `database/seeders/RolesAndPermissionsSeeder.php`.

### Role Permissions for Opponent Intelligence
- `campaign-owner` / `campaign-director` / `deputy-campaign-director`: All 9 opponent permissions including `opponents.view-confidential`
- `strategy-director`: All except `opponents.delete` and `opponents.delete-research`
- `research-officer`: `opponents.view`, `opponents.create`, `opponents.edit`, `opponents.view-research`, `opponents.add-research`, `opponents.edit-research` (NO `view-confidential`)
- `legal-compliance-officer`: `opponents.view-research` only

### Role Hierarchy Tiers
- Tier 1: `platform-owner`, `platform-support` (not assignable via invite)
- Tier 2: `campaign-owner`, `campaign-director`, `deputy-campaign-director`
- Tier 3: Department heads (`field-director`, `communications-director`, `strategy-director`, etc.)
- Tier 4: Operational (`content-editor`, `field-coordinator`, `data-analyst`, etc.)
- Tier 5: Field/mobile (`polling-station-agent`, `campaign-agent`, `volunteer`, `election-observer`)
- Tier 6: External (`coalition-partner`, `donor`, `auditor`)

## Devin Secrets Needed
No secrets required for local API testing. Future phases may need:
- `AFRICAS_TALKING_API_KEY` — for SMS/MFA integration
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` — for S3 media storage in production
