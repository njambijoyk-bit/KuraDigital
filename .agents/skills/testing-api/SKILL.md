# KuraDigital API Testing

## Overview
KuraDigital is a Laravel 10 campaign manager platform with a React frontend. The API lives under `/api/v1/` and uses Sanctum token auth.

## Local Dev Setup

### Prerequisites
- PHP 8.3+ (from `ppa:ondrej/php`)
- Composer 2.x
- Node.js (for frontend, not needed for API-only testing)
- SQLite (default local DB)

### Setup Steps
```bash
cd /home/ubuntu/repos/KuraDigital
composer install --no-interaction
touch database/database.sqlite

# Ensure .env has:
# DB_CONNECTION=sqlite
# APP_KEY set (check with php artisan key:generate --show)

php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000 &
```

The `migrate:fresh --seed` command runs `RolesAndPermissionsSeeder` which creates 26 roles and 126 permissions via Spatie Laravel-Permission.

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

### Campaign Creation
When creating a campaign, `election_type` must be one of: `presidential`, `gubernatorial`, `senatorial`, `woman_rep`, `parliamentary`, `mca`, `other`. The `level` must be: `national`, `county`, `constituency`, or `ward`.

### Campaign-Scoped Routes
Routes under `/api/v1/campaigns/{campaign}/...` require the authenticated user to be a member of the campaign. Creating a campaign auto-adds the creator as `campaign-owner`.

### Multi-Tenant Testing
To test tenant isolation, register two separate users. User A creates a campaign, User B should get 403 on all campaign-scoped routes until invited.

### Team Invite Flow
1. User A: `POST /campaigns/{id}/invite` with `{email, role, assigned_wards}`
2. Extract `token` from invitation response
3. User B: `POST /invitations/accept` with `{token}`
4. User B can now access campaign-scoped routes

### ABAC / Clearance-Level Testing
Some features (e.g., opponent research) use clearance levels (`public`, `internal`, `confidential`, `restricted`). To test ABAC:
1. Create resources at different clearance levels as `campaign-owner` (has all permissions)
2. Invite a lower-privilege role (e.g., `research-officer`) — they lack `opponents.view-confidential`
3. Verify the lower role cannot see/create confidential/restricted items (expect 403)
4. Verify the lower role CAN see/create public/internal items (expect 200/201)

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

## Response Format Notes

- **Audit logs** use Laravel pagination: top-level keys are `current_page`, `data`, `total`, etc. The actual log entries are in the `data` array, not `audit_logs`.
- **SWOT entries** are returned grouped by type: `{swot: {strengths: [], weaknesses: [], opportunities: [], threats: []}, total: N}`
- **Content endpoints** (manifesto, events, news, etc.) require a site to exist first — create one via `POST /campaigns/{id}/site` before testing content CRUD.

## Known Issues

- **Audit log `campaign_id` for Campaign model**: Fixed — Campaign model has `getAuditCampaignId()` returning `$this->id`. All models with `Auditable` trait should have this method if they don't have a direct `campaign_id` column.
- **Vite manifest error on non-API routes**: The frontend build might not exist locally. This only affects non-API routes. API routes work fine with `Accept: application/json`.
- **MFA in dev**: MFA verification accepts any 6-digit code in non-production environments.
- **Enum sort order in SQLite**: `ORDER BY enum_column DESC` sorts alphabetically, not by logical severity. For example, `threat_level` sorts as `low > high > critical` instead of `critical > high > medium > low`. This might be fixed in the future with a CASE WHEN or numeric column.
- **Frontend field name alignment**: Frontend forms may use different field names than the API expects. When testing content forms (events, site settings, manifesto), verify field names match the controller's validation rules.

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

## Devin Secrets Needed
No secrets required for local API testing. Future phases may need:
- `AFRICAS_TALKING_API_KEY` — for SMS/MFA integration
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` — for S3 media storage in production
