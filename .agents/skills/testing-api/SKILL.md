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

### Campaign-Scoped Routes
Routes under `/api/v1/campaigns/{campaign}/...` require the authenticated user to be a member of the campaign. Creating a campaign auto-adds the creator as `campaign-owner`.

### Multi-Tenant Testing
To test tenant isolation, register two separate users. User A creates a campaign, User B should get 403 on all campaign-scoped routes until invited.

### Team Invite Flow
1. User A: `POST /campaigns/{id}/invite` with `{email, role, assigned_wards}`
2. Extract `token` from invitation response
3. User B: `POST /invitations/accept` with `{token}`
4. User B can now access campaign-scoped routes

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

## Known Issues

- **Audit log `campaign_id` NULL for Campaign model**: The `Auditable` trait resolves `campaign_id` via `$model->campaign_id`, but the Campaign model doesn't have this attribute (it IS the campaign). The Campaign model might need a `getAuditCampaignId()` method returning `$this->id`. Check if this has been fixed before testing audit logs.
- **Vite manifest error on non-API routes**: The frontend build might not exist locally. This only affects non-API routes. API routes work fine with `Accept: application/json`.
- **MFA in dev**: MFA verification accepts any 6-digit code in non-production environments.

## Password Requirements
- Minimum 8 characters
- Must contain uppercase and lowercase letters
- Must contain at least one number
- Must include `password_confirmation` field

## Roles Available for Testing
Common roles: `platform-owner`, `campaign-owner`, `campaign-director`, `field-coordinator`, `content-editor`, `volunteer`, `polling-station-agent`. Full list of 26 roles in `database/seeders/RolesAndPermissionsSeeder.php`.

## Devin Secrets Needed
No secrets required for local API testing. Future phases may need:
- `AFRICAS_TALKING_API_KEY` — for SMS/MFA integration
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` — for S3 media storage in production
