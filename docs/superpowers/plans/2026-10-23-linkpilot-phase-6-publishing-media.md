# LinkPilot Phase 6 Publishing and Media Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Complete Phase 6 rich bio pages, link-page blocks/forms/files/variants, media safety, scheduled overlays, and consent-gated pixel triggers without expanding workspace collaboration.

**Architecture:** Reuse the existing Laravel/Inertia workspace ownership, content policies, `AnalyticsTracker`, `TrackingConsent`, and React presentation patterns. Add one workspace-owned media lifecycle shared by bio and link-page blocks, then extend public projections with validated block data and time/consent guards. Preserve existing outbound button URLs and analytics references; add block routes only for new block actions.

**Tech Stack:** Laravel, Eloquent, Laravel Filesystem, Symfony Process, Inertia, React/TypeScript, installed shadcn/Radix primitives, PHPUnit, SQLite migration probes.

**Spec:** `PRD.md` §§4, 6, 7.5–7.7, 7.9, 8–9, 10–12; `product/features/bio-pages.json`, `link-pages.json`, `overlays.json`, `tracking-pixels.json`, `admin-files-pages-localization-logs.json` (AOPS-1/AC-1 media foundation contract); roadmap `docs/superpowers/plans/2026-10-22-linkpilot-phases-5-10-parity.md`.

## Global Constraints

- “Every content and integration object belongs to one active workspace; existing `user_id` values remain creator/audit attribution through the ownership migration.”
- “Unsafe outbound schemes, self-redirect loops, inactive custom hosts, and unsafe webhook endpoints are rejected.”
- “Public access checks precede side effects. Inactive, future, expired, or locked links do not redirect or count a click until access succeeds.”
- “Analytics minimizes visitor data. No raw IP addresses, precise locations, browser fingerprints, access codes, or secrets are stored in analytics events.”
- “Enriched tracking is consent-gated. Before a visitor grants tracking consent on a LinkPilot-rendered public surface, LinkPilot emits no analytics event or third-party pixel; direct redirects retain only their aggregate click counter.”
- “No arbitrary customer JavaScript. Tracking pixels are fixed Meta Pixel or Google Analytics renderers driven by validated identifiers. Google Tag Manager and custom snippets are excluded.”
- “Keep the UI native. New React surfaces use the installed base shadcn/Radix primitives and existing Inertia patterns. Use Lucide icons, visible labels, keyboard focus, and responsive layouts.”
- Do not add workspace invitations, collaboration, or new workspace role types. Workspace `owner`/`admin` checks use the existing policy model; platform administrators do not gain workspace-content access.
- Never expose a storage disk/path, lead value, visitor identifier, raw embed URL, or provider script input in public props or logs. Media records also supply the workspace/uploader, opaque path, MIME/size/hash, visibility, scan-state, and soft-deletion metadata required by the later Phase 9 file-administration contract; do not add that admin UI in Phase 6.

## Review Focus

- A forged block/media ID, a block belonging to another page/workspace, or a stale variant cookie must return unavailable without disclosing content, changing counters, or firing a pixel. Pin in Tasks 2, 4, 6, and 7.
- MIME/extension disagreement, SVG/active-content uploads, an unavailable scanner, and a failed scan must leave no stored object or published block. Pin in Tasks 1 and 6; never substitute a fake clean scan.
- Exact UTC schedule boundaries, pinned ordering, disabled content, and deletion/unpublish must agree between editor preview and public output. Pin in Tasks 2, 4, and 8.
- Missing lead consent, forged fields, foreign-workspace response access, platform-admin bypass attempts, and the exact 30-day expiry boundary must not expose or retain values. Pin in Task 5.
- Variant weights, deleted/inactive assignments, and unset/denied tracking consent must not create unstable assignments, visitor tracking, analytics, or third-party requests. Pin in Tasks 7 and 9.

## Implementation Calls

