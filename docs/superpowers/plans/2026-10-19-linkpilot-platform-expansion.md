# LinkPilot Platform Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the remaining LinkPilot SaaS operating surfaces: workspace collaboration, administrator governance, plan/usage controls, verified custom domains, consent-gated public publishing/tracking, richer analytics, CTA overlays, and signed webhooks.

**Architecture:** Expand the existing user-owned publishing model into workspace-owned resources while retaining `user_id` as immutable creator attribution. Use a small `WorkspaceContext` service for active-workspace resolution, Laravel policies for role enforcement, and separate services at external/security boundaries: domain verification, analytics tracking, consent, plan limits, and safe webhook delivery. Preserve existing public short-link behavior and `clicks` data; new public features create consent-gated analytics events without blocking visitor delivery.

**Tech Stack:** Laravel 13 / PHP 8.3, SQLite-compatible migrations, Laravel queues and mail notifications, Inertia 3 / React 19, base shadcn/Radix components, Lucide, existing Docker Compose service.

**Spec:** `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md`; durable feature catalog: `PRD.md`.

> **Superseded for current implementation:** The user deferred all workspace work after Task 1. Do not execute Tasks 2–11 from this plan. The owner-scoped implementation plan is `docs/superpowers/plans/2026-10-19-linkpilot-user-scoped-expansion.md`; this plan remains a future collaboration-slice record.

## Global Constraints

- Keep every existing `links.user_id`, `folders.user_id`, and `bio_pages.user_id`; add/backfill nullable workspace ownership without destructive contraction.
- Use base local shadcn/Radix primitives and existing Inertia page/layout conventions; do not add a UI library or copy reference branding/routes.
- All user-facing resource queries and mutations resolve the active valid workspace; policies distinguish member, admin, owner, and platform administrator.
- P1 roles are owner/admin/member only; workspace deletion and ownership transfer are not implemented.
- The P1 plan catalog is exact: free (25 links, 0 domains/pixels/webhooks, 1 member/page), pro (500/3/10/5/5/25), business unlimited; downgrade preserves existing data and blocks only further creation.
- An active custom domain requires exact TXT proof **and** a successful no-redirect LinkPilot HTTPS challenge; untrusted/unknown hosts return 404.
- Public enriched analytics and third-party pixels require explicit consent, `PUBLIC_PRIVACY_URL`, `ANALYTICS_HASH_KEY`, and `ANALYTICS_HASH_KEY_VERSION` in production. Existing aggregate `clicks` remain available without consent.
- Only Meta Pixel and Google Analytics fixed renderers are supported. Google Tag Manager and arbitrary scripts are rejected.
- Webhooks are HTTPS-only, revalidate public DNS on every delivery, disable redirects, sign exact versioned JSON, retry three total attempts, and never block public delivery.
- Every public request must continue to enforce existing active/schedule/access-code conditions before a redirect or click side effect.
- Route additions require `npm run build` so Wayfinder-generated client routes are current; do not hand-edit `resources/js/routes/**`.
- No external payment provider is added without merchant credentials, product/pricing configuration, and billing-policy approval.

## Review Focus

1. **Existing production data backfill:** Task 2 tests new-user personal-workspace creation and runs the migration against persistent SQLite; manual verification confirms legacy links/folders/bio pages are visible in exactly one personal workspace.
2. **Quota downgrade and invitation reservation:** Task 3 tests that all retained resources count, pending invitations reserve capacity, and a downgrade preserves delivery while rejecting only new metered creation.
3. **Host routing and domain degradation:** Task 6 tests an active custom host, default-host concealment, unknown-host 404, TLS/DNS verification transitions, and a failed recheck that does not silently expose the link on the default host.
4. **Consent and delivery ordering:** Task 7 tests that a direct 302 still writes its compatible click without an enriched event, while consented public pages record one correctly referenced event and telemetry failure does not block delivery.
5. **Webhook DNS rebinding / stale configuration:** Task 10 tests fresh endpoint validation on every attempt, no redirect follow, exact HMAC body signing, revision cancellation, and final delivery state after the defined retry schedule.

---

