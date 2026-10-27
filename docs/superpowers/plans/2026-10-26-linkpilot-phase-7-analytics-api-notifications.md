# LinkPilot Phase 7 Analytics, API, Webhooks, and Notifications Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Complete Phase 7 analytics, workspace-bound API keys and versioned API, webhook redelivery, and account notifications using the current PRD and feature catalogs.

**Architecture:** Extend consented analytics events with local-only GeoIP and bounded attribution dimensions, then use one workspace-scoped filter contract for the dashboard, event table, polling, and CSV export. Add workspace-bound Sanctum tokens and versioned API routes; add immutable webhook-redelivery lineage; use Laravel database notifications with explicit per-user preferences and authorized destination links.

**Tech Stack:** Laravel 13 / PHP 8.3, SQLite-compatible migrations, Laravel queues and database notifications, Sanctum, local MaxMind DB reader, Inertia 3 / React 19, existing shadcn/Radix components, PHPUnit.

**Spec:** `PRD.md` §§7.10, 7.13–7.14 and ID-4; `product/features/analytics.json`, `api-keys.json`, `webhooks.json`, `notifications.json`; roadmap `docs/superpowers/plans/2026-10-22-linkpilot-phases-5-10-parity.md`.

## Global Constraints

- Use the existing active-workspace context and policy checks; never trust a request-supplied workspace identifier to select a tenant.
- Analytics stores no raw IP, full user agent, visitor hash in reports/exports, destination, secret, or request body; country lookup uses only a configured local MaxMind database and reports unavailable when missing.
- Every dashboard filter applies identically to the event table and CSV export; dates use UTC 7/30/90-day windows; live polling pauses while the page is hidden and is ordinary bounded polling, not websocket delivery.
- Custom event names must be explicitly declared for one workspace before ingestion; arbitrary event names and undeclared resources are rejected.
- API credentials are shown once, stored as hashes, permanently bound to one workspace, and constrained by abilities and expiry; preserve current owner/admin policy semantics.
- Webhook redelivery creates immutable lineage and uses the current endpoint revision; retain the existing endpoint validation, exact retry-body/signature, and retry semantics.
- Database notifications remain available without mail; send email only when a mailer and the recipient's matching preference are enabled; notification destinations must still be authorized when opened.
- Preserve deferred workspace collaboration and existing workspace roles. Do not stage or commit this user-owned worktree.

## Review Focus

- A filter or resource ID from another workspace must not leak counts, event rows, or CSV rows; pin in Tasks 2 and 6.
- Missing, unreadable, or corrupt MaxMind data must produce an explicit unavailable country dimension without blocking click delivery or persisting IP; pin in Task 1.
- CSV fields beginning with formula characters must be safe in spreadsheet clients; pin in Task 2.
- Expired, revoked, wrong-workspace, or insufficient-ability API tokens must not access or mutate resources; pin in Tasks 5 and 6.
- A deleted or no-longer-authorized notification target must not disclose its former content; pin in Task 8.

---

### Task 1: Local GeoIP and analytics event dimensions

**Files:**

- Add: `maxmind-db/reader` Composer dependency
- Create: `app/Services/Analytics/GeoIpCountryResolver.php`
- Create: `database/migrations/2026_10_26_000100_add_country_and_utm_to_analytics_events.php`
- Modify: `app/Models/AnalyticsEvent.php`, `app/Services/Analytics/AnalyticsTracker.php`, `config/services.php`, `.env.example`
- Test: `tests/Feature/Analytics/AdvancedAnalyticsTest.php`, `tests/Feature/Content/Phase6MigrationTest.php`

**Interfaces:** `GeoIpCountryResolver::countryCode(string $ip): ?string` reads only the configured local MaxMind database; an absent/unreadable database or unmapped address returns `null`. `AnalyticsEvent` stores nullable ISO country code and bounded allowlisted UTM values; it never stores the source IP.

- [x] **Step 1: Write failing tests for consented UTM capture, consent gating, and unavailable/corrupt local GeoIP data. A positive country lookup requires a real configured local MMDB fixture; record it as externally blocked when none is present.**
- [x] **Step 2: Run the focused tests and confirm the missing columns/resolver fail.**

Run: `docker compose exec -T app php artisan test tests/Feature/Analytics/AdvancedAnalyticsTest.php`
Expected: FAIL on missing country/UTM schema and resolver behavior.

- [x] **Step 3: Implement the local database resolver, migration, config, and consented tracker projection.** Use a supported MaxMind DB reader; do not fetch or download GeoIP data at runtime. Treat invalid/missing database data as unavailable, not as a request failure.
- [x] **Step 4: Rerun the focused tests.**

