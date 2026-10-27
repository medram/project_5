# LinkPilot Initial Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the first complete LinkPilot loop: an authenticated user creates a short link, a visitor is redirected and recorded, the owner sees the insight, and the owner can publish an ordered link-in-bio page.

**Architecture:** Laravel 13 owns authentication, persistence, authorization, public redirects, and Inertia page data. React page components use Inertia forms and server-side routes instead of a separate REST API. Because PHP, Composer, and the Laravel installer are absent locally while Docker is available, a unified PHP 8.5/Node 22 Docker runtime runs Laravel, migrations, tests, and Vite tasks.

**Tech Stack:** Laravel 13, PHP 8.5/Node 22 (Docker), Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui from the official React starter kit, SQLite, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-10-16-linkpilot-design.md`

## Environment Facts

- Docker daemon is available; use it instead of modifying the host with a global PHP/Composer installation.
- Vite’s Wayfinder integration invokes `php artisan`, so JavaScript tasks run in the same Docker runtime rather than through host npm alone.
- Laravel’s official React starter kit provides React 19, Inertia 3, TypeScript, Tailwind 4, shadcn/ui, and authentication scaffolding.

## Global Constraints

- Use the official Laravel React starter kit with SQLite and session authentication; do not introduce a standalone API, client-side fetch layer, cloud tooling, billing, or analytics vendors.
- Preserve original LinkPilot copy, branding, and UI; do not copy source, assets, or text from the reference product.
- Public destinations and bio buttons accept only absolute HTTP(S) URLs. Reject a URL whose scheme, host, and effective port equal the configured `APP_URL` origin.
- Canonicalize all identifiers to lowercase ASCII. Link slugs use `[a-z0-9-]{4,32}` and bio handles use `[a-z0-9-]{3,32}`. Reserve `dashboard`, `login`, `register`, `logout`, and `bio` for short links.
- A short-link request records one `clicks` row, then returns HTTP 302. Do not retain visitor IP addresses, user agents, or other fingerprinting data.
- Use policies for ownership. An authenticated user may access only their links, clicks, bio pages, and bio links.
- Inertia controllers render React pages and React forms use `useForm`; do not return JSON for first-party dashboard pages or use Axios/fetch for those form submissions.
- Use visible labels, inline server validation errors, durable visible focus rings, keyboard-operable dialogs, and at least 44px touch targets for primary interactions.
- Compose frontend UI from the official starter’s built-in shadcn components using its configured primitive base; do not add external UI kits or registry components.

- Insights render a fourteen-day daily series in `config('app.timezone')`, defaulting to UTC. Render a truthful zero-data state instead of dummy chart values.
- Use REA browser captures when a reference surface or the implemented UI needs evidence-based observation; capture only the required navigation, DOM, accessibility, and screenshots.

## File Structure

| Path                                                                               | Responsibility                                                                             |
| ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `compose.yaml`                                                                     | Bind-mounted local Laravel runtime on port 8000.                                           |
| `docker/php/Dockerfile`                                                            | PHP 8.5/Node 22 CLI image with Composer and SQLite support.                                |
| `.dockerignore`                                                                    | Keeps local dependency/build noise out of the Docker image context.                        |
| `app/Models/Link.php`, `Click.php`                                                 | Short-link records, generated-slug behavior, click relationship.                           |
| `app/Models/BioPage.php`, `BioLink.php`                                            | Published bio-page records, ordered button relationship.                                   |
| `app/Policies/LinkPolicy.php`, `BioPagePolicy.php`                                 | Owner-only authorization.                                                                  |
| `app/Http/Requests/*LinkRequest.php`, `*BioPageRequest.php`, `*BioLinkRequest.php` | Normalization and validation at write boundaries.                                          |
| `app/Http/Controllers/LinkController.php`                                          | Authenticated short-link list and CRUD.                                                    |
| `app/Http/Controllers/LinkInsightsController.php`                                  | Total and fourteen-day click aggregation.                                                  |
| `app/Http/Controllers/PublicLinkController.php`                                    | Public 302 redirect and click persistence.                                                 |
| `app/Http/Controllers/BioPageController.php`, `BioLinkController.php`              | Authenticated bio-page and ordered-link management.                                        |
| `app/Http/Controllers/PublicBioPageController.php`                                 | Published public bio-page rendering.                                                       |
| `database/migrations/*_create_links_table.php`, `*_create_clicks_table.php`        | Link/click storage and referential cleanup.                                                |
| `database/migrations/*_create_bio_pages_table.php`, `*_create_bio_links_table.php` | Bio-page/button storage and referential cleanup.                                           |
| `database/factories/{Link,Click,BioPage,BioLink}Factory.php`                       | Isolated feature-test fixtures.                                                            |
| `resources/js/pages/links/index.tsx`, `insights.tsx`                               | Link management and insight pages.                                                         |
| `resources/js/components/links/link-form-dialog.tsx`                               | Reusable accessible create/edit form.                                                      |
| `resources/js/pages/bio-pages/index.tsx`, `edit.tsx`, `public.tsx`                 | Bio-page index, editor, and public surface.                                                |
| `resources/js/components/bio-pages/{editor,live-preview}.tsx`                      | Ordered editor controls and shared preview presentation.                                   |
| `resources/js/types/{links,bio-pages}.ts`                                          | Server-prop and UI data contracts.                                                         |
| `resources/js/components/app-sidebar.tsx`, `resources/css/app.css`                 | LinkPilot navigation and original visual tokens.                                           |
| `routes/web.php`                                                                   | Authenticated management routes followed by public bio and final wildcard redirect routes. |
| `tests/Feature/Links/*.php`, `tests/Feature/BioPages/*.php`                        | Behavioral regression coverage.                                                            |
| `README.md`                                                                        | Containerized setup, development, test, and build commands.                                |

## Review Focus

1. **Identifier collisions and case variants:** tests in Task 2 prove normalized custom slugs remain unique and generated slugs meet the contract.
2. **Route capture and reserved words:** tests in Tasks 2–3 prove reserved values cannot be stored and the final wildcard route does not shadow dashboard/auth/bio routes.
3. **Redirect safety and accounting:** tests in Task 3 prove invalid self-targets are rejected, inactive links return 404, and each active request inserts exactly one click before a 302.
4. **Insight boundary dates:** tests in Task 3 freeze time and prove the fourteen-day series includes today, omits older clicks, and supplies zero-filled dates.
5. **Ownership, publication, and ordering:** tests in Task 5 prove cross-user writes fail, unpublished pages are hidden, and reorder requests cannot inject/miss another page’s links.

---

### Task 1: Bootstrap the Containerized Laravel React Application

**Files:**

- Create: `compose.yaml`
- Create: `docker/php/Dockerfile`
- Create: `.dockerignore`
- Create: Laravel 13 React starter-kit files at repository root, including `artisan`, `composer.json`, `package.json`, `routes/web.php`, `resources/js/`, `resources/css/app.css`, and `database/database.sqlite`
- Modify: `.env.example`
- Test: generated `tests/Feature/Auth/*` and `tests/Feature/ExampleTest.php`

**Interfaces:**

- Produces a running Laravel application at `http://localhost:8000` with standard registration/login/logout routes and an Inertia React app shell.
- Produces `docker compose run --rm app php artisan <command>` and `docker compose run --rm app npm <command>` as the canonical PHP/Composer/test and JavaScript commands.

- [ ] **Step 1: Add a PHP 8.5 Compose runtime before creating app code**

Create `docker/php/Dockerfile` from `php:8.5-cli-bookworm`, retain its bundled `pdo_sqlite` support, install SQLite tooling, `git`, and `unzip`, then copy Composer and Node 22/npm from official stages and set `COMPOSER_HOME` to writable temporary storage. Create `compose.yaml` with an `app` service that bind-mounts the repository at `/var/www`, runs as the local UID/GID, exposes `8000:8000`, and runs `php artisan serve --host=0.0.0.0 --port=8000`. Add `.dockerignore` for `node_modules`, vendor output, test/cache output, and local environment files.

- [ ] **Step 2: Generate the Laravel React starter application with the official installer in a one-shot Composer container**

Run the installer in a disposable `composer:2` container mounted at the repository root using the host UID/GID so generated files remain editable. Install the Laravel installer inside that container, temporarily prepend an `npm` shim that exits successfully because the current installer invokes `npm install` even in non-interactive mode, run `laravel new /tmp/linkpilot --database=sqlite --react --no-interaction`, then copy the generated application contents into the mounted root without deleting `docs/`. The shim exists only in the one-shot container; remove the starter’s generated Laravel Sail dev dependency, then Step 3 runs the real containerized `npm install`. Do not add Laravel Boost, Laravel Cloud tooling, Sail, or another starter kit.

- [ ] **Step 3: Configure local SQLite and install JavaScript dependencies**

Set `APP_URL=http://localhost:8000` and `APP_TIMEZONE=UTC` in `.env`, retain generated SQLite configuration, then run `docker compose run --rm app npm install`, `docker compose run --rm app npm run build`, and `docker compose run --rm app php artisan migrate --force`.

- [ ] **Step 4: Run the generated baseline tests**

Run: `docker compose run --rm app php artisan test`

Expected: PASS. This task is generated framework/configuration setup; feature TDD begins in Task 2.

- [ ] **Step 5: Commit the verified application foundation**

```bash
git add compose.yaml docker/php/Dockerfile .dockerignore artisan composer.json composer.lock package.json package-lock.json routes resources app bootstrap config database tests .env.example README.md
git commit -m "feat: bootstrap Laravel React application"
```

### Task 2: Build Owner-Authorized Short-Link Management

**Files:**

- Create: `app/Models/Link.php`
- Create: `app/Policies/LinkPolicy.php`
- Create: `app/Http/Requests/StoreLinkRequest.php`
- Create: `app/Http/Requests/UpdateLinkRequest.php`
- Create: `app/Http/Controllers/LinkController.php`
- Create: `database/migrations/*_create_links_table.php`
- Create: `database/factories/LinkFactory.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Links/LinkManagementTest.php`

**Interfaces:**

- `GET /dashboard` redirects an authenticated user to `links.index`.
- `GET /links` (`links.index`) renders `links/index` with the signed-in owner’s links and total click counts.
- `POST /links` (`links.store`) accepts `destination_url`, nullable `title`, nullable `slug`, and `is_active`; it redirects to `links.index`.
- `PATCH /links/{link}` (`links.update`) updates only an owned link; `DELETE /links/{link}` (`links.destroy`) removes only an owned link.
- `links`: `user_id`, `slug`, `destination_url`, nullable `title`, `is_active`, timestamps; `slug` is unique and canonical lowercase.

- [ ] **Step 1: Write failing short-link management feature tests**

Create `tests/Feature/Links/LinkManagementTest.php` covering:

```php
public function test_owner_can_create_a_normalized_custom_link(): void
{
    $this->actingAs(User::factory()->create())
        ->post(route('links.store'), [
            'destination_url' => 'https://example.com/launch',
            'title' => 'Launch',
            'slug' => 'Launch-26',
            'is_active' => true,
        ])
        ->assertRedirect(route('links.index'));

    $this->assertDatabaseHas('links', [
        'slug' => 'launch-26',
        'destination_url' => 'https://example.com/launch',
        'is_active' => true,
    ]);
}
```

Add focused cases for generated seven-character lowercase slugs, duplicate and reserved slugs, invalid/non-HTTP(S)/self-origin destinations, owner edit/deactivate/delete, and another user’s 403/404 management attempt.

- [ ] **Step 2: Run the focused test to verify the expected red state**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/LinkManagementTest.php`

Expected: FAIL because `Link`, its routes, and its controller do not exist.

- [ ] **Step 3: Implement the smallest owner-scoped CRUD surface**

Create the migration with a unique `slug`, foreign key to `users`, nullable 160-character `title`, 2048-character `destination_url`, and boolean `is_active` defaulting true. Implement the `Link` relation to `User`, its lowercasing/generated-slug behavior, and `LinkPolicy` owner checks.

Implement both form requests. In `prepareForValidation`, lowercase non-empty identifiers. Validate title/destination limits, identifier regex, reserved words, uniqueness, and exact `APP_URL` scheme-host-effective-port loop rejection. Use `LinkController` with policy authorization and `Inertia::render('links/index', ...)`; Task 3 adds the database-side `clicks_count` once click storage exists.

Place authenticated management routes in `routes/web.php` before all later public routes, replacing the generated dashboard endpoint with an authenticated redirect to `links.index`.

- [ ] **Step 4: Run the focused test to verify green**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/LinkManagementTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the verified short-link management backend**

```bash
git add app/Models/Link.php app/Policies/LinkPolicy.php app/Http/Requests/StoreLinkRequest.php app/Http/Requests/UpdateLinkRequest.php app/Http/Controllers/LinkController.php database/migrations database/factories/LinkFactory.php routes/web.php tests/Feature/Links/LinkManagementTest.php
git commit -m "feat: add owned short links"
```

### Task 3: Record Public Redirects and Serve Per-Link Insights

**Files:**

- Create: `app/Models/Click.php`
- Create: `app/Http/Controllers/PublicLinkController.php`
- Create: `app/Http/Controllers/LinkInsightsController.php`
- Create: `database/migrations/*_create_clicks_table.php`
- Create: `database/factories/ClickFactory.php`
- Modify: `app/Models/Link.php`, `app/Http/Controllers/LinkController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Links/PublicRedirectTest.php`
- Test: `tests/Feature/Links/LinkInsightsTest.php`

**Interfaces:**

- `GET /links/{link}/insights` (`links.insights`) renders `links/insights` with `link`, `totalClicks`, and `dailyClicks: Array<{ date: string, clicks: int }>` for exactly fourteen application-timezone days ending today.
- Final route `GET /{slug}` (`links.redirect`) creates one click and returns HTTP 302 to an active link’s destination; inactive/missing slugs return 404.
- `Click` belongs to `Link` and stores only `link_id` and non-null `occurred_at`; it does not store visitor attributes.

- [ ] **Step 1: Write failing redirect and aggregation tests**

Create `PublicRedirectTest.php` with an active link request asserting `assertRedirect('https://example.com/launch')` and exactly one persisted click; add inactive and unknown-slug 404 cases.

Create `LinkInsightsTest.php`. Freeze time, create clicks on two in-window dates and one date older than fourteen days, then assert the owner gets a fourteen-element `dailyClicks` Inertia prop with zero-filled missing days and a `totalClicks` equal only to this link’s count. Add an ownership case for another user’s insight URL.

- [ ] **Step 2: Run the focused tests to verify the expected red state**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/PublicRedirectTest.php tests/Feature/Links/LinkInsightsTest.php`

Expected: FAIL because clicks, the public route, and the insights controller do not exist.

- [ ] **Step 3: Implement click persistence, public 302 routing, and portable daily aggregation**

Create `clicks` with a cascading `link_id` foreign key and non-null `occurred_at`. Add `Link::clicks()` and `Click::link()` relationships. Update `LinkController`’s list query to add `withCount('clicks')`. `PublicLinkController` must resolve only active links, insert one `Click` with `occurred_at` set to the current application time, and return `redirect()->away($link->destination_url, 302)`.

`LinkInsightsController` must authorize the link, derive the fourteen calendar dates from `config('app.timezone')`, fetch only the date-window records, group them in PHP by timezone-adjusted `occurred_at`, and return a zero-filled chronological series via `Inertia::render('links/insights', ...)`. Place the constrained `/{slug}` wildcard last in `routes/web.php`; Task 5 adds `/bio/{handle}` before that wildcard when its controller exists.

- [ ] **Step 4: Run the focused tests to verify green**

Run: `docker compose run --rm app php artisan test tests/Feature/Links/PublicRedirectTest.php tests/Feature/Links/LinkInsightsTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the verified redirect and insight behavior**

```bash
git add app/Models/Click.php app/Models/Link.php app/Http/Controllers/PublicLinkController.php app/Http/Controllers/LinkInsightsController.php database/migrations database/factories/ClickFactory.php routes/web.php tests/Feature/Links/PublicRedirectTest.php tests/Feature/Links/LinkInsightsTest.php
git commit -m "feat: track short link redirects"
```

### Task 4: Deliver the Short-Link Dashboard Surface

**Files:**

- Create: `resources/js/types/links.ts`
- Create: `resources/js/pages/links/index.tsx`
- Create: `resources/js/pages/links/insights.tsx`
- Create: `resources/js/components/links/link-form-dialog.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/layouts/app-layout.tsx`
- Modify: `resources/css/app.css`

**Interfaces:**

- `LinkListItem` includes `id`, `title`, `slug`, `destinationUrl`, `isActive`, `clicksCount`, and timestamps.
- `LinkInsightProps` includes one `LinkListItem`, `totalClicks: number`, and `dailyClicks: Array<{ date: string; clicks: number }>`.
- The UI submits only through named Inertia routes/forms from Tasks 2–3.

- [ ] **Step 1: Implement the LinkPilot dashboard visual foundation**

Adapt the starter kit’s sidebar app layout to a compact LinkPilot navigation with “Short links” and “Bio pages”. Define original CSS tokens in `resources/css/app.css`: ink/white base, `#2563EB` active blue, `#7C3AED` creator purple, and `#EC4899` action accent. Keep the dashboard data-first; do not recreate the reference product’s layout, copy, or decorative treatment.

- [ ] **Step 2: Implement accessible link list and create/edit dialog**

Use the starter kit’s native shadcn components and Inertia `useForm` in `link-form-dialog.tsx`; before using a component, inspect its local shadcn documentation and existing implementation. The dialog has persistent visible labels for destination URL, title, and optional custom slug; it renders field errors adjacent to controls, disables while processing, and returns focus to the trigger on close. `links/index.tsx` lists each link’s short URL, destination, active state, and click count with keyboard-operable edit, active/inactive, delete, and insights actions.

- [ ] **Step 3: Implement the truthful insight page**

Render total clicks plus a fourteen-day CSS/SVG-free bar list from `dailyClicks`; include date and count text so no meaning is color-only. When all values are zero, replace the chart with an explicit empty state. Do not add a chart dependency for this small fixed series.

- [ ] **Step 4: Build the React assets**

Run: `docker compose run --rm app npm run build`

Expected: PASS with TypeScript/Vite assets emitted and no type/build errors.

- [ ] **Step 5: Commit the verified short-link UI**

```bash
git add resources/js/types/links.ts resources/js/pages/links resources/js/components/links resources/js/components/app-sidebar.tsx resources/js/layouts/app-layout.tsx resources/css/app.css
git commit -m "feat: add short link dashboard"
```

### Task 5: Build Bio-Page Persistence, Authorization, and Public Resolution

**Files:**

- Create: `app/Models/BioPage.php`
- Create: `app/Models/BioLink.php`
- Create: `app/Policies/BioPagePolicy.php`
- Create: `app/Http/Requests/StoreBioPageRequest.php`
- Create: `app/Http/Requests/UpdateBioPageRequest.php`
- Create: `app/Http/Requests/StoreBioLinkRequest.php`
- Create: `app/Http/Requests/UpdateBioLinkRequest.php`
- Create: `app/Http/Requests/ReorderBioLinksRequest.php`
- Create: `app/Http/Controllers/BioPageController.php`
- Create: `app/Http/Controllers/BioLinkController.php`
- Create: `app/Http/Controllers/PublicBioPageController.php`
- Create: `database/migrations/*_create_bio_pages_table.php`
- Create: `database/migrations/*_create_bio_links_table.php`
- Create: `database/factories/BioPageFactory.php`
- Create: `database/factories/BioLinkFactory.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BioPages/BioPageManagementTest.php`
- Test: `tests/Feature/BioPages/PublicBioPageTest.php`

**Interfaces:**

- Authenticated `GET /bio-pages`, `POST /bio-pages`, `GET/PATCH/DELETE /bio-pages/{bioPage}`.
- Authenticated `POST /bio-pages/{bioPage}/links`, `PATCH/DELETE /bio-pages/{bioPage}/links/{bioLink}`, and `PATCH /bio-pages/{bioPage}/links/reorder` with a complete ordered `link_ids` array.
- Public `GET /bio/{handle}` renders `bio-pages/public` only for a published page, with its active `bioLinks` ordered by `position`.
- `BioPage`: owner, lowercase handle, 80-character display name, nullable 280-character bio, published state. `BioLink`: page, 100-character title, 2048-character HTTP(S) destination, position, active state.

- [ ] **Step 1: Write failing bio-page feature tests**

Create `BioPageManagementTest.php` with an owner creating a normalized handle and a named link, updating publication state, disabling a link, and submitting a complete reorder. Add cross-user edit/delete/reorder cases and a tampered reorder list containing a link from another page.

Create `PublicBioPageTest.php` proving a published page returns an Inertia response with only active links in `position` order and proving unpublished/unknown handles return 404.

- [ ] **Step 2: Run the focused tests to verify the expected red state**

Run: `docker compose run --rm app php artisan test tests/Feature/BioPages/BioPageManagementTest.php tests/Feature/BioPages/PublicBioPageTest.php`

Expected: FAIL because the bio models, routes, and controllers do not exist.

- [ ] **Step 3: Implement the smallest secure bio-page domain surface**

Create both migrations with cascade deletion and unique canonical `handle`. Add ordered relations (`bioLinks()->orderBy('position')`) and factories. Use `BioPagePolicy` for all parent resources; `BioLinkController` must authorize the containing page before every operation.

Normalize handles in form requests, validate the specified lengths/URL policy, and assign new buttons `max(position) + 1`. For reorder, require the exact set of that page’s link IDs once each, then update positions in a transaction. Add `GET /bio/{handle}` before the existing constrained `/{slug}` wildcard. `PublicBioPageController` resolves only published pages and eager-loads only active buttons in order before `Inertia::render('bio-pages/public', ...)`.

- [ ] **Step 4: Run the focused tests to verify green**

Run: `docker compose run --rm app php artisan test tests/Feature/BioPages/BioPageManagementTest.php tests/Feature/BioPages/PublicBioPageTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the verified bio-page backend**

```bash
git add app/Models/BioPage.php app/Models/BioLink.php app/Policies/BioPagePolicy.php app/Http/Requests app/Http/Controllers/BioPageController.php app/Http/Controllers/BioLinkController.php app/Http/Controllers/PublicBioPageController.php database/migrations database/factories routes/web.php tests/Feature/BioPages
git commit -m "feat: add publishable bio pages"
```

### Task 6: Deliver the Bio-Page Editor and Original Public Presentation

**Files:**

- Create: `resources/js/types/bio-pages.ts`
- Create: `resources/js/pages/bio-pages/index.tsx`
- Create: `resources/js/pages/bio-pages/edit.tsx`
- Create: `resources/js/pages/bio-pages/public.tsx`
- Create: `resources/js/components/bio-pages/editor.tsx`
- Create: `resources/js/components/bio-pages/live-preview.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/css/app.css`

**Interfaces:**

- `BioPageEditorProps` contains one owner-authorized page and its ordered buttons.
- `BioPagePublicProps` contains `displayName`, nullable `bio`, `handle`, and active ordered buttons only.
- Editor mutations submit through the routes from Task 5; reordering is explicit “move up”/“move down”, not drag-and-drop.

- [ ] **Step 1: Implement page index and identity editor**

Provide an authenticated bio-page index with create, edit, publish/unpublish, and delete actions. The editor uses visible labeled `useForm` controls for handle, display name, bio, and publication state. It shows the public URL and server-side validation errors without claiming unpublished content is live.

- [ ] **Step 2: Implement ordered outbound-button controls and live preview**

Use `editor.tsx` to add/edit/disable/delete buttons and explicit, keyboard-operable “Move up”/“Move down” actions that submit the complete order. Use `live-preview.tsx` for the same display-name/bio/button presentation used by the public page, distinguishing a preview from a published page with text rather than duplicated UI logic.

- [ ] **Step 3: Implement the public bio-page surface**

`bio-pages/public.tsx` is a full-height mobile-first page with the design’s bold identity block, original styling, clear outbound buttons, semantic heading/paragraph/list structure, focus-visible links, and no dashboard navigation. It must behave well in a narrow viewport without horizontal scrolling.

- [ ] **Step 4: Build the React assets**

Run: `docker compose run --rm app npm run build`

Expected: PASS with the editor and public page included in the Vite manifest.

- [ ] **Step 5: Commit the verified bio-page UI**

```bash
git add resources/js/types/bio-pages.ts resources/js/pages/bio-pages resources/js/components/bio-pages resources/js/components/app-sidebar.tsx resources/css/app.css
git commit -m "feat: add bio page editor"
```

### Task 7: Document, Exercise, and Verify the Complete Release

**Files:**

- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-10-16-linkpilot-design.md` only if implementation exposes a real spec mismatch
- Test: full Laravel suite and Vite production build

**Interfaces:**

- README documents the only supported local commands: Docker build/serve, migrations, tests, and npm build.
- The checked path is registration → create link → public redirect/click → insight count → create/publish bio page → public bio page.

- [ ] **Step 1: Document local setup and runtime commands**

Update `README.md` with prerequisites (Docker), one-shot Laravel setup already performed, `docker compose up --build`, `docker compose run --rm app php artisan migrate`, `docker compose run --rm app php artisan test`, `docker compose run --rm app npm install`, and `docker compose run --rm app npm run build`. Explain that the app is served at `http://localhost:8000` and is configured for SQLite by default.

- [ ] **Step 2: Run the complete automated verification**

Run:

```bash
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

Expected: Laravel tests PASS and Vite build exits 0.

- [ ] **Step 3: Perform an actual browser smoke scenario**

Start the app with `docker compose up --build -d`. Use REA `capture_browser_scenario` to retain final URL, DOM, accessibility, and screenshot evidence while registering a new account; creating an active `https://example.com/` short link; opening its public short URL; verifying the redirect; returning to its insight page and observing count `1`; creating a bio page with two buttons; disabling one; publishing it; and verifying public `/bio/{handle}` shows only the active button. Capture a narrow mobile viewport and verify visible focus/keyboard controls for dialogs and reorder actions. Stop the owned Compose service after capture.

- [ ] **Step 4: Commit the verified release**

```bash
git add README.md docs/superpowers/specs/2026-10-16-linkpilot-design.md
git commit -m "docs: document LinkPilot setup"
```

## Plan Self-Review

- **Spec coverage:** Tasks 1–4 cover authentication, owned short links, public redirects, click persistence, insights, and dashboard usability. Tasks 5–6 cover bio-page ownership, publication, ordering, preview, and public presentation. Task 7 covers documentation and end-to-end proof. QR codes, folders, domains, teams, deep analytics, and tracking providers remain deferred as specified.
- **Step scan:** Every test step names a concrete file and observable behavior; every implementation step names its route, model, page, or validation contract. Generated framework/container setup is isolated to Task 1.
- **Type consistency:** `LinkListItem`, `LinkInsightProps`, `BioPageEditorProps`, public route names, identifier rules, and daily-series shape are defined once and used by their producing/consuming tasks.
- **Review focus:** All five high-risk input classes have a named owner task and concrete regression coverage.
- **Proportion:** The plan specifies contracts and checks without prescribing private function bodies or adding unrequested providers, integrations, or abstraction layers.