### Task 1: Commit the durable requirements and expansion design

**Files:**
- Create: `PRD.md`
- Create: `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md`
- Create: `docs/superpowers/plans/2026-10-19-linkpilot-platform-expansion.md`

**Interfaces:**
- Consumes: REA evidence `ev_3166f9b5f54416181d58a8a0ccf45470f64f0c7dc8c7f4032009525692347a0d` and existing LinkPilot P0 behavior.
- Produces: authoritative P1 ownership, privacy, lifecycle, plan, domain, and webhook contracts for all later tasks.

- [ ] **Step 1: Inspect the three documents against the spec header and PRD catalog**

Confirm that every user-requested module—custom domains, overlays, pixels, link pages, richer analytics, billing, workspaces, webhooks, and admin controls—has an explicit P1 or deferred requirement and no contradictory status.

- [ ] **Step 2: Commit the reviewed product documentation**

```bash
git add PRD.md docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md docs/superpowers/plans/2026-10-19-linkpilot-platform-expansion.md
git commit -m "docs: add LinkPilot SaaS product requirements"
```

### Task 2: Add workspace ownership, personal-workspace backfill, and active-resource scoping

**Files:**
- Create: `database/migrations/2026_10_19_000700_create_workspaces_and_entitlements_tables.php`
- Create: `database/migrations/2026_10_19_000800_add_workspace_context_to_existing_resources.php`
- Create: `database/migrations/2026_10_19_000900_backfill_personal_workspaces.php`
- Create: `app/Models/Workspace.php`
- Create: `app/Models/Plan.php`
- Create: `app/Models/WorkspaceSubscription.php`
- Create: `app/Policies/WorkspacePolicy.php`
- Create: `app/Services/Workspaces/CreatesPersonalWorkspace.php`
- Create: `app/Services/Workspaces/WorkspaceContext.php`
- Create: `tests/Feature/Workspaces/WorkspaceScopingTest.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Link.php`
- Modify: `app/Models/Folder.php`
- Modify: `app/Models/BioPage.php`
- Modify: `app/Policies/LinkPolicy.php`
- Modify: `app/Policies/FolderPolicy.php`
- Modify: `app/Policies/BioPagePolicy.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/FolderController.php`
- Modify: `app/Http/Controllers/BioPageController.php`
- Modify: `app/Http/Requests/LinkRequest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `database/factories/LinkFactory.php`
- Modify: `database/factories/FolderFactory.php`
- Modify: `database/factories/BioPageFactory.php`

**Interfaces:**
- Consumes: existing owner-only models/controllers and P0 data tables.
- Produces: `WorkspaceContext::currentFor(User $user): Workspace`, `WorkspaceContext::activate(User $user, Workspace $workspace): Workspace`, `User::belongsToWorkspace(Workspace $workspace): bool`, and `WorkspacePolicy` abilities `manageContent`, `manageIntegrations`, `manageMembers`, and `manageBilling`.
- Produces: active-workspace Inertia shared prop used by Tasks 4–10.

- [ ] **Step 1: Write failing workspace feature tests**

In `WorkspaceScopingTest`, require a newly created user to receive a personal workspace and active free subscription; require resources created through existing `/links`, `/dashboard/folders`, and `/bio-pages` routes to receive that workspace; require a member to see/manage workspace content and a non-member to receive 403; require an invalid selected workspace to be rejected/fall back.

- [ ] **Step 2: Run the focused test and verify the expected missing-schema/service failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Workspaces/WorkspaceScopingTest.php`

Expected: FAIL because workspace tables, context, and scoping do not exist.

- [ ] **Step 3: Implement reversible workspace/entitlement expansion and resource scoping**

Create the three migrations in expand/backfill order. Seed `free`, `pro`, and `business` exactly as specified; create a free active subscription and owner membership for each backfilled user; update legacy link/folder/bio-page rows with the personal workspace. Keep every old `user_id` intact and leave new foreign keys nullable for rollback compatibility.

Implement `CreatesPersonalWorkspace` for new users, `WorkspaceContext` for validating/persisting the active workspace, model relations, factory defaults, and role-aware policies. Change existing resource controllers and `LinkRequest` folder validation to use the context/workspace relation rather than user-only ownership. Share active workspace/membership data through Inertia without exposing unrelated workspaces.

