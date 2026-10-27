# LinkPilot User-Scoped Platform Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` task by task. This plan supersedes `2026-10-19-linkpilot-platform-expansion.md` for current implementation because the user deferred the workspace slice.

**Goal:** Deliver user-owned custom domains, account plan/usage controls, administration, consent-gated analytics, managed pixels, link pages, CTA overlays, and signed webhooks while leaving workspaces, memberships, and invitations absent until explicitly requested.

**Architecture:** Preserve existing `user_id` ownership for P0 resources and add new private records with the same owner boundary. Add a compact local account-entitlement model, user-owned integration records, and isolated services at external/security boundaries: domain verification, consent/analytics, plan enforcement, and webhook delivery. Continue to use Laravel 13/PHP 8.3, SQLite-compatible migrations, React 19/Inertia, base shadcn/Radix components, and Compose.

**Spec:** `docs/superpowers/specs/2026-10-19-linkpilot-user-scoped-expansion-design.md`; durable catalog: `PRD.md`; retained future workspace design: `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md`.

## Global constraints

- Do not add workspace tables, columns, routes, policies, membership joins, invitation mail, or collaboration UI.
- Keep existing owner-only link/folder/bio behavior and `user_id` attribution intact; all new resource queries/mutations use the authenticated owner ID.
- Use installed base shadcn/Radix primitives and existing Inertia conventions only. Do not hand-edit Wayfinder route output; run `npm run build` after route additions.
- Exact current plan catalog: free (25 links, 0 domains/pixels/webhooks, 1 link page), pro (500/3/10/5/25), business unlimited. Downgrades retain existing data and block only future creation.
- An active custom domain requires exact TXT proof and a no-redirect LinkPilot HTTPS/TLS challenge. Unknown/untrusted hosts return 404.
- Rich analytics and third-party pixels require explicit consent; production needs `PUBLIC_PRIVACY_URL`, `ANALYTICS_HASH_KEY`, and `ANALYTICS_HASH_KEY_VERSION`. Existing aggregate `clicks` remains available without consent.
- Only fixed Meta Pixel and Google Analytics renderers are allowed. Reject Google Tag Manager and arbitrary/customer scripts.
- Webhooks are HTTPS-only, revalidate public DNS on every delivery, disable redirects, sign exact versioned JSON, retry three total attempts, and never block public delivery.
- Existing availability/schedule/access-code checks precede every redirect/click side effect. Access-code sessions are link-specific, versioned, and rate-limited.
- No external payment provider or payment form without merchant credentials, product configuration, compliance policy, and explicit approval.

## Review focus

1. **Legacy data and user backfill:** Task 2 creates exactly one active free subscription for every current/new user without changing legacy content ownership; build/migration proof uses the persistent SQLite database.
2. **Quota/race behavior:** Task 2 locks the user through count and creation, counts retained inactive records, and proves downgrade behavior does not unpublish public content.
3. **Admin isolation:** Task 3 proves suspension invalidates current sessions without leaking other users’ content/destinations or allowing self-demotion/suspension.
4. **Host degradation:** Task 4 proves DNS/TLS transitions, default-host concealment, unknown-host 404, and failure rechecks do not expose an assigned link at the default host.
5. **Consent/delivery ordering:** Task 5 proves direct 302 clicks remain compatible but no rich event occurs without a consent surface; analytics failure cannot interrupt public delivery.
6. **Webhook rebinding/staleness:** Task 8 proves fresh endpoint validation per attempt, redirect refusal, exact HMAC body signing, revision cancellation, and final retry state.

---

### Task 1: Commit the user-scoped scope correction and executable design

**Files:**

- Modify: `PRD.md`
- Modify: `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md`
- Modify: `docs/superpowers/plans/2026-10-19-linkpilot-platform-expansion.md`
- Create: `docs/superpowers/specs/2026-10-19-linkpilot-user-scoped-expansion-design.md`
- Create: `docs/superpowers/plans/2026-10-19-linkpilot-user-scoped-expansion.md`

**Interfaces:**

- Consumes: the user’s explicit deferral of workspace implementation and REA evidence `ev_3166f9b5f54416181d58a8a0ccf45470f64f0c7dc8c7f4032009525692347a0d`.
- Produces: an authoritative current owner-scoped contract and a retained future collaboration contract.

- [ ] **Step 1: Inspect the catalog and both designs**

Confirm custom domains, overlays, pixels, link pages, richer analytics, billing, webhooks, and admin controls remain active; confirm workspaces/memberships/invitations are explicitly deferred rather than silently dropped.

