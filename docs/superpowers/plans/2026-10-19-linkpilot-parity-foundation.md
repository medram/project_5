# LinkPilot Parity Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add owned folders, controlled public short-link access, and locally generated QR SVG distribution to LinkPilot.

**Architecture:** Folders remain a small owned resource and links hold a nullable foreign key. Public-link delivery stays in the existing public controller, which delegates scheduling and access-code decisions to Link model predicates; a separate endpoint records a short-lived session authorization after code verification. QR rendering is a protected derived representation of an existing link, not a second link model, and uses a PHP-compatible SVG-only library.

**Tech Stack:** Laravel 13, PHP 8.3+, Inertia React 19, local shadcn/Radix UI components, SQLite, Endroid QR Code 6.0.9.

**Spec:** `docs/superpowers/specs/2026-10-19-linkpilot-parity-foundation-design.md`

## Global Constraints

- All new dashboard UI uses the repository's local shadcn/Radix primitives and existing Inertia conventions.
- Folder ownership must be enforced on every create, assignment, mutation, and delete path.
- Folder deletion clears `links.folder_id`; it never deletes links.
- Access codes are Laravel hashes only and must not be returned in Inertia props or stored in browser storage.
- Public links that are inactive, future, or expired return HTTP 404 without recording a click.
- A protected link records its click only after its correct access code succeeds for the current server session.
- QR data encodes the canonical public LinkPilot URL and SVG is generated locally; no remote QR service is used.
- Pin `endroid/qr-code` to `6.0.9`: its PHP `^8.2` requirement keeps LinkPilot's advertised PHP 8.3 support, unlike current 6.1 releases requiring PHP 8.4.
- Do not add standalone QR records, QR styling/scanning analytics, DNS integrations, or role/billing/workspace features in this slice.

## Review Focus

- A forged `folder_id` must be rejected rather than attaching another user's folder; cover it in Task 2's link creation test.
- A folder deletion must preserve every linked short link while nulling only its folder reference; cover it in Task 1's delete test.
- An expired or future protected link must not reveal the unlock form, redirect, or create a click; cover it in Task 2's availability tests.
- An incorrect access code must not create a session grant or click; cover it in Task 2's unlock test.
- A QR endpoint request for another user's link must be forbidden even when the ID exists; cover it in Task 3's authorization test.

---

### Task 1: Owned folders