- [ ] **Step 4: Run focused and complete backend verification**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Workspaces/WorkspaceScopingTest.php
docker compose run --rm app php artisan test
```

Expected: PASS. Existing link/folder/bio tests continue to prove non-member isolation.

- [ ] **Step 5: Exercise the persistent migration transition**

Run `docker compose run --rm app php artisan migrate`, then inspect the authenticated link, folder, and bio lists in the running application. Confirm the existing local account has one active personal workspace and preexisting public content still resolves.

- [ ] **Step 6: Commit**

```bash
git add app database tests resources/js
git commit -m "feat: scope publishing resources to workspaces"
```

### Task 3: Enforce plan usage and add the billing/usage surface

**Files:**
- Create: `app/Services/Billing/WorkspaceUsage.php`
- Create: `app/Services/Billing/EnforcesPlanLimits.php`
- Create: `app/Http/Controllers/BillingController.php`
- Create: `app/Http/Requests/SelectFreePlanRequest.php`
- Create: `resources/js/pages/billing/index.tsx`
- Create: `resources/js/types/billing.ts`
- Create: `tests/Feature/Billing/WorkspaceUsageTest.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `README.md`

**Interfaces:**
- Consumes: `WorkspaceContext`, `Plan`, `WorkspaceSubscription`, and workspace-owned relation counts from Task 2.
- Produces: `WorkspaceUsage::for(Workspace $workspace): array`, `EnforcesPlanLimits::assertCanCreate(Workspace $workspace, string $metric): void`, and `/billing` page props `{ subscription, plans, usage }`.
- Later tasks call `assertCanCreate` for domains, pixels, pages, webhooks, links, and pending invitations.

- [ ] **Step 1: Write failing plan/usage tests**

Require free workspaces to reject a twenty-sixth link, pro workspaces to accept their configured capacity, business null limits to remain unrestricted, pending invitations to reserve member capacity, and downgrade below current use to preserve existing records while rejecting a new record of that metric.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Billing/WorkspaceUsageTest.php`

Expected: FAIL because no usage/limit service or billing route exists.

- [ ] **Step 3: Implement usage calculation, lock-aware enforcement, and billing UI**

Count all existing links/domains/pixels/webhooks/link pages regardless of active state; count current members plus unexpired pending invitations for membership capacity. Lock the workspace before checking and creating a metered resource. Render plan price/limits, current use, over-limit state, and only the permitted free-plan self-selection; platform plan changes belong to Task 5. Document that the local subscription is entitlement state, not payment collection.

- [ ] **Step 4: Verify focused behavior, complete tests, and frontend build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Billing/WorkspaceUsageTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app resources/js routes tests README.md
git commit -m "feat: add workspace billing usage controls"
```

### Task 4: Add workspace switching, members, and invitation lifecycle UI

**Files:**
- Create: `app/Models/WorkspaceInvitation.php`
- Create: `app/Http/Controllers/WorkspaceController.php`
- Create: `app/Http/Controllers/WorkspaceMemberController.php`
- Create: `app/Http/Controllers/WorkspaceInvitationController.php`
- Create: `app/Http/Controllers/AcceptWorkspaceInvitationController.php`
- Create: `app/Http/Requests/StoreWorkspaceRequest.php`
- Create: `app/Http/Requests/StoreWorkspaceInvitationRequest.php`
- Create: `app/Http/Requests/UpdateWorkspaceMemberRequest.php`
- Create: `app/Notifications/WorkspaceInvitationNotification.php`
- Create: `resources/js/pages/workspaces/index.tsx`
- Create: `resources/js/components/workspaces/workspace-switcher.tsx`
- Create: `resources/js/components/workspaces/workspace-form-dialog.tsx`
- Create: `resources/js/components/workspaces/invite-member-dialog.tsx`
- Create: `resources/js/types/workspaces.ts`
- Create: `tests/Feature/Workspaces/WorkspaceInvitationTest.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

**Interfaces:**
- Consumes: Task 2 `WorkspaceContext`/policies and Task 3 `EnforcesPlanLimits` member metric.
- Produces: authenticated workspace create/switch/member/invitation routes and unauthenticated invitation-accept endpoint guarded by authenticated matching-email behavior.

- [ ] **Step 1: Write failing invitation lifecycle tests**

Cover owner/admin role boundaries, owner-only admin invitations, active seven-day expiry, same-email replacement/resend, revocation, already-member rejection, member-limit reservation, wrong-email rejection, suspended-user rejection, one-time atomic acceptance, role changes, and last-owner protection.

- [ ] **Step 2: Run focused tests and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Workspaces/WorkspaceInvitationTest.php`

