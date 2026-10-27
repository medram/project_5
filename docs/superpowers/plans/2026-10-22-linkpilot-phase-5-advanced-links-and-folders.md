# Phase 5A — Advanced Short Links and Folders

**Date:** 2026-10-22  
**Roadmap:** `2026-10-22-linkpilot-phases-5-10-parity.md`  
**Scope:** `PRD.md` §§7.2–7.3; `product/features/short-links.json` LNK-1–14 and LNK-AC-1–8; `product/features/folders.json` FOL-1–4 and FOL-AC-1–3.

## Goal

Complete the approved short-link and folder contract without changing existing workspace membership/role scope. Preserve existing link behavior, active-workspace authorization, consent-gated rich analytics, and the shared user/admin shells.

## Current baseline and known constraints

- Core link CRUD, folders, tags, access codes, schedules, custom domains, overlays, first-party destination checks, consented analytics, quotas, and delete operations exist.
- Public direct delivery and overlay continuation use different controller paths. Both must enforce the same final-click rules and side-effect ordering.
- No trusted proxy/country source is configured. Country targeting must fail safely to the default destination unless the request comes from an explicitly configured trusted country proxy.
- `PRD.md` §7.2 is missing LNK-13/14 and has account-oriented ownership language; §7.3 is missing FOL-4. Feature JSON is the more complete current contract.
- Folder rollups currently expose link/click counts only. Folder/link workspace consistency is not enforced by a database constraint; counts must scope the joined links to the folder’s workspace.
- Keep `LinkPilot` surfaces local. Never embed a remote destination in an iframe.

## Design decisions

1. Keep direct, splash, published-link-page, and LinkPilot-controlled overlay delivery as allowlisted modes. A published link-page visit is final short-link delivery: record one aggregate click and then redirect to the selected published page. Splash and overlay views create no final click; Continue performs exactly one guarded click. Preserve the established direct fallback when an assigned overlay is inactive; a missing, unpublished, or foreign link-page target fails closed.
2. Use a materialized `click_count` updated by one conditional SQL write (`click_count < max_clicks`) inside the same transaction as the click-row insert. Backfill it from existing click rows. This atomic predicate is the quota boundary under concurrent requests; do not rely only on a preflight count or database-specific row locking.
3. Persist edit snapshots in workspace-owned revision rows. Each snapshot includes every delivery-affecting scalar and ordered child collection, plus its `base_version`; never store raw access codes. Approved live fields remain unchanged while a proposal is pending. Approval atomically applies that exact snapshot only when its base version is current. Snapshot/actor data is immutable; review state changes are explicit and stale proposals cannot be applied.
4. Persist targeting rules with an explicit global order. First matching country/device/platform rule wins; no match uses the link’s default mode/target. Read country only from an allowlisted header when the socket peer (`REMOTE_ADDR`), not a forwarded client IP, matches an explicit trusted proxy/CIDR list; otherwise treat country as unknown and continue safely.
5. Merge UTM/custom query parameters without dropping the original query or fragment. Preserve unrelated repeated values and replace every occurrence of configured keys. Apply non-empty UTM values before non-empty custom values; the last non-empty configured value for a key wins. Keep a deterministic key/value validation contract and cap the number/size of custom parameters.
6. CSV export is current-workspace and filter scoped, deterministic, and excludes secrets/hashes. CSV import processes rows independently, applies the same validation, workspace-reference checks, and plan limits as the UI, and returns bounded row-index/status/error outcomes without echoing sensitive input. Bulk actions are workspace-scoped and capped.
7. Folder link/click/event rollups use only links whose `workspace_id` matches the folder’s `workspace_id`, including historical inconsistent rows.

## Implementation tasks

### 1. Add failing behavior coverage first

Extend public-link tests for access, schedule, pending/unpublished state, archive, and maximum-click boundaries: rejected deliveries create zero new click/event side effects; the last permitted click succeeds; later attempts fail.
Verify the atomic quota predicate with an exact last-click boundary test and a deterministic conditional-write race test; do not let the phase close on sequential preflight-count coverage alone.
Cover direct, splash, link-page, and overlay modes with exact response type, aggregate click count, consented `link.clicked` count, and no final-click side effects on intermediate views. Verify existing consented `overlay.view` analytics remains intact. Missing/unpublished/foreign selected pages fail closed; no remote iframe is introduced.
Cover ordered targeting precedence for country/device/platform and default fallback. Spoofed country headers and forwarded-address headers from untrusted socket peers must not match; only configured socket peers in and at CIDR boundaries may supply valid normalized country values.
Cover UTM/custom query merge with an existing query, repeated keys, empty query, URL fragment, and colliding configured keys.
Cover pending revision isolation, exact snapshot application, actor/child collection immutability, stale-base rejection after a later approved edit, duplicate review actions, and approve/reject authorization. A pending proposal never replaces the previous approved delivery.
Cover CSV import row-level valid/invalid results, quota exhaustion, foreign folder/tag/page/overlay references, and current-workspace export with no access-code hash.
Cover bulk archive/restore/activate/deactivate/delete boundaries and cross-workspace IDs.
Extend folder tests to assert event rollups and that deliberately mismatched foreign-workspace links cannot contaminate folder counts.

### 2. Schema and domain contracts

