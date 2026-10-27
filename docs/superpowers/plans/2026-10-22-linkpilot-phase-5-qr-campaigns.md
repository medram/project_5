# Phase 5B — First-Class QR Campaigns

**Date:** 2026-10-22  
**Roadmap:** `2026-10-22-linkpilot-phases-5-10-parity.md`  
**Dependency:** Phase 5A’s stable short-link delivery guards and workspace-safe target lookup.  
**Scope:** `PRD.md` §7.4; `product/features/qr-codes.json` QR-1–6 and QR-AC-1–3.

## Goal

Replace link-only derived QR records with workspace-owned QR campaigns while preserving existing per-link SVG URLs and canonical-link behavior. Support direct URL and owned-link targets, QR-level controls, scan analytics, allowlisted styles, sharing metadata, logo references, CSV export, and SVG/PNG/JPEG/WebP downloads.

## Current baseline and constraints

- Current QR page enumerates active-workspace links and generates a derived SVG for each link; there is no persisted QR model, public `/q/{code}` route, scan counter, download-format route, or campaign form.
- Existing QR generation uses Endroid QR Code 6.0.9. The installed package provides SVG, PNG, and WebP writers; PNG/WebP require PHP GD. The current PHP image has no GD extension. It has no JPEG writer; JPEG must be encoded from generated raster output with GD.
- Existing link QR URLs use `PublicLinkHostResolver::canonicalUrl()`, respecting the assigned custom domain or application URL.
- QR scan history must not be fabricated from historical short-link clicks. Existing workspace links need one linked-QR campaign each; historical `clicks` remain link clicks only.

## Design decisions

1. Persist `QrCode` records in a new table with immutable stable public code, `workspace_id`, optional `link_id`, guarded direct URL alternative, title/sharing fields, allowlisted styling, active/schedule/access/max-scan controls, private logo path, and timestamps. Enforce exactly one target in request validation and database-safe model logic. A linked campaign references a link in the same workspace.
2. Backfill one non-metered linked QR row for every existing link and create the same derived row with every new link. These rows do not consume the paid QR-campaign allowance because short links already expose one derived QR under QR-1; only user-created direct-destination campaigns consume that allowance. Preserve the existing `/dashboard/links/{link}/qr-code.svg` route and encode the current canonical short-link URL there.
3. Every persisted campaign download encodes the public campaign URL `/q/{code}` so QR-level active/schedule/access/max-scan guards and scan analytics cannot be bypassed. The legacy per-link SVG route alone retains the direct canonical short-link payload.
4. Public `/q/{code}` validates QR controls and target integrity before inserting exactly one anonymous scan row. Direct targets are rechecked against the first-party destination guard; linked rows must still resolve to a same-workspace link. Only final short-link controls remain deferred to the link route. Keep only the QR reference and timestamp; do not persist IP, user agent, or raw visitor identity. Enforce max scans with a conditional scan-count increment and scan insert in one transaction.
5. A valid linked QR records one QR scan and redirects to the linked short link’s canonical public URL. The short-link route performs final link availability/access/approval/archive/click checks. Never create a short-link click in the QR controller. If the link exists in the same workspace but is unavailable, the scan remains a scan attempt while the link creates no click.
6. Direct URL QR records use the existing first-party destination guard and HTTP(S) validation. They never provide an iframe or open redirect to a LinkPilot-owned alias.
7. Use a bounded allowlist of foreground/background colors, correction level, margin/size, and supported logo image types. Upload logos to private workspace-scoped storage; never accept arbitrary file paths from the client. Validate image MIME/size/dimensions; delete replaced/deleted logos safely. Keep QR and link references within the active workspace.
8. Share title/description are explicit campaign metadata, shown in the campaign UI and available on QR-controlled preview/unlock surfaces; they do not change the encoded destination. Format download filenames are deterministic (`qr-{code}.{svg|png|jpg|webp}`), and the format segment is allowlisted.
9. Add PHP GD to the project PHP image and declare `ext-gd` in Composer so raster formats fail during setup rather than at download when unsupported. Generate JPEG by rendering a PNG with the existing Endroid writer and converting the resulting image with GD’s JPEG encoder. Do not introduce another QR or image-processing package.
10. QR insights use QR scan records only. Provide per-campaign and workspace totals/time-series from scan timestamps; do not mix QR scans with link clicks or consent-gated analytics events.

## Implementation tasks

### 1. Add failing behavior coverage first

Test QR list authorization and workspace isolation; a user cannot view, mutate, export, or download a foreign QR campaign.
Test creation of direct URL and same-workspace owned-link campaigns; reject both-target, no-target, invalid URL, first-party target, foreign link, invalid style, and foreign/forged logo references.
Test QR active, schedule, access-code, archive, and max-scan guards before scan insertion. Correct unlock grants access only to that QR and increments no scan until the valid scan request.
Verify the conditional max-scan write at the exact last-scan boundary and that a failed scan-row insert rolls back the counter.
Test linked QR uses the owned link’s current canonical host, records one QR scan, and leaves link-click creation to the public link route; link controls can reject without a link click.
Test backfill creates exactly one non-metered linked QR per existing link, preserves all current link fields, and creates no historical QR scans; linked rows do not consume the direct-campaign plan allowance.
Test persisted campaign formats decode to `/q/{code}` and legacy link SVG decodes to the canonical public link URL; also assert format allowlist, MIME type, deterministic filename, and actual SVG/PNG/JPEG/WebP signatures.
Test logo upload validation/storage lifecycle and that a workspace cannot reference another workspace’s file.
Test CSV export is deterministic, workspace-scoped, and contains no access-code hash/private storage path.

