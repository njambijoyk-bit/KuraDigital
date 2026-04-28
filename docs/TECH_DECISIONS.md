# KuraDigital — Technology Decisions

> Decisions made for the KuraDigital campaign manager platform. This document explains **what** we chose, **why**, and **how the pieces fit together**.

## Platform Overview

KuraDigital is an all-in-one political campaign management platform. It has three surfaces:

| Surface | Users | Technology | Status |
|---------|-------|-----------|--------|
| **Public campaign website** | Voters | Laravel + React (Vite) | Done |
| **Campaign manager dashboard** | Campaign managers, strategists, content editors | React (web) + Tauri (desktop) | Planned |
| **Field agent mobile app** | Field agents, coordinators | React Native | Planned |

All three surfaces talk to the same **Laravel API** backend.

---

## Architecture

```
                    ┌──────────────────┐
                    │   LARAVEL API    │  ← Auth, data, SMS, M-Pesa
                    │   PostgreSQL     │
                    │   Redis          │
                    └────────┬─────────┘
                             │ HTTPS
            ┌────────────────┼────────────────┐
            │                │                │
   ┌────────▼──────┐  ┌─────▼──────┐  ┌──────▼───────┐
   │  TAURI DESKTOP │  │ WEB BROWSER│  │ REACT NATIVE │
   │  (Rust + React)│  │  (React)   │  │  (Mobile)    │
   │  Managers on   │  │ Same React │  │ Field agents │
   │  laptop        │  │ dashboard  │  │ on Android   │
   └────────────────┘  └────────────┘  └──────────────┘
            │                │                │
            └──────── Shared React code ──────┘

   ┌─────────────────────┐
   │  PUBLIC WEBSITE     │  ← Already done
   │  (Laravel + React)  │
   └─────────────────────┘
```

---

## Decision 1: Tauri for Desktop App

### What is Tauri?

Tauri is a framework that wraps a web frontend (our React dashboard) into a native desktop application. It uses the operating system's built-in web engine (WebView2 on Windows, WebKit on macOS/Linux) and a Rust backend — not a bundled Chromium like Electron.

### Why Tauri over Electron?

| Criteria | Tauri | Electron |
|----------|-------|----------|
| **App size** | 5–10 MB | 150–300 MB |
| **RAM usage** | 30–50 MB | 200–500 MB |
| **Backend language** | Rust (memory-safe, no crashes) | Node.js |
| **Security model** | Allowlist permissions — frontend can only access APIs you explicitly expose | Full system access by default |
| **Auto-updater** | Built-in with signature verification | Needs `electron-updater` package |
| **WebView** | Uses OS built-in (no Chromium shipped) | Ships entire Chromium engine |

### Why Tauri over web-only?

A web dashboard in the browser works well for most use cases. Tauri adds value in specific scenarios relevant to campaign management:

- **System tray notifications** — alert the campaign manager when new contact messages arrive, field agents check in, or opponent activity is detected.
- **Local encrypted storage** — cache sensitive data (opponent research, voter segments) for fast access without repeated API calls, encrypted by Tauri's Rust layer.
- **Local file operations** — export campaign reports as PDF, import voter registers from CSV/Excel files on the user's machine, save rally photos locally.
- **Offline resilience** — if the server goes down during a critical campaign period, cached data remains accessible.
- **Installable product** — a desktop app feels like a premium product. Campaign managers double-click an icon instead of remembering a URL.
- **Code signing** — proves the app is authentic and untampered. Critical for political software where trust matters.

### Why Tauri doesn't replace the web version

The same React dashboard code runs in both the browser and Tauri. Campaign managers who prefer the browser can use it — Tauri is an additional distribution channel, not a replacement. This is possible because Tauri wraps the same frontend; there is no separate codebase.

### How Tauri fits into the build order

Tauri is a wrapper around the React dashboard. Building it is a 1–2 day task once the dashboard exists. The priority is:

1. Build the Laravel API and React web dashboard first.
2. Wrap the dashboard in Tauri once it is functional.

---

## Decision 2: React Native for Mobile App

### Why a native mobile app at all?

Field agents operate in conditions that a web browser cannot handle:

| Requirement | Browser/PWA | Native App |
|-------------|-------------|------------|
| **Offline data entry** (no signal in rural Kenya) | Service workers cache pages, but cannot run a local database for structured data sync | SQLite/WatermelonDB provides a full offline relational database that syncs when connectivity returns |
| **Background GPS tracking** (agent location monitoring) | Not supported — browser suspends background tasks | Full background location access with user permission |
| **Camera integration** (photo evidence at rallies, Form 35A/B tallying) | Basic camera access via browser, limited control | Full camera API — resolution, flash, gallery saving, batch uploads |
| **Push notifications** | Partial on Android, unreliable on iOS | Full push notification support via FCM |
| **Biometric lock** (protect sensitive voter data if phone is stolen) | Not available | Fingerprint / face authentication via device APIs |

For campaign managers and strategists who are on laptops with stable connectivity, the web dashboard is sufficient. The mobile app is specifically for field operations.

### Why React Native over Flutter?