- Preserve the existing public button paths `/bio/{handle}/links/{bioLink}` and `/pages/{slug}/items/{linkPageItem}` because they are the PRD’s current paths and may already be shared. Add `/bio/{handle}/blocks/{bioBlock}` for social-profile actions, `/pages/{slug}/blocks/{linkPageBlock}` for file blocks, and a POST lead-submission path. Update the catalogs to enumerate both contracts; do not add aliases.
- Treat duplicate catalog requirement OVR-7 as the same schedule/delay behavior as OVR-6. Merge it into OVR-6 at P2, preserving the higher priority and one test contract.
- Media defaults: JPEG/PNG/WebP images up to 5 MiB, decoded and re-encoded with GD to remove metadata; PDF downloads up to 25 MiB; reject SVG, archives, executables, macro-capable, and unknown types. Use a configured Laravel disk, random workspace-prefixed paths, inline delivery only for validated images, attachment delivery for PDFs, and a configurable 365-day active-media retention default. Require an explicitly configured media disk in production. Require a real ClamAV executable for PDFs; missing/failed scan fails closed in every environment. Soft-delete media immediately, retain scan/visibility metadata, and hard-purge deleted/expired objects through the daily retention command.
- Lead forms accept only text, email, textarea, and fixed-option select fields; require a separate explicit lead-collection consent and configured privacy URL in production. Encrypt response values, restrict reads/exports to workspace owners/admins, and permanently purge at 30 days.
- Link-page variants are weighted integer percentages totaling 100, with exactly one control. The server assigns a variant once and stores only an opaque encrypted, HttpOnly, SameSite=Lax 30-day functional cookie; no visitor identity is persisted. Variant reporting is based only on consented analytics events.
- Use finite appearance/template/provider allowlists. No customer HTML, CSS, JavaScript, arbitrary iframe source, or remote-page injection.
- Do not stage or commit changes: the worktree contains pre-existing user changes. Record task completion and verification in the SDD ledger.

---

### Task 1: Shared content-media foundation

**Files:**

- Create: `database/migrations/2026_10_23_000000_create_content_media_table.php`
- Create: `app/Models/ContentMedia.php`
- Create: `app/Services/Content/ContentMediaStorage.php`
- Create: `app/Console/Commands/PruneContentMedia.php`
- Modify: `config/services.php`, `routes/console.php`
- Test: `tests/Feature/Content/ContentMediaStorageTest.php`
- Test: `tests/Feature/Content/Phase6MigrationTest.php`

**Interfaces:**

- Produces `ContentMediaStorage::store(UploadedFile $file, Workspace $workspace, User $user, string $purpose): ContentMedia` and `ContentMediaStorage::delete(ContentMedia $media): void`.
- `ContentMedia` stores workspace/uploader, opaque public UUID, disk/path, sanitized original name, detected MIME, byte size, SHA-256, visibility, scan state, expiry, and soft-delete timestamp. Hide disk/path from serialization. Images are decoded/re-encoded before storage; deleted or expired media is not publicly deliverable. No upload route or media-library UI is added in this task.

- [ ] **Step 1: Write failing service and migration tests.** Assert a decoded PNG is stored as a re-encoded image under a random workspace-prefixed path, a JPEG EXIF marker is absent from the stored bytes, the serialized model omits disk/path, invalid image bytes are rejected before storage, and a PDF is rejected with no persisted object when ClamAV is unavailable. Seed legacy bio/link content plus analytics reference IDs in a disposable SQLite migration probe and assert they remain unchanged after the new media migration.
- [ ] **Step 2: Run the focused tests and confirm the missing media service/schema causes the expected failures.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter='ContentMediaStorageTest|Phase6MigrationTest'`
Expected: FAIL on absent service/table before production changes.

- [ ] **Step 3: Implement the media record, explicit production disk selection, size/MIME validation, GD decode/re-encode for accepted images, ClamAV invocation for PDFs, soft deletion, and hard-purge retention command.** Invoke ClamAV without a shell, treat exit 0 as clean and every other outcome as failure, never log file contents, and delete any stored object if its database write fails. Production must not silently fall back to the local disk when no media disk is configured.
- [ ] **Step 4: Rerun focused tests and confirm PNG storage and fail-closed scanner behavior.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter='ContentMediaStorageTest|Phase6MigrationTest'`
Expected: PASS; the persistent migration probe reports SQLite integrity `ok` and no foreign-key violations.

- [ ] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 2: Rich bio content, appearance, scheduling, and public projection

**Files:**