Add a reversible migration for delivery mode/page target, archive state, max clicks, approval configuration, query parameters, materialized `click_count`, and link `revision_version`. Backfill `click_count` from existing clicks and explicitly set every existing link to approved before public delivery starts enforcing approval state. Preserve existing link/overlay data and backfill current overlay behavior to its explicit mode.
Add `link_routing_rules` and revision rows with immutable full delivery snapshots, ordered child rules/tags, actor, reviewer state, `base_version`, and timestamps. Keep pending proposals separate from live published fields.
Add model relations/casts/fillable fields and validation for these structures. Preserve existing slug, host, first-party destination, schedule, access-code hash/version, and workspace invariants.
Add indexes/constraints for workspace/link/order/status lookups. Do not store raw visitor identity or access-code plaintext.

### 3. Centralize public delivery and rule resolution

Introduce one final-delivery service used by the public short-link action and overlay/splash Continue. Keep intermediate views free of final-click side effects while preserving the existing consented `overlay.view` event; splash views do not create a click.
Apply guards in a documented order: host/link resolution; approved live state; active/archive/schedule; access-code unlock; target validation; conditional maximum-click increment; click-row insert; commit; best-effort consent-gated rich event; response. Any click-row failure rolls back the increment and emits no event/webhook.
Record a published link-page visit as exactly one final short-link delivery before the 302 to the selected local published page. Direct delivery and Continue return exactly one final click, with no duplicate count.
Preserve analytics failure resilience: telemetry failure after commit must not suppress a valid redirect or create duplicate aggregate clicks.
Implement parameter merging on the resolved URL without dropping the original query or fragment; replace existing occurrences of configured keys, ignore empty configured rows, and apply non-empty UTM then custom values so the last non-empty value wins. The preview and final delivery remain identical even when a later duplicate custom row is empty.
Add trusted-country-proxy configuration and docs. Default to no trusted country headers; validate trust against the socket peer’s `REMOTE_ADDR`; missing/invalid country skips country rules. Device/platform routing can still operate from the request user agent.
Keep every intermediate response rendered by LinkPilot or redirected to an existing LinkPilot page. No remote frame delivery.

### 4. Complete link management workflows

- Expand `LinkRequest`/link mutation flow for delivery modes, page/overlay associations, targeting rules, UTM/custom parameters, maximum clicks, approval settings, and archive state.
- Add revision-history and review actions scoped to the active workspace. Only existing owner/admin authority may approve or reject; pending changes do not alter current published fields.
- Add archive/restore and required bulk actions to the list while preserving list query state and existing delete behavior.
- Add preview and UTM/custom-parameter builder controls. The preview is non-executing and never loads an external destination in an iframe.
- Add CSV import/export routes and UI. Use the same server-side contracts as normal link mutations; enforce row limits, file size, per-row validation, quotas, workspace scope, deterministic columns, and safe error output.
- Update TS types/routes and use existing Inertia/shadcn list/form patterns. Retain mobile and keyboard usability: hide view controls when tables are unavailable, render cards for `view=table` on mobile, keep long active-filter values and destination URLs within their cards (URLs truncate while preserving full link text), and preserve the filters' full accessible names.

### 5. Complete folder rollups

- Add `analytics_events_count` (or equivalent clear public name) to the workspace-safe folder query/model relations and Inertia type.
- Render links, clicks, and event totals in folder cards/rows without changing deletion semantics.
- Keep query, mutation, sorting, pagination, and counts tied to the active workspace. A mismatched historical folder/link association must not leak a foreign count.

### 6. PRD, catalog, and operational evidence

- Add LNK-13, LNK-14, and FOL-4 rows to the PRD tables and align §7.2 acceptance wording with active-workspace authorization and the feature catalog. Preserve existing requirement IDs and statuses.
- Update `short-links.json` and `folders.json` statuses/evidence only after observed tests, migration checks, browser scenarios, and reviews. Update `product/progress.json` consistently.
- Document the trusted country-proxy header/CIDR configuration and its safe default. No deployment may infer country from untrusted client headers.

### 7. Verification and sign-off

- Run focused suites for `Links/PublicRedirectTest`, `Links/LinkManagementTest`, `Links/LinkInsightsTest`, `Links/LinkTagsTest`, `Folders/FolderManagementTest`, and the new CSV/revision/routing tests.
- Run the complete Laravel suite, `ProductCatalogTest`, type check, frontend lint/format check, and production build after fixes.
  Migrate a disposable persistent SQLite database with representative legacy links, overlays, folder associations, and clicks; verify the `click_count` backfill, explicit approved state, data preservation, and safe rollback refusal when new campaign data exists.
  Browser-smoke create/edit/preview/UTM, revision review, targeting order, archive/restore, bulk actions, import outcomes, export, and folder rollups at desktop and 390px. Exercise keyboard flows and the mandated shell regressions: global-search shortcut/results/Escape focus restoration and mobile-drawer focus containment/restoration. Use the isolated Phase 5 app only, never shared port 8000.
- Obtain independent user-UX, admin-UX/authorization, and PRD-alignment reviews. Resolve every actionable finding and re-review fixes before Phase 5 closes.

## Completion evidence

Only mark LNK/FOL requirements verified after the required focused/full tests, migration proof, and browser evidence pass. The phase remains open if any specified workflow is absent, any reviewer finding remains actionable, or a PRD claim lacks matching implementation evidence.