- [ ] **Step 2: Commit scope-correction documentation**

```bash
git add PRD.md docs/superpowers/specs docs/superpowers/plans
git commit -m "docs: defer workspace collaboration slice"
```

### Task 2: Add user-scoped entitlement, usage enforcement, and billing

**Files:**

- Create: `database/migrations/2026_10_19_000700_create_account_entitlements_tables.php`
- Create: `database/migrations/2026_10_19_000800_backfill_free_user_subscriptions.php`
- Create: `app/Models/Plan.php`
- Create: `app/Models/UserSubscription.php`
- Create: `app/Services/Billing/AccountUsage.php`
- Create: `app/Services/Billing/EnforcesPlanLimits.php`
- Create: `app/Services/Billing/CreatesFreeSubscription.php`
- Create: `app/Http/Controllers/BillingController.php`
- Create: `app/Http/Requests/SelectFreePlanRequest.php`
- Create: `resources/js/pages/billing/index.tsx`
- Create: `resources/js/types/billing.ts`
- Create: `tests/Feature/Billing/AccountUsageTest.php`
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `README.md`

**Interfaces:**

- Produces: `AccountUsage::for(User $user): array`, `EnforcesPlanLimits::create(User $user, string $metric, Closure $create): mixed`, `CreatesFreeSubscription::for(User $user): UserSubscription`, and `/billing` props `{ subscription, plans, usage }`.
- Later tasks call `EnforcesPlanLimits::create()` for domains, pixels, link pages, and webhooks.

- [ ] **Step 1: Write failing account-entitlement tests**

In `AccountUsageTest`, require every newly created user to get one active free subscription; require every legacy user to be backfilled after migration; require free to reject a twenty-sixth link, pro to allow configured capacity, business null limits to be unlimited, and downgrade below current use to retain records while rejecting one new record. Include a concurrent-creation-safe test around the service callback rather than testing a count helper alone.

- [ ] **Step 2: Run the focused test and observe the missing entitlement failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Billing/AccountUsageTest.php`

Expected: FAIL because plans/subscriptions, user relations, billing route, and enforcement service do not exist.

- [ ] **Step 3: Implement account plans, user subscription backfill, and lock-aware creation**

Seed the exact free/pro/business catalog in the expansion migration. Backfill every existing user with exactly one free active/manual subscription; create it for new users through a model event/service. Keep all old content rows untouched. At this task, `AccountUsage` and the billing surface meter links only because no other resource tables exist; each resource-owning later task extends the same service and billing props before enabling its own limit. `EnforcesPlanLimits::create()` must lock the user row, calculate current count inside the same transaction, reject capacity before calling its creator, and return the creator result. Use it for link creation. Render local subscription state, link usage, plan limits, and over-limit state. Only a user’s own free-plan selection is allowed here; administrator assignment is Task 3. State clearly in README/UI that no payment collection exists.

- [ ] **Step 4: Verify focused behavior, full backend suite, and frontend build**

```bash
docker compose run --rm app php artisan test tests/Feature/Billing/AccountUsageTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Exercise the persistent migration transition and commit**

Run `docker compose run --rm app php artisan migrate`, inspect an existing account’s billing page, and confirm existing public links still resolve.

```bash
git add app database resources/js routes tests README.md
git commit -m "feat: add account billing usage controls"
```

### Task 3: Add administrator controls and safe provisioning

**Files:**

- Create: `database/migrations/2026_10_19_000900_add_administration_flags_to_users_table.php`
- Create: `app/Http/Middleware/EnsureActiveAccount.php`
- Create: `app/Http/Middleware/EnsureAdministrator.php`
- Create: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Create: `app/Http/Controllers/Admin/AdminUserController.php`
- Create: `app/Console/Commands/GrantAdministrator.php`
- Create: `resources/js/pages/admin/index.tsx`
- Create: `resources/js/types/admin.ts`
- Create: `tests/Feature/Admin/AdministrationTest.php`
- Modify: `app/Models/User.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `routes/settings.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**

- Consumes: user subscriptions and plan assignment boundary from Task 2.
- Produces: `active-account` and `administrator` middleware aliases, metadata-only `/admin`, account plan assignment through the Task 2 entitlement boundary, and `linkpilot:grant-admin {email}`.

- [ ] **Step 1: Write failing administration tests**