| Criteria | React Native | Flutter |
|----------|-------------|---------|
| **Language** | JavaScript/TypeScript — same as the existing React codebase | Dart — entirely new language for the team |
| **Code sharing with web dashboard** | ~60–70% shared business logic (Zustand stores, API calls, validation, utilities) | 0% — separate codebase, rewrite all business logic in Dart |
| **Learning curve** | Minimal — React components, hooks, state management all transfer directly | Significant — Dart syntax, widget tree model, different state management patterns |
| **Ecosystem** | Largest third-party library ecosystem; official or community SDKs for Africa's Talking, M-Pesa, and Kenyan carrier APIs | Growing but smaller ecosystem; fewer Africa-specific packages |
| **Developer availability in Kenya** | React/JavaScript developers are abundant in Kenya | Flutter/Dart developers are growing but still fewer |
| **Offline support** | WatermelonDB (built for React Native offline sync), SQLite, MMKV for key-value storage | Hive, Isar, Drift — excellent, slightly ahead of React Native here |
| **Performance** | ~95% native performance — sufficient for forms, maps, and data display | Near-native ARM compilation — better for animation-heavy UIs |
| **Hot reload** | Fast reload during development | Excellent hot reload (marginally better than RN) |

**Summary:** Flutter has a slight edge in raw performance and offline local DB tooling. React Native wins on code sharing, learning curve, ecosystem, and hiring — which matter more for a startup building a campaign platform in Kenya.

### Why not Kotlin (Android) + Swift (iOS)?

True native development means building two completely separate apps with two different languages, two different developer skill sets, and zero code sharing. For a startup, this doubles cost and development time without providing meaningful benefit over React Native for the KuraDigital use case (forms, maps, lists, data sync — not games or heavy animation).

### Why not PWA (Progressive Web App) only?

PWAs work well for content consumption but fall short for field operations:

- No background GPS — cannot track agent locations while the app is not in the foreground.
- No reliable offline database — service workers cache pages but do not provide a queryable local database for survey responses, voter records, and canvassing data.
- No biometric authentication — cannot protect sensitive political data on a lost or stolen phone.
- Push notifications remain unreliable on iOS Safari.

A PWA can serve as a lightweight option for campaign managers checking stats on their phone, but it cannot replace a native app for field agents.

---

## Decision 3: Laravel API Backend (Extended)

The public campaign website already runs on Laravel 10 with PHP 8.1+. The campaign manager backend extends this same Laravel application with:

- Admin authentication (Laravel Sanctum with SPA token auth)
- Role-based access control (Super Admin, Campaign Manager, Strategist, Field Coordinator, Content Editor)
- API endpoints for the dashboard and mobile app
- Queue-based job processing (SMS broadcasts, email campaigns) via Laravel Queues + Redis
- Real-time WebSocket channels (live dashboards) via Laravel Reverb
- Multi-tenant data isolation (tenant-scoped queries)

Laravel was chosen over Node.js or Python because:

- It is already in the codebase — zero migration cost.
- PHP is the most widely available backend language in Kenya — easiest to hire.
- Hosting is the cheapest (works on shared hosting for early stages, scales to VPS/cloud).
- M-Pesa integration libraries (Daraja API) are most mature in PHP.
- Laravel's built-in tooling (auth, queues, events, broadcasting, validation, ORM) means less code to write from scratch.

---

## Decision 4: PostgreSQL (Planned Migration from MySQL)

The current codebase uses MySQL 8. The plan is to migrate to PostgreSQL 15 because:

- **JSONB columns** — campaign data is semi-structured (survey responses, voter tags, manifesto variants). PostgreSQL's JSONB is queryable and indexable; MySQL's JSON is slower.
- **PostGIS extension** — geographic queries for ward boundaries, polling station locations, and agent position tracking.
- **Row-level security** — enforce tenant isolation at the database level, not just in application code.
- **Full-text search** — search voter records, opponent research notes, and news articles without a separate search engine.
- **Better analytics** — window functions, CTEs, and lateral joins for reporting dashboards.

Laravel's Eloquent ORM supports PostgreSQL with zero code changes. The migration is a database swap, not a code rewrite.

---

## Integrations

| Service | Provider | Purpose |
|---------|----------|---------|
| SMS / WhatsApp / USSD | Africa's Talking | Voter outreach, OTP for MFA, field agent comms |
| M-Pesa payments | Safaricom Daraja API | Subscription billing, donation tracking |
| Push notifications | Firebase Cloud Messaging (FCM) | Mobile app alerts |
| Real-time dashboards | Laravel Reverb (WebSockets) | Live updates in dashboard |
| Email campaigns | Resend | Newsletter, campaign updates |
| CDN / DDoS protection | Cloudflare | Security, performance |
| File storage | S3 or DigitalOcean Spaces | Media uploads, documents, rally photos |
| Search | Meilisearch | Voter record search, content search |

---

## Security Posture

Political campaign data is high-value target material. Security measures:

- **MFA on all admin accounts** — SMS OTP via Africa's Talking.
- **Encrypted data at rest** — AES-256 for sensitive columns (voter phones, opponent research).
- **TLS 1.3** for all API communication.
- **RBAC** — granular role-based permissions; field agents cannot access opponent research.
- **Audit logging** — every write action logged with user, timestamp, IP, and before/after values.
- **Tauri permission allowlist** — desktop app frontend can only call explicitly exposed Rust commands.
- **React Native biometric lock** — fingerprint/face to open the mobile app.
- **Cloudflare WAF + DDoS protection** in front of all endpoints.
- **Daily encrypted backups** stored off-site.
- **Kenya Data Protection Act 2019 compliance** — data protection officer, privacy policy, user consent flows.
- **Penetration testing** before launch, then quarterly.

---

## Build Order

| Phase | What | Depends On |
|-------|------|-----------|
| 1 | Laravel API — admin auth, RBAC, CMS endpoints | — |
| 2 | React web dashboard — campaign manager UI | Phase 1 |
| 3 | React Native mobile app — field agent tools | Phase 1 |
| 4 | Tauri desktop wrapper — wraps Phase 2 dashboard | Phase 2 |