Expected: FAIL because membership/invitation routes and notification do not exist.

- [ ] **Step 3: Implement membership lifecycle and workspace UI**

Persist only the invitation token SHA-256 digest; generate a fresh token on create/resend, send it through the configured mailer, and expose its acceptance URL only in the success response. Use the workspace policy for every member/invitation mutation. Use workspace locks where membership capacity or token acceptance races. Add the sidebar switcher and shadcn dialogs/tables with visible role/status/error feedback.

- [ ] **Step 4: Verify focused lifecycle, full suite, and build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Workspaces/WorkspaceInvitationTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app resources/js routes tests
git commit -m "feat: add workspace members and invitations"
```

### Task 5: Add platform administrator controls and safe provisioning

**Files:**
- Create: `app/Http/Middleware/EnsureActiveAccount.php`
- Create: `app/Http/Middleware/EnsureAdministrator.php`
- Create: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Create: `app/Http/Controllers/Admin/AdminUserController.php`
- Create: `app/Console/Commands/GrantAdministrator.php`
- Create: `resources/js/pages/admin/index.tsx`
- Create: `resources/js/types/admin.ts`
- Create: `tests/Feature/Admin/AdministrationTest.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**
- Consumes: workspace/plan relations from Tasks 2–4.
- Produces: middleware aliases `active-account` and `administrator`, `/admin` metadata dashboard, safe account mutations, and `linkpilot:grant-admin {email}`.

- [ ] **Step 1: Write failing admin feature tests**

Require normal users to receive 403 for `/admin`; require an admin to see metadata/aggregate counts but not content destinations; require suspension to delete sessions/clear remember token and block authenticated routes/invitation acceptance; require self-suspension/self-demotion to fail; require the artisan command to grant the explicit target email.

- [ ] **Step 2: Run focused tests and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Admin/AdministrationTest.php`

Expected: FAIL because flags, middleware, routes, and command are absent.

- [ ] **Step 3: Implement administrator middleware, controllers, UI, and command**

Register aliases in `bootstrap/app.php`, apply active-account protection after `auth`/`verified`, and protect the admin group. Make suspension transactional: set state/time, clear remembered login, delete database sessions. Render only permitted metadata/aggregates. Allow a platform admin to assign plan using the Task 3 service, never allow self privilege/status mutation, and do not grant cross-workspace resource membership.

- [ ] **Step 4: Verify focused admin behavior, full suite, and build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Admin/AdministrationTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app bootstrap resources/js routes tests
git commit -m "feat: add LinkPilot administrator controls"
```

### Task 6: Add verified custom domains and host-aware short-link delivery