Require normal users to receive 403 for `/admin`; require an admin to view metadata/aggregate counts without destinations or visitor dimensions; require suspension to clear database sessions/remember token and block every authenticated/settings/billing route; require self-suspension/self-demotion failure; require an admin plan assignment to update only the target’s subscription; require the artisan command to grant an explicit account.

- [ ] **Step 2: Run focused tests and observe missing flags/routes**

Run: `docker compose run --rm app php artisan test tests/Feature/Admin/AdministrationTest.php`

Expected: FAIL because admin flags, middleware, controllers, and command are absent.

- [ ] **Step 3: Implement administration without cross-account content access**

Register middleware aliases and apply active-account after auth/verified across application and settings routes. Use a transaction for suspension, set state/timestamp, clear remember token, and delete database sessions. Expose only user metadata, subscription state, and aggregate counts. Prevent platform administrators from changing their own suspension or administrator flag. Use the existing entitlement model for a target account’s plan assignment, never a content relationship. Add the sidebar entry only for admins.

- [ ] **Step 4: Verify and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/Admin/AdministrationTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build

git add app bootstrap database resources/js routes tests
git commit -m "feat: add LinkPilot administrator controls"
```

Expected: PASS.

### Task 4: Add verified user-owned custom domains and host-aware delivery

**Files:**

- Create: `database/migrations/2026_10_19_001000_create_custom_domains_table.php`
- Create: `app/Models/CustomDomain.php`
- Create: `app/Policies/CustomDomainPolicy.php`
- Create: `app/Services/Domains/DnsTxtLookup.php`
- Create: `app/Services/Domains/HttpsDomainChallengeProbe.php`
- Create: `app/Services/Domains/DomainVerificationService.php`
- Create: `app/Console/Commands/VerifyCustomDomains.php`
- Create: `app/Http/Controllers/CustomDomainController.php`
- Create: `app/Http/Controllers/CustomDomainVerificationController.php`
- Create: `app/Http/Controllers/DomainChallengeController.php`
- Create: `app/Http/Requests/StoreCustomDomainRequest.php`
- Create: `resources/js/pages/custom-domains/index.tsx`
- Create: `resources/js/types/custom-domains.ts`
- Create: `tests/Feature/Domains/CustomDomainTest.php`
- Modify: `app/Services/Billing/AccountUsage.php`
- Modify: `app/Http/Controllers/BillingController.php`
- Modify: `resources/js/pages/billing/index.tsx`
- Modify: `app/Models/Link.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `app/Http/Controllers/LinkQrCodeController.php`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**

- Consumes: `EnforcesPlanLimits::create($user, 'domains', ...)` and owner policies.
- Produces: `DomainVerificationService::verify(CustomDomain): CustomDomain`, active host-aware public resolution, the no-auth challenge endpoint, and canonical QR URLs.

- [ ] **Step 1: Write failing domain feature tests**

Use injected fake DNS/TLS boundaries to prove hostname rejection, exact TXT + no-redirect TLS activation, pending DNS/TLS/failed transitions, active owner-only assignment, default-host concealment, active custom-host redirect, unknown-host 404, failed recheck pausing delivery, explicit deletion/disassociation, and active custom URL QR output.

- [ ] **Step 2: Run focused test and observe missing domain behavior**

Run: `docker compose run --rm app php artisan test tests/Feature/Domains/CustomDomainTest.php`

Expected: FAIL because domain persistence, verifier, routes, and host resolution do not exist.

- [ ] **Step 3: Implement bounded verification and host routing**

Normalize/reject unsafe host input. Generate/reissue random tokens, expose exact setup instructions, call only explicit DNS/HTTPS verifier boundaries, and retain bounded errors. Register daily revalidation. Add nullable `links.custom_domain_id`; domain assignment validates active ownership. Extend account usage/billing props to count retained domains before enforcing the domain quota. Resolve request host only from configured default hosts or an active domain, reject ports/unrecognized hosts, and never default-route a still-assigned inactive domain. Require explicit deletion confirmation before clearing assignments. Update the link UI and QR canonical data.

- [ ] **Step 4: Verify and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/Domains/CustomDomainTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build