- Create: `database/migrations/2026_10_23_000100_add_rich_bio_content.php`
- Create: `app/Models/BioContentBlock.php`
- Create: `app/Http/Controllers/BioContentBlockController.php`
- Create: `app/Http/Controllers/PublicBioContentBlockController.php`
- Create: `app/Http/Controllers/PublicBioContentMediaController.php`
- Create: `app/Http/Requests/StoreBioContentBlockRequest.php`
- Create: `app/Http/Requests/UpdateBioContentBlockRequest.php`
- Create: `app/Http/Requests/ReorderBioPageItemsRequest.php`
- Create: `app/Services/BioPages/BioPagePresentation.php`
- Create: `tests/Feature/BioPages/BioContentBlockTest.php`
- Modify: `app/Models/BioPage.php`, `app/Models/BioLink.php`, `app/Http/Requests/BioPageRequest.php`, `app/Http/Requests/BioLinkRequest.php`, `app/Http/Controllers/BioPageController.php`, `app/Http/Controllers/BioLinkController.php`, `app/Http/Controllers/PublicBioPageController.php`, `routes/web.php`, `resources/js/components/bio-pages/editor.tsx`, `resources/js/components/bio-pages/live-preview.tsx`, `resources/js/pages/bio-pages/public.tsx`, `resources/js/types/bio-pages.ts`, generated `resources/js/routes/bio-pages` helpers

**Interfaces:**

- `BioContentBlock` types are `text`, `image`, `social`, and `embed`. Image blocks accept only a workspace-owned `ContentMediaStorage` upload and are served through `/bio/{handle}/blocks/{bioBlock}/image`; arbitrary remote image URLs are rejected. Social providers are `instagram`, `x`, `tiktok`, `youtube`, `linkedin`, and `github`; store a validated handle and construct the provider URL server-side. Embed providers are YouTube, SoundCloud, Vimeo, Spotify, Twitch, and TikTok; store provider-specific canonical IDs, never embed URLs. Spotify also accepts only `track`, `album`, `playlist`, `episode`, or `show` as its item type.
- `BioLink` remains the outbound-button model and stable route. Links and content blocks share one cross-type `position` order; reorder accepts `items: [{type: "link"|"block", id}]` and replaces the obsolete link-only order contract. Both have UTC `starts_at` (inclusive), `ends_at` (exclusive), `is_pinned`, and `is_active`; public eligibility uses active state and the UTC window, ordered pinned first then position.
- Appearance keys are `theme: light|dark|soft`, `typography: sans|serif`, `color: blue|violet|green|amber|rose`, and `background: plain|sand|lavender|midnight`; reject unknown keys/values and all arbitrary markup/styles/scripts. A shared `BioPagePresentation` model and renderer serve public output and the editor preview.

- [x] **Step 1: Write failing behavior tests.** Cover text/image/social/embed CRUD, workspace-owned image upload and controlled public serving, mixed link/block order, cross-page mutation denial, exact inclusive-start/exclusive-end UTC boundaries, pinned-first order, rejection of arbitrary markup/remote image and embed URLs, and a public social action returning 404 for draft, inactive, scheduled-out, or foreign blocks.
- [x] **Step 2: Run `BioContentBlockTest` and confirm the new block workflow is missing.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=BioContentBlockTest`
Expected: FAIL because the new block route/behavior is absent.

- [x] **Step 3: Implement the migration, workspace-authorized block CRUD, cross-type reorder, uploaded private image storage/delivery, finite provider/ID validation, schedule filtering, appearance allowlist, and safe public projection.** Only the six PRD embed providers are accepted; render fixed provider URLs in sandboxed, click-to-load frames after the visitor explicitly activates that embed. Do not issue provider requests during server rendering or before that click.
- [x] **Step 4: Add the Content and Appearance editor tabs and responsive preview using the same public presentation renderer; provide keyboard-operable ordering across buttons and blocks and visible labels.**
- [x] **Step 5: Rerun `BioContentBlockTest`, the existing `BioPageManagementTest`, frontend checks, and the complete Laravel suite; read all results.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter='BioContentBlockTest|BioPageManagementTest'`
Expected: all focused bio tests pass; `npm run check` and `npm run types:check` pass after the React contract change.

- [x] **Step 6: Record the task result in the SDD ledger; do not stage or commit.**

### Task 3: Bio-specific insights

**Files:**