**Files:**
- Create: `database/migrations/2026_10_19_001000_create_custom_domains_table.php`
- Create: `app/Models/CustomDomain.php`
- Create: `app/Services/Domains/DnsTxtLookup.php`
- Create: `app/Services/Domains/HttpsDomainChallengeProbe.php`
- Create: `app/Services/Domains/DomainVerificationService.php`
- Create: `app/Http/Controllers/CustomDomainController.php`
- Create: `app/Http/Controllers/CustomDomainVerificationController.php`
- Create: `app/Http/Controllers/CustomDomainChallengeController.php`
- Create: `app/Http/Requests/StoreCustomDomainRequest.php`
- Create: `app/Console/Commands/RevalidateCustomDomains.php`
- Create: `resources/js/pages/custom-domains/index.tsx`
- Create: `resources/js/components/custom-domains/domain-form-dialog.tsx`
- Create: `resources/js/types/custom-domains.ts`
- Create: `tests/Feature/CustomDomains/CustomDomainTest.php`
- Modify: `app/Models/Link.php`
- Modify: `app/Http/Requests/LinkRequest.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `app/Http/Controllers/LinkQrCodeController.php`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `resources/js/components/links/link-form-dialog.tsx`
- Modify: `resources/js/types/links.ts`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `README.md`

**Interfaces:**
- Consumes: workspace policy `manageIntegrations` and Task 3 `domains` limit.
- Produces: `DomainVerificationService::verify(CustomDomain $domain): CustomDomain`, `CustomDomain::isActive(): bool`, a no-auth `/.well-known/linkpilot-domain/{token}` challenge route, and active host-aware `PublicLinkController` lookup.

- [ ] **Step 1: Write failing custom-domain feature tests**

Use injected/fake DNS and HTTPS challenge boundaries to prove exact TXT + TLS challenge activation, pending DNS/TLS/failed transitions, default-host concealment, active custom-host redirect, unknown-host 404, failed recheck pausing delivery, assignment only from the active workspace, canonical QR URL behavior, and deletion warning/disassociation behavior.

- [ ] **Step 2: Run focused tests and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/CustomDomains/CustomDomainTest.php`

Expected: FAIL because custom-domain persistence, verifier, and host routing do not exist.

- [ ] **Step 3: Implement domain lifecycle and host routing**

Add the custom-domain schema/statuses and optional `links.custom_domain_id`. Normalize/validate hostnames against `APP_URL` and configured `LINKPILOT_DEFAULT_HOSTS`. Verify TXT records and a public, no-redirect HTTPS challenge response through explicit boundaries; active only after both pass. Revalidate daily. Use the normalized request host for resolution, no custom-domain port, and 404 unrecognized hosts. Keep an inactive assignment bound rather than silently default-routing it; force explicit warning before deletion clears assignments. Add only active workspace domains to the link form and use active custom URLs for QR payloads.

- [ ] **Step 4: Verify focused behavior, full tests, build, and manual DNS instructions**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/CustomDomains/CustomDomainTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Then visit Custom domains locally, create a pending domain, and verify the displayed TXT/HTTPS instructions match the spec.

- [ ] **Step 5: Commit**

```bash
git add app database resources/js routes tests README.md
git commit -m "feat: add verified custom domains"
```

### Task 7: Add consent-gated analytics events, public bio tracking, and richer insights

**Files:**
- Create: `database/migrations/2026_10_19_001100_create_analytics_events_table.php`
- Create: `app/Models/AnalyticsEvent.php`
- Create: `app/Services/Analytics/AnalyticsTracker.php`
- Create: `app/Services/Analytics/TrackingConsent.php`
- Create: `app/Http/Controllers/TrackingConsentController.php`
- Create: `app/Http/Controllers/WorkspaceAnalyticsController.php`
- Create: `app/Http/Controllers/PublicBioLinkController.php`
- Create: `app/Console/Commands/PruneAnalyticsEvents.php`
- Create: `resources/js/components/analytics/tracking-consent.tsx`
- Create: `resources/js/pages/analytics/index.tsx`
- Create: `resources/js/types/analytics.ts`
- Create: `tests/Feature/Analytics/AnalyticsTrackingTest.php`
- Modify: `app/Http/Controllers/LinkInsightsController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `app/Http/Controllers/PublicBioPageController.php`
- Modify: `resources/js/pages/links/insights.tsx`
- Modify: `resources/js/pages/bio-pages/public.tsx`
- Modify: `resources/js/components/bio-pages/live-preview.tsx`
- Modify: `resources/js/app.tsx`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `.env.example`
- Modify: `README.md`

**Interfaces:**
- Consumes: active workspace context and public content resources.
- Produces: `TrackingConsent::allows(Request $request): bool`, `AnalyticsTracker::record(Request $request, string $event, Workspace $workspace, array $references): ?AnalyticsEvent`, and range-aware workspace/link insight props.
- Task 10 consumes persisted analytics events as webhook event sources.

- [ ] **Step 1: Write failing analytics/consent tests**

Require an unset/denied consent cookie to emit no `analytics_events` and no pixel props while a direct short link still writes exactly one legacy click; require granted consent to record UUID events with the correct link/page/item/bio/overlay reference and no raw-IP column; require UTC 7/30/90 windows, no historical backfill, privacy-safe dimensions, and public bio button redirect/event behavior.

- [ ] **Step 2: Run focused tests and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Analytics/AnalyticsTrackingTest.php`