Run: `docker compose exec -T app php artisan test tests/Feature/Analytics/AdvancedAnalyticsTest.php tests/Feature/Content/Phase6MigrationTest.php`
Expected: PASS; historical events retain UUID/workspace/resource references, new dimensions stay null, and no IP column/value is introduced.

- [x] **Step 5: Record observed local database availability and migration proof in the SDD ledger.**

### Task 2: Shared analytics filters, event records, and CSV export

**Files:**

- Create: `app/Http/Requests/AnalyticsFilterRequest.php`
- Create: `app/Http/Controllers/AnalyticsEventController.php`
- Create: `app/Http/Controllers/AnalyticsExportController.php`
- Create: `resources/js/pages/analytics/events.tsx`
- Modify: `app/Services/Analytics/AnalyticsInsights.php`, `app/Http/Controllers/AccountAnalyticsController.php`, `routes/web.php`, `resources/js/types/analytics.ts`, `resources/js/pages/analytics/index.tsx`
- Test: `tests/Feature/Analytics/AdvancedAnalyticsTest.php`

**Interfaces:** One validated filter contract covers active workspace, date window, resource type/ID, event name, browser/platform, country, and allowlisted UTM values. Dashboard totals, paginated event rows, and `/dashboard/insights/export` use the same filter values and base query. Event rows and exports omit visitor hashes. Export is deterministic and formula-safe.

- [x] **Step 1: Write failing behavior tests.** Assert each filter narrows counts and event rows within the active workspace; a foreign resource ID returns no data; CSV uses the same filter set, stable ordering, and no visitor hash; formula-leading cell values are neutralized; and invalid filters return validation errors.
- [x] **Step 2: Run the focused tests and confirm the event/export routes and filtered projections are absent.**

Run: `docker compose exec -T app php artisan test tests/Feature/Analytics/AdvancedAnalyticsTest.php`
Expected: FAIL on absent event/export routes and filter props.

- [x] **Step 3: Implement the shared validated filter/query path, event table pagination, filtered CSV export, and generated route helpers.** Keep workspace selection in the existing active-workspace context; do not add a client-selectable tenant ID.
- [x] **Step 4: Rerun focused analytics and catalog tests.**

Run: `docker compose exec -T app php artisan test tests/Feature/Analytics/AdvancedAnalyticsTest.php tests/Feature/ProductCatalogTest.php`
Expected: PASS; event rows and export match the filtered dashboard query.

### Task 3: Bounded live polling and analytics UI

**Files:**

- Add shadcn chart composition: `resources/js/components/ui/chart.tsx` and the Recharts dependency
- Modify: `resources/js/components/analytics/analytics-dashboard.tsx`, `resources/js/pages/analytics/index.tsx`, `resources/js/pages/analytics/events.tsx`, `resources/js/types/analytics.ts`
- Test: `tests/Feature/Analytics/AdvancedAnalyticsTest.php` plus browser acceptance.

**Interfaces:** `AnalyticsDashboard` has an account mode for validated filters, events URL, and CSV URL, plus a scoped-link mode that retains the 7/30/90-day selector. Visible-tab polling reloads analytics while preserving the current query, pauses on `visibilitychange`, and clears its interval on unmount. Daily analytics charts use shadcn `ChartContainer`, `ChartTooltip`, and Recharts primitives.

- [x] **Step 1: Before UI edits, confirm the current overview lacks filters, export, event rows, and live polling; record the baseline browser result.**
- [x] **Step 2: Use shadcn `ChartContainer`/`ChartTooltip` with Recharts for daily series; add bounded polling paused on `visibilitychange`, cleared on unmount, and reuse query-preserving filter/export controls on overview and event pages. Do not hand-draw charts.**
- [x] **Step 3: Run frontend formatting/lint, typecheck, and build.**

Run: `docker compose exec -T app npm run check && docker compose exec -T app npm run types:check && docker compose exec -T app npm run build`
Expected: all three commands pass.

- [x] **Step 4: Exercise analytics overview and event table in a disposable browser at desktop and 390px; inspect network requests, focus, URL filter state, and exported content.**
- [x] **Step 5: Record observed browser evidence and any external MaxMind-data blocker.**

### Task 4: Declared custom analytics events

**Files:**