- Create: `app/Http/Controllers/BioPageInsightsController.php`
- Create: `tests/Feature/BioPages/BioPageInsightsTest.php`
- Modify: `routes/web.php`, `resources/js/components/bio-pages/editor.tsx`, `resources/js/types/bio-pages.ts`

**Interfaces:**

- `GET /dashboard/biolinks/{bioPage}/insights` returns the selected UTC window, consented bio-page views, outbound-button/social clicks, and zero-filled daily totals. It returns no visitor hashes, raw IPs, or cross-workspace data.

- [x] **Step 1: Write a failing test with two bio pages and one foreign workspace.** Assert exact 7-day UTC totals, zero-filled dates, only the selected page’s events, and 404 for a foreign page.
- [x] **Step 2: Run `BioPageInsightsTest` and confirm the route/response is absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=BioPageInsightsTest`
Expected: FAIL because the insights endpoint is absent.

- [x] **Step 3: Implement the workspace-scoped aggregate and Insights tab; use existing consented `AnalyticsEvent` rows only.**
- [x] **Step 4: Rerun the focused test and full Laravel suite; verify the public/editor UI at desktop and mobile in the Phase 6 browser gate.**
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 4: Link-page templates and rich blocks

**Files:**

- Create: `database/migrations/2026_10_23_000200_create_link_page_blocks.php`
- Create: `app/Models/LinkPageBlock.php`
- Create: `app/Http/Controllers/LinkPageBlockController.php`
- Create: `app/Http/Controllers/PublicLinkPageBlockController.php`
- Create: `app/Http/Requests/StoreLinkPageBlockRequest.php`
- Create: `tests/Feature/LinkPages/LinkPageBlocksTest.php`
- Modify: `app/Models/LinkPage.php`, `app/Models/LinkPageItem.php`, `app/Http/Controllers/LinkPageController.php`, `app/Http/Controllers/LinkPageItemController.php`, `app/Http/Controllers/PublicLinkPageController.php`, `routes/web.php`, `resources/js/pages/link-pages/edit.tsx`, `resources/js/pages/link-pages/public.tsx`, `resources/js/types/link-pages.ts`

**Interfaces:**

- Rich block types are `text`, `image`, `form`, and `file`; existing `LinkPageItem` button routes stay unchanged. Each page and variant presents one union list ordered by the shared position values.
- Templates are exactly `standard`, `profile`, and `campaign`; template changes do not mutate page blocks or item destinations. Custom CSS remains excluded.

- [x] **Step 1: Write failing tests.** Assert the three templates preserve content when switched, text/image blocks render in saved order, inactive/future blocks are omitted, block mutation is scoped to the parent page/workspace, and existing `/pages/{slug}/items/{item}` button delivery still works.
- [x] **Step 2: Run `LinkPageBlocksTest` and confirm the new block workflow is absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=LinkPageBlocksTest`
Expected: FAIL on the absent block route/behavior.

- [x] **Step 3: Implement the block migration/controllers/requests, mixed ordering with existing buttons, template allowlist, and a shared responsive preview/public component.** All outbound destinations still use the existing first-party destination guard; media IDs are never accepted across workspaces.
- [x] **Step 4: Rerun `LinkPageBlocksTest` and `LinkPageTest`, frontend checks, and the full Laravel suite.**
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 5: Lead forms, encrypted responses, access, and retention

**Files:**

- Create: `database/migrations/2026_10_23_000300_create_link_page_leads.php`
- Create: `app/Models/LinkPageLead.php`
- Create: `app/Http/Controllers/PublicLinkPageLeadController.php`
- Create: `app/Http/Controllers/LinkPageLeadController.php`
- Create: `app/Console/Commands/PruneLinkPageLeads.php`
- Create: `tests/Feature/LinkPages/LinkPageLeadTest.php`
- Modify: `routes/web.php`, `routes/console.php`, `app/Models/LinkPageBlock.php`, `app/Http/Requests/StoreLinkPageBlockRequest.php`, link-page editor/public types and components

**Interfaces:**

- Public form submission is `POST /pages/{slug}/blocks/{linkPageBlock}/leads`; it accepts only the saved field schema plus explicit lead consent. Responses are encrypted at rest, workspace-scoped, and never joined into analytics/webhook payloads.
- Owner/admin response listing and deterministic CSV export are under `/dashboard/link-pages/{linkPage}/leads`; workspace members without the existing owner/admin permission receive 403.
- `linkpilot:prune-link-page-leads` permanently deletes rows at `retention_expires_at <= now('UTC')` and is scheduled daily.