git add app config database resources/js routes tests .env.example
git commit -m "feat: add verified custom domains"
```

Expected: PASS. Local manual smoke may show only pending setup unless public DNS/TLS/routing exists.

### Task 5: Add consent-gated analytics, account insights, bio tracking, and access-code hardening

**Files:**

- Create: `database/migrations/2026_10_19_001100_add_access_code_version_to_links_table.php`
- Create: `database/migrations/2026_10_19_001200_create_analytics_events_table.php`
- Create: `app/Models/AnalyticsEvent.php`
- Create: `app/Services/Analytics/TrackingConsent.php`
- Create: `app/Services/Analytics/AnalyticsTracker.php`
- Create: `app/Http/Controllers/TrackingConsentController.php`
- Create: `app/Http/Controllers/AccountAnalyticsController.php`
- Create: `app/Http/Controllers/PublicBioLinkController.php`
- Create: `app/Console/Commands/PruneAnalyticsEvents.php`
- Create: `resources/js/components/analytics/tracking-consent.tsx`
- Create: `resources/js/pages/analytics/index.tsx`
- Create: `resources/js/types/analytics.ts`
- Create: `tests/Feature/Analytics/AnalyticsTrackingTest.php`
- Modify: `app/Models/Link.php`
- Modify: `app/Models/BioPage.php`
- Modify: `app/Models/BioLink.php`
- Modify: `app/Http/Controllers/LinkAccessController.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/LinkInsightsController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `app/Http/Controllers/PublicBioPageController.php`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**

- Produces: `TrackingConsent::allows(Request): bool`, `AnalyticsTracker::record(Request, string, User, array): ?AnalyticsEvent`, account/link analytics props, link-specific unlock state, and public tracked bio redirect.
- Task 8 consumes persisted analytics events.

- [ ] **Step 1: Write failing privacy/delivery tests**

Require direct eligible links to create one existing click but no rich event; require a consented bio page view/button click to store only minimal versioned data with exact references; require denied/unset consent to store no rich event; require a tracking exception not to block rendering/redirects; require 7/30/90-day zero-filled account/link results; require unlock version invalidation, eight-hour expiry, and five-failure-per-five-minute link/visitor throttling.

- [ ] **Step 2: Run focused tests and observe missing interfaces**

Run: `docker compose run --rm app php artisan test tests/Feature/Analytics/AnalyticsTrackingTest.php`

Expected: FAIL because consent/event tables, tracker, account insight route, and hardened unlock state are absent.

- [ ] **Step 3: Implement privacy-minimal tracking and access control**

Create immutable event records with user ownership and no raw identity. Keep production tracking/pixels disabled until required configuration exists; use the server-only consent cookie. Wrap tracker/enqueue calls in public paths so telemetry cannot block delivery. Preserve legacy click semantics. Add the public bio link redirect route, account analytics cards/tables, daily pruning schedule, and owner-only authorization. Increment the link access-code version when setting/clearing it; store only versioned link-specific unlock state and throttle failures.

- [ ] **Step 4: Verify and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/Analytics/AnalyticsTrackingTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build

git add app config database resources/js routes tests .env.example
git commit -m "feat: add consented account analytics"
```

Expected: PASS.

### Task 6: Add managed pixels and publishable link pages

**Files:**

- Create: `database/migrations/2026_10_19_001300_create_tracking_pixels_and_link_pages_tables.php`
- Create: `app/Models/TrackingPixel.php`
- Create: `app/Models/LinkPage.php`
- Create: `app/Models/LinkPageItem.php`
- Create: `app/Policies/TrackingPixelPolicy.php`
- Create: `app/Policies/LinkPagePolicy.php`
- Create: `app/Http/Controllers/TrackingPixelController.php`
- Create: `app/Http/Controllers/LinkPageController.php`
- Create: `app/Http/Controllers/LinkPageItemController.php`
- Create: `app/Http/Controllers/PublicLinkPageController.php`
- Create: `app/Http/Controllers/PublicLinkPageItemController.php`
- Create: `app/Http/Requests/StoreTrackingPixelRequest.php`
- Create: `app/Http/Requests/StoreLinkPageRequest.php`
- Create: `app/Http/Requests/StoreLinkPageItemRequest.php`
- Create: `resources/js/components/analytics/public-tracking-pixels.tsx`
- Create: `resources/js/pages/tracking-pixels/index.tsx`
- Create: `resources/js/pages/link-pages/index.tsx`
- Create: `resources/js/pages/link-pages/edit.tsx`
- Create: `resources/js/pages/link-pages/public.tsx`
- Create: `resources/js/types/link-pages.ts`
- Create: `tests/Feature/LinkPages/LinkPageTest.php`
- Modify: `app/Services/Billing/AccountUsage.php`
- Modify: `app/Http/Controllers/BillingController.php`
- Modify: `resources/js/pages/billing/index.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**

