---
name: testing-field-reports
description: Test the Field Reports system end-to-end across all phases (backend CRUD, data processing pipeline, offline PWA). Use when verifying field report UI, API, or processing changes.
---

# Testing Field Reports System

## Overview
The Field Reports system has 3 phases:
- **Phase 1**: Backend CRUD + Admin UI (FieldReportsPage, detail modal, stats)
- **Phase 2**: Offline-first PWA (CaptureReportPage, Service Worker, IndexedDB)
- **Phase 3**: Data processing pipeline (ProcessFieldMedia jobs, Whisper/Vision/FFmpeg)

## Local Dev Setup

```bash
cd /home/ubuntu/repos/KuraDigital

# Database setup
cp .env.example .env
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
sed -i 's|DB_DATABASE=laravel|DB_DATABASE=/home/ubuntu/repos/KuraDigital/database/database.sqlite|' .env
sed -i 's/MAIL_MAILER=smtp/MAIL_MAILER=log/' .env
touch database/database.sqlite

# Queue driver: use 'database' for testing (not 'sync')
# With sync driver, ProcessFieldMedia runs inline and may fail if GD/API keys missing
sed -i 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=database/' .env

composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed
php artisan config:clear
npm install --legacy-peer-deps
npm run build
php artisan serve --host=127.0.0.1 --port=8000 &
```

### Create Test User + Campaign
```bash
php artisan tinker --execute="
\$user = \App\Models\User::create(['name'=>'Field Admin','email'=>'admin@test.com','password'=>bcrypt('Password1'),'clearance_level'=>'top_secret','account_status'=>'active']);
\$user->assignRole('platform-owner');
echo 'TOKEN=' . \$user->createToken('test')->plainTextToken;
"
```

Note: `clearance_level` must be one of: `public`, `internal`, `confidential`, `top_secret` (underscore, not hyphen).

### Create Campaign via API
```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/campaigns \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Test Campaign","candidate_name":"John Doe","election_type":"gubernatorial","level":"county","county":"Nairobi"}'
```

## UI Navigation Paths

- **Field Reports page**: Sidebar → "Field Reports" link → `/admin/campaigns/{id}/field-reports`
  - Permission required: `field.view-reports`
- **Capture Report page**: "Capture Report" button (top-right green) on Field Reports page → `/admin/campaigns/{id}/capture-report`
  - Permission required: `field.create-reports`
- **Detail modal**: Click on any report card in the Field Reports list
  - Shows: title, body, status badge, media gallery, processing results, action buttons
- **Reprocess button**: In detail modal actions bar (purple button with refresh icon)
  - Only visible when report has media attachments

## Key API Endpoints

- `GET /api/v1/campaigns/{id}/field-reports` — List with filters (type, status, search)
- `GET /api/v1/campaigns/{id}/field-reports/stats` — Stats breakdown
- `POST /api/v1/campaigns/{id}/field-reports` — Create (multipart/form-data for files)
- `POST /api/v1/campaigns/{id}/field-reports/{id}/reprocess` — Re-dispatch processing jobs

## Testing Strategies

### Phase 1 (Admin UI)
1. Navigate to Field Reports page → verify stats cards match report count
2. Use type/status filter dropdowns → verify list filters correctly
3. Click report card → verify detail modal shows all fields
4. Create a text report via API → verify it appears in the list

### Phase 2 (Capture Report)
1. Click "Capture Report" → verify 4 type buttons (Photo, Video, Audio, Text)
2. Select Text → verify form renders with Title, Report*, Tags, GPS
3. Fill form and submit → verify "Report uploaded successfully!" screen
4. Go back → verify stats updated with new report

### Phase 3 (Processing Pipeline)
1. Upload a photo report via API → verify job queued in `jobs` table
2. Click Reprocess in detail modal → verify new job added
3. Verify job payload contains `ProcessFieldMedia` class
4. Verify queue name is `field-reports`

```bash
# Check jobs queued
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM jobs WHERE queue='field-reports';"
sqlite3 database/database.sqlite "SELECT payload FROM jobs LIMIT 1;" | grep -o 'ProcessFieldMedia'
```

### PWA Verification
```bash
curl -s http://127.0.0.1:8000/manifest.json | python3 -m json.tool
curl -s http://127.0.0.1:8000/sw.js | head -5
curl -s http://127.0.0.1:8000/admin | grep -E 'manifest|theme-color'
```

## Common Issues

- **GD PHP extension missing**: `php8.3-gd` might not be installed. ThumbnailService constructor throws `DriverException` if GD is absent. Workaround: set `QUEUE_CONNECTION=database` so jobs are queued (not run inline), and uploads succeed.
- **clearance_level check constraint**: Use `top_secret` (underscore), not `top-secret` (hyphen).
- **DB_DATABASE path**: When using SQLite, must be an absolute path (e.g., `/home/ubuntu/repos/KuraDigital/database/database.sqlite`), not just `laravel`.
- **Rate limiting on auth**: If hitting 429 on login/register, use `php artisan cache:clear` or create users via tinker.
- **Location permission**: Capture Report page requests geolocation permission on load. Allow or dismiss to proceed.

## Devin Secrets Needed

No secrets required for basic testing. For full processing pipeline testing:
- `OPENAI_API_KEY` — OpenAI Whisper API for audio/video transcription
- `GOOGLE_VISION_API_KEY` — Google Cloud Vision for OCR and image labels