Expected: FAIL because consent, events, public click routes, and analytics aggregation do not exist.

- [ ] **Step 3: Implement consent, tracker, analytics pages, and public route instrumentation**

Add versioned HMAC analytics records, setting the HMAC from `ANALYTICS_HASH_KEY` only when production prerequisites are configured. Provide a server-written granted/denied 180-day cookie and a shadcn consent control linked to `PUBLIC_PRIVACY_URL`. Keep direct redirects compatible with `clicks`; wrap noncritical analytics/enqueue paths so failures log but do not block public rendering/redirects. Add the UTC-range link/workspace summaries and the bio-button tracking redirect route. Register daily pruning for 90-day retention.

- [ ] **Step 4: Verify focused behavior, full suite, and build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Analytics/AnalyticsTrackingTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app database resources/js routes tests .env.example README.md
git commit -m "feat: add consent-gated analytics events"
```

### Task 8: Add managed tracking pixels and publishable link pages

**Files:**
- Create: `database/migrations/2026_10_19_001200_create_tracking_pixels_and_link_pages_tables.php`
- Create: `app/Models/TrackingPixel.php`
- Create: `app/Models/LinkPage.php`
- Create: `app/Models/LinkPageItem.php`
- Create: `app/Http/Controllers/TrackingPixelController.php`
- Create: `app/Http/Controllers/LinkPageController.php`
- Create: `app/Http/Controllers/LinkPageItemController.php`
- Create: `app/Http/Controllers/PublicLinkPageController.php`
- Create: `app/Http/Controllers/PublicLinkPageItemController.php`
- Create: `app/Http/Requests/StoreTrackingPixelRequest.php`
- Create: `app/Http/Requests/StoreLinkPageRequest.php`
- Create: `app/Http/Requests/StoreLinkPageItemRequest.php`
- Create: `resources/js/components/tracking-pixels/public-tracking-pixels.tsx`
- Create: `resources/js/pages/tracking-pixels/index.tsx`
- Create: `resources/js/pages/link-pages/index.tsx`
- Create: `resources/js/pages/link-pages/edit.tsx`
- Create: `resources/js/pages/link-pages/public.tsx`
- Create: `resources/js/types/tracking-pixels.ts`
- Create: `resources/js/types/link-pages.ts`
- Create: `tests/Feature/TrackingPixels/TrackingPixelTest.php`
- Create: `tests/Feature/LinkPages/LinkPageTest.php`
- Modify: `routes/web.php`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**
- Consumes: `EnforcesPlanLimits` (`pixels`, `link_pages`), `WorkspacePolicy::manageIntegrations`, `AnalyticsTracker`, and `TrackingConsent`.
- Produces: reusable `PublicTrackingPixels` props/component and public `/pages/{slug}` plus item redirect behavior. Task 9 reuses the pixel relation/component for overlays.

- [ ] **Step 1: Write failing pixel and link-page feature tests**

Require owner/admin-only pixel management, Meta/GA ID validation, Google Tag Manager/arbitrary provider rejection, quota enforcement, maximum five active attachments, active-workspace association validation, unpublished 404, public item redirect, consented view/item events, and no pixel data for unconsented public pages.

- [ ] **Step 2: Run focused tests and verify failure**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/TrackingPixels/TrackingPixelTest.php
docker compose run --rm app php artisan test tests/Feature/LinkPages/LinkPageTest.php
```

Expected: FAIL because pixels, pages, pivots, and public controllers are absent.

- [ ] **Step 3: Implement safe pixel records and link pages**