### 2. Schema, model, and backfill

Create a reversible `qr_codes` table with `workspace_id`, nullable `link_id`/`destination_url`, unique stable `code`, `source` (`linked` or `campaign`), `scan_count`, campaign metadata, controls, styles, `logo_path`, and timestamps. Add indexes and a uniqueness rule for one linked QR per link.
Create `qr_scans` rows with `qr_code_id` and timestamp only, indexed for per-campaign and per-workspace time ranges.
Backfill existing links as linked QR rows using collision-safe stable codes. Do not populate `qr_scans` from `clicks`; linked rows are excluded from QR-campaign usage counts.
Add model relations, workspace queries, serialization that never exposes access-code hashes, private-logo cleanup, and conditional scan counting.
Update link creation/update/delete paths to preserve the one-linked-QR invariant without charging the separate campaign allowance or bypassing link plan limits and workspace authorization.

### 3. Public QR delivery and controls

Add authenticated campaign CRUD/list/insight/export routes scoped through `WorkspaceContext::currentFor()`.
Add `/q/{code}` and `/q/{code}/unlock` routes. Apply QR-level guards and validate target integrity before scan side effects; use a QR-scoped hashed access code/version and rate-limited unlock behavior.
For linked QR records, create the scan row once and issue a redirect to `PublicLinkHostResolver::canonicalUrl($link)`. The owned short-link route remains authoritative for final link availability/access/approval/archive/click controls and click/event recording.
For direct targets, re-run the shared first-party destination guard before scan insertion and redirect only after QR-level guards pass.
Ensure unknown, broken-target, or unavailable QR codes fail closed and do not increment scan counts.

### 4. QR generation and deployment image

Add a single QR rendering service that receives a campaign and explicit allowlisted format; it encodes `url('/q/'.$code)` for every persisted campaign and never uses arbitrary request input. The legacy per-link SVG route separately encodes the canonical link URL.
Retain SVG output and add PNG/WebP writers. Add JPEG encoding from PNG via GD with explicit quality and correct content type.
Update `docker/php/Dockerfile` to install/enable GD with required PNG/JPEG/WebP libraries and add `ext-gd` to `composer.json`; rebuild the isolated app image and verify `gd_info()` reports supported output formats.
Return deterministic filenames and correct `Content-Type`/`Content-Disposition`; reject unsupported format strings.
Keep logo handling format-safe and verify render output for each allowed style. SVG logo embedding, if unsupported by the installed writer, must be explicitly rejected rather than silently omitted.

### 5. Campaign UI, insights, and sharing

- Replace the derived-link-only QR page with current-workspace campaign rows/cards, create/edit form, QR preview, active state, scan totals, and explicit SVG/PNG/JPEG/WebP download actions.
- Provide direct URL versus owned-link target selection, title, sharing title/description, allowed styling controls, private logo upload, access/schedule/max-scan controls, and clear validation feedback.
- Add a non-sensitive QR unlock page and preserve existing link QR downloads.
- Add deterministic CSV export and QR-specific insights derived only from `qr_scans`.
- Retain responsive layouts, keyboard access, visible errors, and list state on mutation.

### 6. PRD, catalog, operations, and verification

- Add QR-5/QR-6 rows to PRD §7.4 and align all QR acceptance criteria/statuses with `qr-codes.json`. Preserve existing IDs and do not mark requirements verified before evidence.
  Update `qr-codes.json`, `product/progress.json`, and operational docs with the migration/backfill, GD build requirement, private local-disk proof, durable production-storage prerequisite, and scan privacy. The local private disk must pass the complete QR workflow; if production durability is not configured, record that exact operator-owned prerequisite and set only the affected feature state to `blocked_external` with completed local checks retained.
- Run focused QR tests, the full Laravel suite, `ProductCatalogTest`, frontend checks/build, and migration/backfill checks against a disposable persistent SQLite database.
- Browser-smoke QR creation, editing, target controls, access unlock, scans/insights, export, and four download formats at desktop/mobile. Decode generated outputs to verify payloads. Use isolated app/data only; do not mutate shared port 8000.
- Obtain independent user-UX, admin-UX/authorization, and PRD-alignment reviews after implementation. Resolve and re-review all actionable findings before Phase 5 closes.

## Completion evidence

QR-1–6 and QR-AC-1–3 remain in progress until all delivery, scan/control, ownership, format, migration, UX, and review evidence passes. A pre-existing link click is never represented as a QR scan, and no format is advertised unless its actual MIME/payload smoke check passes.