- Consumes: account entitlement service plus Task 5 consent/tracker.
- Produces: fixed public pixel props, user-owned page/pixel CRUD, `/pages/{slug}`, and item-click redirect events. Task 7 reuses the pixel component/pivot constraints.

- [ ] **Step 1: Write failing pixels/link-page tests**

Require owner-only pixel/page mutation, Meta/GA identifier validation, provider/GTM/arbitrary-provider rejection, plan enforcement, maximum five active owned pixel attachments, unpublished/unknown 404, public item redirect, exact consented view/item events, and no emitted public pixel data before consent.

- [ ] **Step 2: Run focused test and observe missing surfaces**

Run: `docker compose run --rm app php artisan test tests/Feature/LinkPages/LinkPageTest.php`

Expected: FAIL because pixel/page persistence, routes, and public pages do not exist.

- [ ] **Step 3: Implement only fixed renderers and owner-scoped public pages**

Create user-owned models/pivots with safe cascading. Extend account usage/billing props to count retained pixels and link pages before enforcing either quota. Enforce a maximum of five active pixels and same-owner relation validation. Render only the fixed Meta/GA shapes after `TrackingConsent::allows`; do not serialize arbitrary snippets. Implement ordered item CRUD, public/published responses, and tracker calls with exact item/page references. Register `/pages` routes before the short-link catch-all and exclude public page/redirect paths from the authenticated application layout.

- [ ] **Step 4: Verify and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/LinkPages/LinkPageTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build

git add app database resources/js routes tests
git commit -m "feat: add tracking pixels and link pages"
```

Expected: PASS.

### Task 7: Add CTA overlay interstitial delivery

**Files:**

- Create: `database/migrations/2026_10_19_001400_create_link_overlays_table.php`
- Create: `app/Models/LinkOverlay.php`
- Create: `app/Policies/LinkOverlayPolicy.php`
- Create: `app/Http/Controllers/LinkOverlayController.php`
- Create: `app/Http/Controllers/PublicLinkOverlayController.php`
- Create: `app/Http/Requests/StoreLinkOverlayRequest.php`
- Create: `resources/js/pages/overlays/index.tsx`
- Create: `resources/js/pages/links/overlay.tsx`
- Create: `resources/js/types/overlays.ts`
- Create: `tests/Feature/Overlays/OverlayDeliveryTest.php`
- Modify: `app/Models/Link.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**

- Consumes: same-user domains/pixels, consent/tracker, link availability/access controls, and fixed pixel renderer.
- Produces: one optional owned `Link::linkOverlay()`, `/overlays` management, public overlay render, and `POST /{slug}/continue` delivery.

- [ ] **Step 1: Write failing overlay delivery tests**

Require owner-only overlay management; same-owner active overlay selection only; foreign/inactive rejection; zero click on overlay render; consented `overlay.view`; no render/click before access-code success; exactly one compatible click plus final redirect/event on continue; and unchanged direct-link behavior without an overlay.

- [ ] **Step 2: Run focused test and observe missing overlay behavior**

Run: `docker compose run --rm app php artisan test tests/Feature/Overlays/OverlayDeliveryTest.php`

Expected: FAIL because overlays, selected relation, and continue route do not exist.

- [ ] **Step 3: Implement interstitial, not remote-page injection**

Add nullable `links.link_overlay_id` and pixel pivot tables. Validate ownership/active state in link requests, use LinkPilot-rendered page content, repeat availability/access checks on continue, and reuse consent/pixel/tracker paths. Do not embed, iframe, or inject into the destination. Preserve public no-layout handling and CSRF behavior for the continue form.

- [ ] **Step 4: Verify and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/Overlays/OverlayDeliveryTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build

git add app database resources/js routes tests
git commit -m "feat: add CTA overlay delivery"
```

Expected: PASS.

### Task 8: Add signed webhook delivery and a running queue worker

**Files:**

- Create: `database/migrations/2026_10_19_001500_create_webhooks_and_deliveries_tables.php`
- Create: `app/Models/Webhook.php`
- Create: `app/Models/WebhookDelivery.php`
- Create: `app/Policies/WebhookPolicy.php`
- Create: `app/Services/Webhooks/WebhookEndpointValidator.php`
- Create: `app/Services/Webhooks/WebhookPayload.php`
- Create: `app/Services/Webhooks/WebhookDispatcher.php`
- Create: `app/Jobs/DeliverWebhook.php`
- Create: `app/Http/Controllers/WebhookController.php`
- Create: `app/Http/Controllers/WebhookDeliveryController.php`
- Create: `app/Http/Requests/StoreWebhookRequest.php`
- Create: `resources/js/pages/webhooks/index.tsx`
- Create: `resources/js/types/webhooks.ts`
- Create: `tests/Feature/Webhooks/WebhookDeliveryTest.php`
- Modify: `app/Services/Billing/AccountUsage.php`
- Modify: `app/Http/Controllers/BillingController.php`
- Modify: `resources/js/pages/billing/index.tsx`
- Modify: `app/Services/Analytics/AnalyticsTracker.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `compose.yaml`
- Modify: `.env.example`
- Modify: `README.md`