**Files:**
- Create: `database/migrations/2026_10_19_000400_create_folders_table.php`
- Create: `app/Models/Folder.php`
- Create: `database/factories/FolderFactory.php`
- Create: `app/Policies/FolderPolicy.php`
- Create: `app/Http/Controllers/FolderController.php`
- Create: `app/Http/Requests/StoreFolderRequest.php`
- Create: `app/Http/Requests/UpdateFolderRequest.php`
- Create: `resources/js/components/folders/folder-form-dialog.tsx`
- Create: `resources/js/pages/folders/index.tsx`
- Create: `resources/js/types/folders.ts`
- Create: `database/migrations/2026_10_19_000500_add_folder_id_to_links_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Link.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Test: `tests/Feature/Folders/FolderManagementTest.php`

**Interfaces:**
- Produces: `Folder` with `user(): BelongsTo`, `links(): HasMany`, and `clicks(): HasManyThrough`; `Link::folder(): BelongsTo`; named `folders.index`, `folders.store`, `folders.update`, and `folders.destroy` routes.
- Produces: `FolderPayload` and folder index props for Task 2's owned-folder selector.
- Consumes: current authenticated/verified dashboard convention and existing `Link` model.

- [ ] **Step 1: Write failing folder feature tests**

Create `FolderManagementTest` with a real authenticated owner. Assert the owner can create/rename a folder; another user gets 403 on update/delete; deleting a folder preserves an attached link and changes `folder_id` to null; and the index only contains the owner’s folder data with link/click counts.

- [ ] **Step 2: Run the focused tests to verify they fail**

Run: `docker compose run --rm app php artisan test tests/Feature/Folders/FolderManagementTest.php`

Expected: FAIL because the folders routes/models do not exist.

- [ ] **Step 3: Implement owned folder persistence and server endpoints**

Add the folders migration (`user_id`, `name` length 80, timestamps) and additive nullable `links.folder_id` with `nullOnDelete()` and an index. Implement model relations, user relation, owner policy, normalized/trimming requests, and `FolderController` index/store/update/destroy. The index must load only the signed-in user’s folders and aggregate link/click counts without N+1 queries.

- [ ] **Step 4: Implement the native shadcn folder surface**

Create an Inertia folders index with list/count cards and a native dialog shared by create/edit. Add the Folder nav item using the existing sidebar pattern. Use generated Wayfinder routes; do not hand-edit `resources/js/routes/**`.

- [ ] **Step 5: Run focused tests to verify they pass**

Run: `docker compose run --rm app php artisan test tests/Feature/Folders/FolderManagementTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the owned-folder task**

```bash
git add app database resources/js routes tests docs/superpowers
git commit -m "feat: add short link folders"
```

### Task 2: Controlled short-link access

**Files:**
- Create: `database/migrations/2026_10_19_000600_add_delivery_controls_to_links_table.php`
- Create: `app/Http/Controllers/LinkAccessController.php`
- Create: `app/Http/Requests/UnlockLinkRequest.php`
- Create: `resources/js/pages/links/unlock.tsx`
- Modify: `app/Models/Link.php`
- Modify: `app/Http/Controllers/PublicLinkController.php`
- Modify: `app/Http/Controllers/LinkController.php`
- Modify: `app/Http/Requests/StoreLinkRequest.php`
- Modify: `app/Http/Requests/UpdateLinkRequest.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/links/link-form-dialog.tsx`
- Modify: `resources/js/pages/links/index.tsx`
- Modify: `resources/js/types/links.ts`
- Test: `tests/Feature/Links/LinkManagementTest.php`
- Test: `tests/Feature/Links/PublicRedirectTest.php`

**Interfaces:**
- Consumes: Task 1’s `Folder` ownership and `folders` index prop.
- Produces: `Link::isAvailableAt(CarbonInterface $at): bool`, `Link::requiresAccessCode(): bool`, and named `links.unlock` POST route.
- Produces: link payload fields `folder_id`, `folder`, `available_from`, `expires_at`, and `has_access_code`; no password hash field is serialized.

- [ ] **Step 1: Write failing link-delivery tests**

Extend link-management tests to assert creation accepts one owned `folder_id` and rejects another user’s folder. Extend public redirect tests to assert a future link and an expired link 404 with zero clicks; a protected link renders the unlock Inertia page with zero clicks; an incorrect code leaves it locked; and a correct code redirects then creates exactly one click. Add a validation test that rejects an availability window whose start is not earlier than expiration.

- [ ] **Step 2: Run the focused tests to verify they fail**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/LinkManagementTest.php tests/Feature/Links/PublicRedirectTest.php`

Expected: FAIL because delivery-control fields and unlock route are absent.

- [ ] **Step 3: Implement link delivery controls and public gate**

Add nullable `access_code`, `available_from`, and `expires_at` columns; cast timestamps. Keep the hash private. Validate/transform a non-empty submitted code with Laravel `Hash::make`, preserve an existing code on an empty edit, and support an explicit `clear_access_code` boolean. Resolve owned folders only from `$request->user()->folders()`.

Add availability predicates to `Link`, make `PublicLinkController` 404 before all click creation when the link is inactive or out of window, and render `links/unlock` for locked links without a session grant. `LinkAccessController` validates the code, records `unlocked-links.{id}` in the session only on success, and redirects back to the public slug. On successful return, `PublicLinkController` records one click and redirects as it already does.

- [ ] **Step 4: Extend the native link form and list**

Load the owner’s folders in the links index. Add an owned-folder select, date/time fields, and an access-code checkbox/input with an explicit remove-code action to the existing shadcn dialog. Render schedule/protection/folder metadata in the list without exposing the hash. Add the public access-code form with native existing fields/buttons.

- [ ] **Step 5: Run focused tests to verify they pass**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/LinkManagementTest.php tests/Feature/Links/PublicRedirectTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the delivery-controls task**

```bash
git add app database resources/js routes tests docs/superpowers
git commit -m "feat: add controlled short link access"
```

### Task 3: Local QR distribution

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `app/Http/Controllers/QrCodeController.php`
- Create: `app/Http/Controllers/LinkQrCodeController.php`
- Create: `resources/js/pages/qr-codes/index.tsx`
- Create: `resources/js/types/qr-codes.ts`
- Modify: `routes/web.php`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `README.md`
- Test: `tests/Feature/QrCodes/QrCodeTest.php`

**Interfaces:**
- Consumes: `Link` ownership, public redirect route, and standard `Gate::authorize('view', $link)` convention.
- Produces: named `qr-codes.index` and `links.qr-code` routes; `GET /links/{link}/qr-code.svg` returns a locally generated `image/svg+xml` QR representation.

- [ ] **Step 1: Write failing QR feature tests**

Create `QrCodeTest` that asserts the QR index includes only owned links, the owner receives an `image/svg+xml` response containing SVG markup for their link, and a non-owner receives 403 for the SVG endpoint.

- [ ] **Step 2: Run the focused tests to verify they fail**

Run: `docker compose run --rm app php artisan test tests/Feature/QrCodes/QrCodeTest.php`

Expected: FAIL because the QR routes/controllers do not exist.

- [ ] **Step 3: Add the PHP-compatible QR dependency and implement endpoints**

Run `docker compose run --rm app composer require endroid/qr-code:6.0.9`. Implement an authenticated `QrCodeController` that lists the signed-in owner’s links with click counts. Implement `LinkQrCodeController` with ownership authorization and Endroid’s SVG writer, encoding `route('links.redirect', ['slug' => $link->slug])` as an absolute URL.

- [ ] **Step 4: Build the native QR-code dashboard surface**

Add the QR-codes sidebar entry and page. Display each link’s title/slug/destination, total clicks, SVG preview, and download control through the protected endpoint. Use existing Card, Button, Empty, and page layout primitives; no custom component library or external image endpoint.

- [ ] **Step 5: Document local QR behavior and run focused tests**

Document the locally generated QR endpoint in the README. Run: `docker compose run --rm app php artisan test tests/Feature/QrCodes/QrCodeTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the QR-distribution task**

```bash
git add app composer.json composer.lock database resources/js routes tests README.md docs/superpowers
git commit -m "feat: add local QR code distribution"
```

### Task 4: Release verification

**Files:**
- Modify: `README.md` only if the manual smoke workflow needs one concise update.

**Interfaces:**
- Consumes: all Task 1–3 routes and UI paths.
- Produces: release evidence, no new runtime interface.

- [ ] **Step 1: Run complete backend and production frontend verification**

Run: `docker compose run --rm app php artisan test && docker compose run --rm app npm run build`

Expected: Laravel suite and Vite production build PASS.

- [ ] **Step 2: Exercise the new UI against the running Compose app**

Using the existing local server and a disposable test account, create a folder, create a protected scheduled link in it, unlock/redirect it once, and open its QR page. Confirm the protected redirect and generated QR SVG through the actual browser surface.

- [ ] **Step 3: Commit verification/documentation-only changes if any**

```bash
git add README.md docs/superpowers
git commit -m "docs: document LinkPilot distribution controls"
```

Skip this commit when there are no tracked documentation changes.