- Create: `database/migrations/2026_10_26_000200_create_analytics_event_definitions.php`
- Create: `app/Models/AnalyticsEventDefinition.php`
- Create: `app/Http/Controllers/AnalyticsEventDefinitionController.php`
- Create: `app/Http/Requests/StoreAnalyticsEventDefinitionRequest.php`
- Create: `tests/Feature/Analytics/CustomAnalyticsEventTest.php`
- Create: `resources/js/pages/analytics/custom-events.tsx`
- Modify: `app/Models/Workspace.php`, `app/Services/Analytics/AnalyticsTracker.php`, `app/Http/Controllers/AccountAnalyticsController.php`, `routes/web.php`, `resources/js/pages/analytics/index.tsx`, `resources/js/types/analytics.ts`

**Interfaces:** Workspace owners/admins can declare and disable custom event names. Ingestion validates the declaration and resource workspace and writes only a normal privacy-minimized `AnalyticsEvent`; the versioned API ingest endpoint in Task 6 consumes this definition. No user-defined code or payload is executed or persisted.

- [x] **Step 1: Write failing declaration tests.** Cover owner/admin creation, member denial, duplicate/invalid names, disable behavior, and isolation between two workspaces.
- [x] **Step 2: Run the focused tests and confirm declaration model/routes are absent.**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app php artisan test tests/Feature/Analytics/CustomAnalyticsEventTest.php`
Expected: FAIL on the missing management routes and definitions; the tracker rejects undeclared custom names.

- [x] **Step 3: Implement workspace-scoped event definitions and management UI.** Keep event values to an allowlisted scalar schema and do not store request payloads.
- [x] **Step 4: Rerun focused declaration tests and frontend checks.**

Run: focused PHP test above; `npm run check && npm run types:check && docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app npm run build`
Expected: focused tests, formatting/lint, TypeScript, and production build pass.

- [x] **Step 5: Exercise owner event creation and disable in a disposable browser at desktop and 390px; confirm no horizontal overflow and the owner-only analytics link is visible.**

### Task 5: Workspace-bound API key lifecycle

**Files:**

- Add: `laravel/sanctum` dependency and its supported token migration/configuration
- Create: `app/Http/Controllers/ApiKeyController.php`, API-key request validation, settings page, and `tests/Feature/Api/ApiKeyTest.php`
- Modify: `app/Models/User.php`, `routes/web.php`, API-key settings navigation and types

**Interfaces:** API keys are Sanctum personal access tokens bound permanently to the active workspace and created with explicit abilities, optional expiry, name, and last-use metadata. The raw token is returned once only; later props never contain it.

- [x] **Step 1: Write failing tests.** Verify token reveal-once, hashed persistence, workspace binding independent of workspace switching, owner/admin policy, expiry, ability intersection, revoke, last-use updates, and denial to members without management permission. The workspace-switch regression failed before its one-time-secret fix.
- [x] **Step 2: Rerun the focused tests.** This task resumed after implementation was already present, so the original “routes/schema absent” baseline failure was not replayed by reverting user-owned work.

Run: `docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app php artisan test tests/Feature/Api/ApiKeyTest.php`
Expected: PASS; 6 tests, 80 assertions.

- [x] **Step 3: Add Sanctum and implement the workspace-bound token lifecycle and settings UI.** Never fall back to an unscoped account token.
- [x] **Step 4: Rerun API-key tests and frontend checks.**

### Task 6: Versioned API, custom-event ingestion, and audit/idempotency contracts

**Files:**

- Create: `routes/api.php`, versioned API controllers/requests/resources, API audit and idempotency migrations/models, OpenAPI generation/configuration, and `tests/Feature/Api/VersionedApiTest.php`
- Modify: `bootstrap/app.php`, `app/Models/AnalyticsEventDefinition.php`, API rate-limit configuration and operational docs
- Add: `dedoc/scramble` Composer dependency for generated OpenAPI output

**Interfaces:** `/api/v1` uses Sanctum authentication and token abilities for links, bio pages, link pages, analytics export, webhooks, and declared custom events. Create endpoints honor idempotency keys; collection endpoints use cursor pagination; errors use stable JSON; audit records include actor/workspace/route/method/status/time only. Generate OpenAPI documentation from the implemented request/response contracts.

- [x] **Step 1: Write failing API contract tests.** Cover versioned route availability, CRUD/read scope declared by the catalogs, ability and expiry denial, cursor boundaries, repeated idempotency keys, stable validation JSON, rate limiting, workspace binding, audit redaction, and declared-only custom-event ingestion.
- [x] **Step 2: Run the focused tests and confirm API routes/contracts are absent.**

Run: `docker compose exec -T app php artisan test tests/Feature/Api/VersionedApiTest.php`
Expected: FAIL on absent versioned routes and API middleware.

- [x] **Step 3: Implement API middleware, resource controllers, stable resources/errors, rate limits, idempotency, redacted audit logging, and generated OpenAPI documentation.** Validate each resource against the workspace permanently bound to its token.
- [x] **Step 4: Rerun API-key and versioned API tests; inspect generated OpenAPI output against route contracts.**

### Task 7: Webhook manual redelivery and endpoint health

**Files:**

- Create: `app/Http/Requests/RedeliverWebhookRequest.php`, `tests/Feature/Webhooks/WebhookRedeliveryTest.php`
- Modify: webhook delivery schema/model, `app/Http/Controllers/WebhookDeliveryController.php`, delivery job/service, `routes/web.php`, `resources/js/pages/webhooks/index.tsx`, webhook types/helpers

**Interfaces:** Eligible failed/cancelled deliveries can be manually redelivered by authorized workspace owners/admins. A new delivery row points to the source delivery, uses the current webhook revision and secret, and leaves the source immutable. Pending work remains cancellable under current webhook lifecycle behavior.

- [x] **Step 1: Write failing tests.** Cover eligible/ineligible states, source-to-redelivery lineage, current endpoint revision, unchanged source record, workspace-role denial, queue dispatch, and endpoint health aggregation.
- [x] **Step 2: Run the focused tests and confirm manual redelivery is absent.**

Run: `docker compose exec -T app php artisan test tests/Feature/Webhooks/WebhookRedeliveryTest.php`
Expected: FAIL on the missing redelivery route/lineage.

- [x] **Step 3: Implement redelivery lineage, authorization, queueing, health summary, and owner/admin UI.** Reuse existing endpoint validation and automatic retry behavior; do not duplicate the delivery transport.
- [x] **Step 4: Rerun webhook redelivery and delivery tests.**

### Task 8: Notification preferences, delivery, and center

**Files:**

- Create: `database/migrations/2026_10_26_000300_create_notification_preferences.php`, notification classes, preference controller, notification-center controller/page, and `tests/Feature/Notifications/NotificationCenterTest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, notification-producing invitation/domain/webhook/usage/billing/admin flows, `routes/web.php`, `resources/js/components`, and notification types