**Interfaces:**

- Consumes: consented `AnalyticsEvent` records and account plan limits.
- Produces: exact `WebhookPayload::fromEvent(AnalyticsEvent): string`, `WebhookDispatcher::dispatch(AnalyticsEvent): void`, a delivery-ID queue job, and Compose worker service.

- [ ] **Step 1: Write failing webhook feature tests**

Cover HTTPS-only/credential URL rejection, private/loopback/link-local/reserved DNS rejection, ownership, event-name validation, encrypted one-time secret/rotation, exact serialized payload and HMAC, no redirect follow, fresh endpoint validation per attempt, initial/+60/+300 schedule, 2xx success, failure history, and cancellation on update/disable/rotate/delete.

- [ ] **Step 2: Run focused test and observe missing webhook delivery behavior**

Run: `docker compose run --rm app php artisan test tests/Feature/Webhooks/WebhookDeliveryTest.php`

Expected: FAIL because webhook tables, payload, validation, and queue job do not exist.

- [ ] **Step 3: Implement auditable safe delivery**

Persist deliveries from a successfully persisted consented event. Extend account usage/billing props to count retained webhooks before enforcing that quota. Encrypt 32-byte secrets, never expose them after create/rotate, and preserve exact JSON bytes through retries. Resolve/validate every endpoint on creation and each job attempt; bind only a validated public address while retaining TLS hostname checking; disable redirects. Mark stale revision rows cancelled before any outbound request. Use configured retry delays and bounded response excerpts. Add owner-only dashboard/history and a distinct Compose `queue` service for `php artisan queue:work --tries=3`.

- [ ] **Step 4: Verify worker configuration, feature tests, suite, build, and commit**

```bash
docker compose run --rm app php artisan test tests/Feature/Webhooks/WebhookDeliveryTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
docker compose config

git add app compose.yaml database resources/js routes tests .env.example README.md
git commit -m "feat: add signed webhook delivery"
```

Expected: PASS; Compose config includes app and queue services.

### Task 9: Run release verification, document operations, and provision manual accounts

**Files:**

- Modify: `README.md`
- Modify: `.env.example`
- Modify: `PRD.md` only for observed verified status updates.
- Modify: `docs/superpowers/specs/2026-10-19-linkpilot-user-scoped-expansion-design.md` only for observed verified contract changes.

**Interfaces:**

- Consumes: all current owner-scoped modules and the running Compose service.
- Produces: documented local operations, active manual user/admin accounts, and end-to-end evidence.

- [ ] **Step 1: Update operations documentation**

Document that workspaces remain deferred, user-scoped ownership is current, account plan limitations/no payment processing, `LINKPILOT_DEFAULT_HOSTS`, DNS/TLS challenge routing, `PUBLIC_PRIVACY_URL`, analytics key/version/retention, fixed pixel providers, queue worker, and webhook signing/retries.

- [ ] **Step 2: Run migration and automated gates**

```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
docker compose config
```

Expected: PASS. Record actual test/assertion totals and any non-fatal build warning accurately.

- [ ] **Step 3: Run browser/manual smoke scenarios against the live server**

At `http://localhost:8000`, verify billing/limit state, admin-only controls, domain pending instructions, consent deny/grant, link-page publish/click, overlay continue, rich insights, and webhook delivery history. Verify a legacy short link/bio page still works. Use only a public safe endpoint for a live webhook test; never weaken local SSRF guards.

- [ ] **Step 4: Provision accounts and keep services running**

Create/reset `user@linkpilot.test` and `admin@linkpilot.test`; grant platform admin only through `php artisan linkpilot:grant-admin admin@linkpilot.test`. Verify both can log in and report actual credentials only in final handoff, never repository files.

- [ ] **Step 5: Commit final documentation/integration changes**

```bash
git add README.md .env.example PRD.md docs
git commit -m "docs: document LinkPilot platform operations"
```
