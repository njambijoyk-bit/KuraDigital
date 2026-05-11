---
name: testing-finance-compliance
description: Test the Finance Compliance, Encryption, and Biometric features end-to-end. Use when verifying compliance dashboard, ABAC enforcement, encrypted fields, or WebAuthn flows.
---

# Testing Finance Compliance + Encryption + Biometric

## Prerequisites

- MySQL 8 running with database `kuradigital`
- PHP 8.1+ with Laravel 10
- Node.js with Vite

## Environment Setup

1. Run migrations: `php artisan migrate --force`
   - If migration fails on `receipt_number TEXT` change, check PR #61 fix — the `receipt_number` column has an existing index that conflicts with TEXT conversion.

2. Seed test data:
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```
   Then create a test user with `finance-director` + `campaign-owner` roles and assign `compliance.*` permissions.

3. **Critical: Campaign membership role must match a Spatie role.** The `campaign_members.role` column value must exist in the `roles` table. If using `campaign-manager`, ensure that role is seeded. Otherwise use `campaign-owner` which is always available. If `/auth/me` crashes with `RoleDoesNotExist`, this is the cause.

4. Start servers:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   npm run dev  # Vite on port 5173
   ```

## Known Issues

- **WebAuthnCredential model table name:** The model class name generates `web_authn_credentials` but the migration creates `webauthn_credentials`. The model needs `protected $table = 'webauthn_credentials'`.
- **Encrypted casts on existing data:** Models (Donation, Expense, MpesaTransaction) have `encrypted` casts on PII fields. Existing plaintext data will throw `DecryptException` on read. New data inserted after casts are applied works fine.
- **NaN% weight in score factors:** The compliance dashboard Overview tab shows "NaN% weight" for score factors — the API response doesn't include weight percentages.
- **Vite esbuild scan error:** Dev mode may show "No matching export in app.jsx" — this is pre-existing and doesn't block testing.

## API Test Endpoints

All require Bearer token auth. Campaign ID = 1 in test data.

| Endpoint | Method | Expected |
|----------|--------|----------|
| `/campaigns/{id}/compliance/dashboard` | GET | 200, compliance score + factors |
| `/campaigns/{id}/compliance/alerts` | GET | 200, paginated alerts |
| `/campaigns/{id}/compliance/settings` | GET | 200, settings object |
| `/campaigns/{id}/compliance/settings` | PUT | 428 biometric challenge (step-up auth) |
| `/campaigns/{id}/compliance/report/iebc` | GET | 200, IEBC report data |
| `/campaigns/{id}/compliance/report/donors` | GET | 428 biometric challenge |
| `/auth/webauthn/credentials` | GET | 200, credentials array |
| `/campaigns/{id}/finance/expenses` | POST | 201, ABAC checks applied |

## UI Test Flow

1. Log in → Select campaign → Finance page
2. Verify 5 tabs visible: Summary, Expenses, Budgets, Donations, **Compliance**
   - If Compliance tab is missing, check browser localStorage `kura_campaigns` — the permissions array may not include `compliance.view`. Call `/auth/me` to refresh.
3. Click Compliance tab → Overview sub-tab shows compliance score, factors, donations by source
4. Alerts sub-tab shows empty state or alert list
5. IEBC Reports sub-tab has date inputs + Generate Report button
6. Settings gear button opens modal with all compliance settings fields
7. Regression: verify Expenses and Donations tabs still render without DecryptException

## Devin Secrets Needed

None — uses local MySQL with root/password and Laravel APP_KEY from .env.