**Interfaces:** Persist database notifications for invitations, domain/webhook failures, usage thresholds, billing events, and admin broadcasts. Users filter unread/all, mark one/all read, and configure per-category email preferences. Email is sent only when the mailer is configured and the matching preference is enabled. Unread count refreshes on navigation or bounded visible-tab polling.

- [x] **Step 1: Write failing tests.** Cover database delivery without mail, preference-gated mail, unread/all filters, mark-one/read-all state, unread count, and notification links that hide or redirect safely when the recipient loses access to the target.
- [x] **Step 2: Run focused tests and confirm notification persistence/routes are absent.**

Run: `docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app php artisan test tests/Feature/Notifications/NotificationCenterTest.php`
Expected: PASS; 9 tests, 135 assertions.

- [x] **Step 3: Implement database notification types, preference persistence, guarded destinations, center routes/UI, and bounded unread-count refresh.**
- [x] **Step 4: Run focused tests and frontend checks; exercise user and workspace-admin notification flows in the browser at desktop and 390px.**

### Task 9: Product records and Phase 7 acceptance gates

**Files:**

- Modify: `PRD.md`, `product/features/analytics.json`, `api-keys.json`, `webhooks.json`, `notifications.json`, `product/progress.json`, and the Phase 5–10 SDD ledger
- Update operational docs for local MaxMind database, API-key use, queue worker, and optional mailer.

**Interfaces:** Consumes the verified contracts from Tasks 1–8 and current feature catalogs; produces phase-specific evidence and conservative product states for every remaining external prerequisite.

- [x] **Step 1: Reconcile PRD requirement mapping and route contracts, including catalogued ANL-8/ANL-9.** Keep external prerequisites explicit and only advance requirements with observed evidence.
- [x] **Step 2: Run focused catalog and complete Laravel suites, migration preservation probes, frontend check/type/build, and static checks required by repository scripts.**
- [x] **Step 3: Exercise actual analytics, API-key, webhook, and notification user/admin workflows in a disposable app/browser; do not use shared persistent data.**
- [x] **Step 4: Obtain fresh regular-user UX, admin UX/authorization, and PRD-alignment reviews; no separate security review is requested for this pass. Resolve actionable non-security findings and retest affected paths.**
- [x] **Step 5: Record exact test/browser/review evidence. Leave local MaxMind data, configured mail transport, and HTTPS receiver as `blocked_external` if unavailable. Do not stage or commit.**