- [x] **Step 1: Write failing tests.** Assert no row for absent consent/forged fields, saved values are unreadable in the database column, workspace owner/admin can read/export only their workspace, an ordinary member and platform super-admin are denied, and a value expires at exactly 30 days while a newer one remains.
- [x] **Step 2: Run `LinkPageLeadTest` and confirm the new endpoint/retention behavior is absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=LinkPageLeadTest`
Expected: FAIL because no lead endpoint/model exists.

- [x] **Step 3: Implement schema, fixed form-field validation, independent lead consent, encrypted persistence, policy-checked owner/admin access, formula-safe CSV export, and 30-day pruning.** A missing production privacy URL prevents rendering and submission; no lead values are logged.
- [x] **Step 4: Run the focused test and invoke the prune command against a disposable database to verify only expired records are removed.**
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 6: File blocks and controlled media delivery

**Files:**

- Create: `app/Http/Controllers/ContentMediaController.php`
- Create: `tests/Feature/Content/LinkPageMediaDeliveryTest.php`
- Modify: `app/Http/Controllers/LinkPageBlockController.php`, `app/Http/Requests/StoreLinkPageBlockRequest.php`, `app/Models/ContentMedia.php`, `app/Services/LinkPages/LinkPagePresentation.php`, `routes/web.php`, link-page editor/public components and types
- Remove: `app/Http/Controllers/PublicLinkPageBlockController.php` and the replaced `/pages/{slug}/blocks/{linkPageBlock}/image` endpoint; images and files use `/content-media/{publicId}`. Existing media disk, retention, and ClamAV settings in `config/services.php` are reused.

**Interfaces:**

- Image/file upload is nested under a workspace-owned content block; the client cannot submit disk/path or reuse another workspace’s media ID.
- `GET /content-media/{publicId}` serves only an unexpired asset attached to an eligible published block. Images are inline; downloads use sanitized attachment names, `nosniff`, and no-store caching. Unpublish, deactivate, schedule expiry, deletion, or asset expiry makes the URL unavailable immediately. Task 7 adds variant-cookie matching after the variant schema exists.

- [x] **Step 1: Write failing tests.** Assert a published image is delivered with its detected MIME; a clean-scanned PDF is attachment-only; ineligible/unpublished assets return 404 after block deactivation, schedule expiry, page unpublishing, deletion, asset expiry, or foreign workspace/draft access; clients cannot reuse media IDs; and missing ClamAV leaves no file block, media row, or stored object.
- [x] **Step 2: Run `LinkPageMediaDeliveryTest` and confirm media delivery and upload guards are absent.**

Run: `docker exec --user 0:0 -w /var/www -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-bio-smoke php artisan test --filter=LinkPageMediaDeliveryTest`
Expected: FAIL on the missing media route and file-block validation.

- [x] **Step 3: Implement block-owned media lifecycle, verified MIME/size/extension agreement, real ClamAV scanning for non-images, content-disposition policy, schedule guards, and expiration cleanup.** Missing or failing ClamAV remains fail-closed; Task 7 adds variant eligibility after the variant schema exists.
- [x] **Step 4: Rerun the focused tests.** `LinkPageMediaDeliveryTest|LinkPageBlocksTest`: 11 passed / 176 assertions, 1 skipped because real ClamAV is unavailable. `LINKPILOT_MEDIA_DISK` is unset; no successful PDF scan or production durable-storage result was fabricated.
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 7: Weighted link-page variants and reporting

**Files:**

- Create: `database/migrations/2026_10_23_000400_create_link_page_variants.php`
- Create: `app/Models/LinkPageVariant.php`
- Create: `app/Http/Controllers/LinkPageVariantController.php`
- Create: `app/Http/Controllers/LinkPageInsightsController.php`
- Create: `tests/Feature/LinkPages/LinkPageVariantsTest.php`
- Modify: `app/Models/LinkPage.php`, `app/Models/LinkPageItem.php`, `app/Models/LinkPageBlock.php`, `app/Models/LinkPageLead.php`, `app/Models/AnalyticsEvent.php`, `app/Services/Analytics/AnalyticsTracker.php`, `app/Http/Controllers/LinkPageItemController.php`, `app/Http/Controllers/LinkPageBlockController.php`, `app/Http/Controllers/ContentMediaController.php`, `app/Http/Controllers/PublicLinkPageController.php`, `app/Http/Controllers/PublicLinkPageItemController.php`, `routes/web.php`, link-page editor/public components and types

**Interfaces:**

- Each page receives exactly one control variant. The migration backfills existing `LinkPageItem` and `LinkPageBlock` rows to it without changing IDs, destinations, content, positions, or existing analytics rows; variant-specific content remains scoped to one parent page and variant.
- Owners can edit variant names/weights and assign items/blocks through the existing dashboard routes. `LinkPageVariantController`, item/block controllers, and requests must validate that each variant belongs to the page. Each variant renders one mixed button/block list in saved position order.
- Public GET selects by validated integer weights totaling 100, stores the encrypted variant ID in a 30-day functional cookie, and serves that same variant on later item, form, and media actions. Stale, deleted, inactive, or cross-page assignments safely resolve to control or return 404 without disclosing sibling content.
- `link_page.view`, button-click, and lead-conversion events carry a variant ID only after analytics consent. Lead records retain variant attribution only when analytics consent is granted; lead-collection consent remains independent. Insights aggregate consented views, clicks, and lead conversions without visitor identity or pre-consent reporting.

- [x] **Step 1: Write failing migration and behavior tests.** Assert existing items and blocks backfill to the control variant without changing IDs, positions, or analytics rows; each variant renders and mutates only its own items/blocks; cross-variant item/form/media actions return unavailable; invalid weights or multiple controls are rejected; weighted assignment remains stable through the 30-day cookie; stale/deleted/inactive assignments resolve safely; and unset/denied tracking consent creates no variant analytics or lead attribution.
- [x] **Step 2: Run `LinkPageVariantsTest` and confirm the variant schema/assignment behavior is absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=LinkPageVariantsTest`
Expected: FAIL because variants and assignment behavior are absent.

