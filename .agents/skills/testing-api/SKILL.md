---
name: testing-api
description: Test KuraDigital API endpoints and admin UI. Covers Laravel setup, auth flow, campaign creation, and common gotchas.
---

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

# For SQLite, set absolute path:
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
sed -i 's|DB_DATABASE=laravel|DB_DATABASE=/home/ubuntu/repos/KuraDigital/database/database.sqlite|' .env
touch database/database.sqlite

php artisan key:generate
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

Note: `clearance_level` must be one of: `public`, `internal`, `confidential`, `top_secret` (underscore, not hyphen).

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

### Voter Registration Endpoint
Public voter registration endpoint is `POST /api/v1/sites/{slug}/voters` (no auth required). Creates a voter record with `source=online`, `supporter_status=supporter`.

## Devin Secrets Needed

No secrets required for basic API testing.