Create workspace-owned resource/pivot models with `nullOnDelete` where appropriate. Render only fixed Meta/GA tag shapes after `TrackingConsent::allows`; do not serialize arbitrary script. Implement link-page CRUD, ordered active items, published public response, item redirects, and tracker calls with exact item/page references. Build dashboard management views from existing shadcn forms/cards/tables and add public-page layout exclusions in `app.tsx`.

- [ ] **Step 4: Verify focused behavior, full suite, and build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/TrackingPixels/TrackingPixelTest.php tests/Feature/LinkPages/LinkPageTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app database resources/js routes tests
git commit -m "feat: add tracking pixels and link pages"
```

### Task 9: Add CTA overlays to controlled short-link delivery

**Files:**
- Create: `database/migrations/2026_10_19_001300_create_link_overlays_table.php`
- Create: `app/Models/LinkOverlay.php`
- Create: `app/Http/Controllers/LinkOverlayController.php`
- Create: `app/Http/Controllers/LinkOverlayPixelController.php`
- Create: `app/Http/Controllers/LinkContinueController.php`
- Create: `app/Http/Requests/StoreLinkOverlayRequest.php`
- Create: `resources/js/pages/overlays/index.tsx`
- Create: `resources/js/pages/overlays/public.tsx`
- Create: `resources/js/types/overlays.ts`
- Create: `tests/Feature/Overlays/LinkOverlayTest.php`
- Modify: `app/Models/Link.php`
- Modify: `app/Http/Requests/LinkRequest.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `resources/js/components/links/link-form-dialog.tsx`
- Modify: `resources/js/types/links.ts`
- Modify: `resources/js/app.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`

**Interfaces:**
- Consumes: active workspace domains/pixels/context, `AnalyticsTracker`, `TrackingConsent`, and existing `Link::isAvailableAt` / access-code behavior.
- Produces: optional `Link::linkOverlay()`, `/overlays` dashboard management, `POST /{slug}/continue`, and a LinkPilot-controlled public interstitial.

- [ ] **Step 1: Write failing overlay delivery tests**

Require owner/admin overlay management, member selection of an active same-workspace overlay only, foreign/inactive overlay rejection, zero click on overlay render, consented `overlay.view`, no click/event before access-code success, and one click plus final redirect/event only on continue. Preserve the existing direct-link redirect path without an overlay.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Overlays/LinkOverlayTest.php`

Expected: FAIL because overlay tables/controllers/continue route are absent.

- [ ] **Step 3: Implement interstitial overlays and associations**

Add the nullable overlay relation to links and reusable tracking-pixel pivot. Reuse Link access availability/code checks in both GET and continue actions. The GET public controller renders the overlay after eligibility; the POST action rechecks eligibility then creates legacy click/event and redirects. Use `PublicTrackingPixels` only after consent. Do not proxy, frame, or inject into the remote destination.

- [ ] **Step 4: Verify focused behavior, full suite, and build**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Overlays/LinkOverlayTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app database resources/js routes tests
git commit -m "feat: add short link CTA overlays"
```

### Task 10: Add signed webhook delivery history and queue worker