- [x] **Step 3: Implement the data-preserving control-variant backfill for items and blocks, optional consented lead attribution, weighted assignment, encrypted functional cookie, variant-scoped item/block/form/media guards, variant editor, and consented reporting.** Never persist a stable visitor identifier.
- [x] **Step 4: Run the focused variant test and persistent SQLite migration probe; assert `PRAGMA integrity_check` is `ok`, `foreign_key_check` is empty, and historical item/block IDs, positions, and analytics event IDs are preserved.**
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 8: Scheduled overlays and enforced display delay

**Files:**

- Create: `database/migrations/2026_10_23_000500_add_overlay_schedule_fields.php`
- Create: `tests/Feature/Overlays/OverlaySchedulingTest.php`
- Modify: `app/Models/LinkOverlay.php`, `app/Http/Requests/StoreLinkOverlayRequest.php`, `app/Http/Controllers/LinkOverlayController.php`, `app/Http/Controllers/PublicLinkController.php`, `app/Http/Controllers/PublicLinkOverlayController.php`, `routes/web.php`, overlay editor/public React component and types

- [x] **Step 1: Write failing tests.** Assert UTC start is inclusive, expiry is exclusive, an unavailable overlay falls back to normal link delivery, the CTA cannot continue before the configured delay, and early/expired continuation does not increment clicks or write analytics.
- [x] **Step 2: Run `OverlaySchedulingTest` and confirm schedule/delay behavior is absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=OverlaySchedulingTest`
Expected: FAIL on missing overlay schedule/delay behavior.

- [x] **Step 3: Implement UTC schedule validation, LinkPilot-controlled delay UI, and a server-side session deadline bound to link and overlay IDs. Continue revalidates link availability/access before any side effect.**
- [x] **Step 4: Rerun `OverlaySchedulingTest` and existing `OverlayDeliveryTest`; record completion in the SDD ledger without staging or committing.**

### Task 9: Consent-gated pixel triggers on owned surfaces

**Files:**

- Create: `database/migrations/2026_10_23_000600_create_tracking_pixel_rules.php`
- Create: `tests/Feature/Analytics/TrackingPixelTriggersTest.php`
- Modify: `app/Models/TrackingPixel.php`, `app/Models/BioPage.php`, `app/Models/LinkPage.php`, `app/Models/LinkOverlay.php`, `app/Http/Controllers/TrackingPixelController.php`, public bio/link/overlay controllers, `resources/js/components/analytics/public-tracking-pixels.tsx`, public page/button/form React components, and the corresponding types

**Interfaces:**

- Allowed trigger names are exactly `page_view`, `button_click`, and `lead_submit`; rule targets are only a workspace-owned bio page, link page, or overlay. Existing page/overlay attachments migrate to `page_view` rules.
- Public props contain only provider and validated identifier grouped by trigger. Fixed Meta/GA renderers map the three names to documented provider events. Provider scripts are absent until tracking consent is granted; embed click-to-load consent remains separate.

- [x] **Step 1: Write failing tests.** Assert existing page-view attachments migrate, cross-workspace/inactive pixels are rejected, no pixel/script request occurs before consent, each allowlisted event fires only for its attached resource, and unknown trigger/remote-script inputs are rejected.
- [x] **Step 2: Run `TrackingPixelTriggersTest` and confirm trigger rules/events are absent.**

Run: `docker exec --user 0:0 -w /var/www -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array linkpilot-manual-fixed php artisan test --filter=TrackingPixelTriggersTest`
Expected: FAIL on missing trigger-specific behavior.

- [x] **Step 3: Implement data migration and fixed provider event dispatch.** A page view fires on eligible render, button click fires only on a user action for that page, and lead submit fires only after the server accepted the submission. Keep the existing per-account Meta `trackSingle` behavior and issue no third-party requests for denied/unset consent.
- [x] **Step 4: Rerun the focused test, `AnalyticsTrackingTest`, and full frontend checks/build after React changes.**
- [x] **Step 5: Record the task result in the SDD ledger; do not stage or commit.**

### Task 10: Product records and Phase 6 acceptance gates

**Files:**

- Modify: `PRD.md`, `product/features/bio-pages.json`, `product/features/link-pages.json`, `product/features/overlays.json`, `product/features/tracking-pixels.json`, `product/progress.json`, `.superpowers/sdd/2026-10-22-linkpilot-phases-5-10-parity/progress.md`
- Update applicable operational docs with media-disk, ClamAV, lead-privacy, retention, and scheduler prerequisites.

- [x] **Step 1: Reconcile PRD/catalog route and requirement records.** Add BIO-8/9, PAG-11/12/13, and PIX-6 to the PRD; document legacy button paths beside new block-action paths; merge duplicate OVR-7 into OVR-6 at P2; keep every unverified requirement `in_progress` and each unavailable operator prerequisite explicit.
- [x] **Step 2: Run `ProductCatalogTest` after product-record changes.**

Run: `docker exec --user 0:0 -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array -w /var/www linkpilot-bio-smoke php artisan test --filter=ProductCatalogTest`
Expected: PASS; every PRD requirement resolves to exactly one feature record and all product records validate.

- [x] **Step 3: Run the complete Laravel suite, frontend check/type/build gates, and persistent migration probe once after all Phase 6 fixes.** Expected: zero failures; migration probe preserves representative data and passes SQLite integrity/foreign-key checks.
- [x] **Step 4: Exercise an isolated disposable app in browser as owner, workspace admin, and platform administrator at desktop and 390px.** Cover bio Content/Appearance/Insights, embed click-to-load, link-page templates/forms/file fail-closed state/variants, scheduled overlays, consent states and pixel events; confirm platform administrators cannot access workspace media/lead content; recheck global-search shortcut/Escape focus restoration and mobile-drawer focus containment/restoration. Do not mutate the shared manual app or stop its server.
- [x] **Step 5: Obtain fresh independent regular-user UX, admin/authorization, and PRD-alignment reviews after fixes; reviewers receive no credentials. Resolve actionable findings and repeat the affected review.**
- [x] **Step 6: Update product and SDD evidence only with observed results. Leave production media storage, ClamAV, and production privacy configuration as `blocked_external` unless actually supplied and verified. Ensure the media schema satisfies later AOPS-1 metadata needs without advancing the Phase 9 admin-files requirement. Record Phase 6 completion only if every local gate and all three reviews pass. Do not stage or commit.**