**Files:**
- Create: `database/migrations/2026_10_19_001400_create_webhooks_and_deliveries_tables.php`
- Create: `app/Models/Webhook.php`
- Create: `app/Models/WebhookDelivery.php`
- Create: `app/Services/Webhooks/OutboundUrlValidator.php`
- Create: `app/Services/Webhooks/WebhookPayload.php`
- Create: `app/Services/Webhooks/WebhookDispatcher.php`
- Create: `app/Jobs/DeliverWebhook.php`
- Create: `app/Http/Controllers/WebhookController.php`
- Create: `app/Http/Controllers/WebhookDeliveryController.php`
- Create: `app/Http/Requests/StoreWebhookRequest.php`
- Create: `resources/js/pages/webhooks/index.tsx`
- Create: `resources/js/pages/webhooks/show.tsx`
- Create: `resources/js/types/webhooks.ts`
- Create: `tests/Feature/Webhooks/WebhookDeliveryTest.php`
- Modify: `app/Services/Analytics/AnalyticsTracker.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `compose.yaml`
- Modify: `README.md`

**Interfaces:**
- Consumes: consented `AnalyticsEvent` records and `WorkspacePolicy::manageIntegrations`/`EnforcesPlanLimits` (`webhooks`).
- Produces: `WebhookPayload::fromEvent(AnalyticsEvent $event): string`, `WebhookDispatcher::dispatch(AnalyticsEvent $event): void`, and an encrypted queue job that operates by delivery ID.

- [ ] **Step 1: Write failing webhook feature tests**

Use a real persisted delivery-state assertion and HTTP boundary fake only for the remote transport. Require unsafe URL rejection, fresh DNS validation on every attempt, no redirect follow, canonical schema-version/event-ID body, exact HMAC signature, 2xx success state, non-2xx retry/final failure state, response truncation, secret show-once/rotation, and cancellation of pending deliveries after revision changes.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `docker compose run --rm app php artisan test tests/Feature/Webhooks/WebhookDeliveryTest.php`

Expected: FAIL because webhook persistence, dispatcher, and job do not exist.

- [ ] **Step 3: Implement webhook lifecycle and safe delivery job**

Generate 32-byte secrets, encrypt them, and expose raw values only in create/rotate flash data. Build deterministic JSON in the specified key order; sign exact body bytes. At dispatch, snapshot the event body/revision into a delivery after commit. Before each job attempt, reload the webhook, cancel stale/deleted/disabled revisions, resolve every address, block unsafe ranges, pin the validated address while preserving TLS hostname verification, disable redirects, and treat only 2xx as delivery. Configure three total attempts with 60/300 second retry delays and bounded logging. Add a Compose worker service using the existing database queue.

- [ ] **Step 4: Verify focused behavior, full suite, build, and worker smoke path**

Run:

```bash
docker compose run --rm app php artisan test tests/Feature/Webhooks/WebhookDeliveryTest.php
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
docker compose up -d --build
```

Confirm the worker is healthy, create a webhook through the UI, and inspect its delivery history after a consented public interaction against a safe public test endpoint.

- [ ] **Step 5: Commit**

```bash
git add app database resources/js routes tests compose.yaml README.md
git commit -m "feat: add signed webhook deliveries"
```

### Task 11: Run release verification, update operational docs, and prepare manual accounts

**Files:**
- Modify: `README.md`
- Modify: `.env.example`
- Modify: `PRD.md` only if an observed verified behavior changes a status statement.
- Modify: `docs/superpowers/specs/2026-10-19-linkpilot-platform-expansion-design.md` only if an observed verified behavior changes a design decision.

**Interfaces:**
- Consumes: all prior slices and the running Compose service.
- Produces: documented host/DNS/consent/analytics/worker/payment prerequisites, seeded manual user/admin credentials, and end-to-end proof.

- [ ] **Step 1: Update operations documentation**

Document `LINKPILOT_DEFAULT_HOSTS`, domain DNS/TLS challenge behavior, `PUBLIC_PRIVACY_URL`, analytics key/version/retention, queue worker service, webhook signing/retry contract, local plan limits, and payment-provider prerequisite. Do not claim configured merchant processing.

- [ ] **Step 2: Run migration and all automated gates**

Run:

```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: all pass. Record actual test/assertion totals and any non-fatal build warning accurately.

- [ ] **Step 3: Run browser/manual smoke scenarios against the live server**

At `http://localhost:8000`, verify all of: workspace creation/switch/member invite; billing use/limit state; admin-only controls; domain pending instructions; consent deny/grant; link-page publish/click; overlay continue; rich insights; and webhook delivery history. Use only a public safe endpoint for live webhook testing; do not weaken SSRF protections for localhost.

- [ ] **Step 4: Provision manual accounts and keep services running**

Create or reset `user@linkpilot.test` and `admin@linkpilot.test`; grant platform admin only through `php artisan linkpilot:grant-admin admin@linkpilot.test`. Verify both accounts can log in and document actual credentials only in the final handoff, not repository files.

- [ ] **Step 5: Commit documentation/final integration changes**

```bash
git add README.md .env.example PRD.md docs
git commit -m "docs: document LinkPilot platform operations"
```
