# Agent guide

LinkPilot is a Laravel 13 + Inertia 3 + React 19 + Tailwind CSS 4 + shadcn/ui publishing console for owned short links, QR campaigns, bio pages, link pages, domains, analytics, billing, and platform administration. This file is the operating contract for full-stack feature work. Keep it short; detail belongs in the owning doc.

## Operating contract

- **Own the slice end to end.** Migration → model → policy → request → service → controller → route → TypeScript contract → page/component → test → browser proof. Rendering the happy path is not done.
- **Quality first, speed second.** You have time. Read the owner, design the contract, cover the edges. Do not gold-plate: the smallest correct change that fully solves the request wins.
- **Read before write; reuse before create.** Open the current page, controller, service, request, policy, type, and test. Extend the existing pattern. A second real consumer is the threshold for a shared abstraction.
- **No placeholders.** No stubs, TODOs, fake data, fake endpoints, or "follow-up" deferrals. Finish the reachable behavior.
- **Report evidence.** What changed, what you ran, what you observed, what you did not verify. Never claim a check you did not run.

## Stack and map

| Layer         | Technology                                         | Owns                                                   |
| ------------- | -------------------------------------------------- | ------------------------------------------------------ |
| Backend       | Laravel 13 / PHP 8.3                               | models, policies, requests, services, jobs, migrations |
| Routing       | Laravel routes + Inertia 3                         | page props, redirects, validation                      |
| UI            | React 19 + shadcn/ui + Radix                       | pages, domain components, interactions                 |
| Styling       | Tailwind CSS 4 + CSS variables                     | layout, spacing, semantic color                        |
| URLs          | Wayfinder                                          | generated route/action helpers                         |
| Build/tooling | Vite+ (`vp`)                                       | dev, build, lint, format, tests                        |
| Charts/maps   | Recharts, `react-svg-worldmap`, local `flag-icons` | charts and country maps                                |

| Area       | Path                                                                                               |
| ---------- | -------------------------------------------------------------------------------------------------- |
| Backend    | `app/` (`Http/Controllers`, `Http/Requests`, `Models`, `Policies`, `Services`, `Enums`, `Jobs`)    |
| Routes     | `routes/web.php`, `routes/api.php`, `routes/settings.php`                                          |
| DB         | `database/migrations`, `database/factories`, `database/seeders`                                    |
| Tests      | `tests/Feature/**`, `tests/Unit/**`; frontend tests are colocated `*.test.ts(x)`                   |
| Frontend   | `resources/js/pages`, `components` (domain), `components/ui` (primitives), `hooks`, `lib`, `types` |
| CSS/tokens | `resources/css/app.css`                                                                            |
| Docs       | `docs/DESIGN.md`, `frontend-instructions/`                                                         |

Generated/protected: `resources/js/routes/**`, `actions/**`, `wayfinder/**` (Wayfinder), `components/ui/**` (shadcn). Never edit generated files; fix the Laravel route and regenerate. `.superpowers/sdd/**` is scratch; exclude it from `vp test`.

## Read order

1. `docs/DESIGN.md` §2–§9. §7.2a is account analytics, §7.2b admin platform, §7.2c the standard for every insights page.
2. `frontend-instructions/FRONTEND_INSTRUCTIONS.md` for stack rules and the task router; then the guides it names. Read `10-AGENT-WORKFLOW.md` before starting and `09-TESTING-AND-VERIFICATION.md` before claiming done.
3. `frontend-instructions/PROJECT-PROFILE.md` for commands, paths, shared props, and layout wiring; `PROJECT-SURFACES.md` for domain surfaces and their entry files.
4. The owning code and its tests, in the current working-tree state.

If code and a guide disagree, follow the guide for new code and report the deviation. Never copy legacy debt just because it is in the file you opened.

After changing `frontend-instructions/`, run `node frontend-instructions/scripts/check-pack.mjs`. The checker requires `AGENTS.md` to point at `frontend-instructions/FRONTEND_INSTRUCTIONS.md`; keep that path literal.

## Delivery loop

`Frame → Discover → Contract → Backend → Wire → UI → Prove`

1. **Frame.** One sentence: actor, action, surface, authorization, data, non-goals. Name the owner and the permission.
2. **Discover.** `find` for behavior, `grep` for symbols. Read the page, controller, service, request, policy, type, and test. Do not open a random similar page.
3. **Contract.** Decide table/columns/indexes, validation, payload, permissions, and empty/error states before UI. The server contract is the source of truth; the TypeScript type mirrors it.
4. **Backend.** Migration → model/casts/relations → factory/seeder → policy/gate → FormRequest → service → controller → route → feature test. Keep the route group, middleware, and workspace scope consistent.
5. **Wire.** Add/update `resources/js/types/*`; regenerate Wayfinder through the build or running app; confirm the helper exists. No hand-written product URLs.
6. **UI.** Page shell → domain component → primitives; then loading, empty, filtered-empty, error; then accessibility and responsive. Reuse the shared primitives named in `PROJECT-SURFACES.md`.
7. **Prove.** Run the narrow checks in Verification, exercise the browser path at 1440/1024/768/390, and report evidence.

## Backend rules

- **Thin controllers.** Authorize, validate through a FormRequest, call a service/action, render Inertia or redirect. No business logic in controllers or closures.
- **FormRequest is the only validation schema.** Use `rules()` and `after()`; never duplicate rules on the client. `authorize()` may return `true` only when the route/middleware/policy already owns access.
- **Services own multi-step logic.** Put them under `app/Services/<Domain>/`; name them for the job. They return arrays, DTOs, or models; they do not render.
- **Policies/gates own record authorization; middleware owns surface access** (`platform:admin.*`, `active-account`, `verified`). Hiding UI is not authorization.
- **Models** own relationships, casts, fillable, scopes, and small invariants. No HTTP/Inertia in models. Use enums for closed sets.
- **Migrations are additive by default.** Nullable/safe defaults; backfill before non-null; index FKs; choose cascade/null behavior deliberately; workspace-owned tables carry `workspace_id`. No destructive change without approval.
- **Queries:** scope through `WorkspaceContext`; eager-load; select needed columns; `withCount`/`withExists`; paginate with `withQueryString()`; aggregate in SQL; zero-fill series in the service; wrap multi-writes in `DB::transaction`.
- **Security:** server authorization; no secrets in props (`access_code`, tokens, webhook secrets); sanitize server-owned HTML; verify webhook/provider signatures; hash access codes; return URLs never grant entitlements.
- **Tests:** feature tests under `tests/Feature/<Area>` with `RefreshDatabase`, `AssertableInertia`, and `withoutVite()` for props. Test permissions, validation, payload shape, scoping, and the state change. Unit-test pure services/rules with non-obvious edges. `tests/TestCase.php` requires in-memory SQLite.

## Frontend rules

The pack owns the detail; these are the non-negotiables.

- **Inertia owns data.** Page props are the data layer. No React Query, SWR, Redux, Zustand, client cache, or data-fetching hook.
- **Wayfinder for product URLs.** Import `@/routes/*` and `@/actions/*`. No Ziggy, no global `route()`, no raw `/dashboard/...` in new code. Server-provided pagination/export URLs are the exception.
- **Mutations:** `<Form {...Action.form()}>` for page/auth/settings forms; `useForm<T>()` for dialogs/editors; `router.post/patch/delete` for one-shot actions; `router.get` for filters/ranges. Never `router.put`; use `patch` or `_method`. Default `preserveScroll: true`; `preserveState` for filters; `replace` for ranges; `only` for expensive reloads.
- **Composition:** a page owns `<Head>`, wrapper, `PageHeader`, and arrangement; a domain component owns one reusable slice and fetches nothing; `components/ui` owns primitives; `hooks` browser behavior; `lib` pure functions; `types` contracts. Dependencies point page → component → ui/lib. No barrel files, no `components/common`, no second layout registry.
- **Page anatomy** (guide 02): `<Head>` → max-width wrapper → `PageHeader` → KPI row → toolbar → main content → pagination → one closing note. One `h1`; no eyebrow, decorative icon, or duplicate title. There is no `PageContainer`.
- **Forms:** server `FormRequest` only; `Field`/`FieldLabel`/`FieldError`/`FormErrorSummary`; inline errors, never toasts. Invalid submit preserves values and focuses the summary/field. Dialogs reset on open, return focus on close, and keep the footer reachable at 390 px.
- **States:** skeletons match final heights; shared `Empty` block with one sentence and one next step; distinguish filtered-empty from true empty; errors stay inside the affected card; never invent demo rows.
- **Styling:** semantic tokens only (`bg-card`, `text-muted-foreground`, `var(--chart-*)`); no raw palette for status, no hex. Cards 16 px radius/16 px padding; controls 8–12 px; 4 px spacing scale; sentence case; `tabular-nums`; one accent per chart; no gradient text, glow, rainbow series, uppercase tracked labels, or emoji icons.
- **Accessibility/responsive:** every icon-only control has a name; focus is visible; keyboard works; touch targets ≥44 px; no page-level horizontal scroll at 390 px; tables scroll inside their panel or become cards.
- **Formatting:** dates ISO → format at render; money via `formatMoney`; countries via `countryName`/`CountryFlag`.

## Design authority

`docs/DESIGN.md` is binding. Read §2–§9 before changing a surface. The language is HeroUI Pro's dashboard implemented with shadcn/ui primitives; do not install HeroUI. Composition references: `docs/reference/hero-dashboard.png`, `hero-dashboard-2.png`. The platform accent is `html[data-platform-accent]`, remapping `--primary` and `--chart-1…5`; never hard-code a hue.

Every insights page follows §7.2c: one header, one window control, a 3–5 card KPI row, one main 300 px time series, a full-width 4:3 country map with ranked flag legend, donut distributions, ranked lists, closing snapshots, and one privacy note. Read `frontend-instructions/PROJECT-SURFACES.md` for the surface entry files and "do not rebuild" list before building one. If a chart needs a new field, add it to the service and the TypeScript contract first; never derive a new total in the page. Do not repeat the k-anonymity threshold in every card or add an exact daily-values table to aggregate/admin pages.

## Verification

| Purpose                   | Command                                                                  | Prerequisite                                                           |
| ------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------- |
| Type check                | `npm run types:check`                                                    | `node_modules`                                                         |
| Focused lint/format check | `npx vp check --no-fmt <changed paths>`                                  | `node_modules`                                                         |
| Format fix                | `npx vp check --fix <changed paths>`                                     | only your files                                                        |
| Frontend tests            | `npx vp test <explicit paths>`                                           | PHP on `PATH` or app container                                         |
| Production build          | `npm run build`                                                          | PHP on `PATH` or app container; run when routing/Wayfinder/CSS changes |
| Backend tests             | `docker compose -f compose.dev.yaml exec -T app php artisan test <path>` | Compose stack                                                          |
| PHP lint                  | `vendor/bin/pint --test <paths>`                                         | `vendor`                                                               |
| PHPStan                   | `composer types:check`                                                   | `vendor`; level 7                                                      |
| Full CI                   | `composer ci:check`                                                      | full stack                                                             |

- `npm run types:check` is mandatory; `vp check` is not type-aware enough.
- `vp test`/`vp build` load the Laravel Vite plugin and run Wayfinder; if the host has no PHP, use the app container or a PHP shim. Prefix `LARAVEL_BYPASS_ENV_CHECK=1` if the CI guard blocks.
- Pass explicit test paths; a bare `npx vp test` discovers `.superpowers/sdd/**` scratch suites and fails.
- Scope `vp check --fix` to your files; never run a repo-wide formatter. Backend changes need the relevant PHPUnit file.

**Done when:** behavior works end to end (validation, loading, empty, filtered-empty, error); authorization is server-side; 1440/1024/768/390 have no page-level overflow and a clean console; focus/names/announcements are correct; types, focused lint, relevant tests, and build (when routing/CSS changed) pass; shared-rule docs updated; pack checker passes if the pack changed. Report with the template in `09-TESTING-AND-VERIFICATION.md`.

## Working tree

- Other agents may have uncommitted edits. Read the current working-tree version immediately before editing; preserve unrelated hunks.
- No worktrees. Work in the current checkout.
- One owner per file per task; sequence shared primitives.
- Format and commit only your files; `git diff -- <paths>` before handoff. Never commit credentials, generated files, or another agent's scratch work.
- Commit style follows the history: `feat(area): …`, `fix(area): …`, `refactor(area): …`, `docs: …`, `test: …`.

## Fast defaults

| Need                   | Default                                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------ |
| New page               | Route + controller + `pages/<area>/<name>.tsx` + layout registry check                                             |
| New admin page         | Admin route group + `platform:admin.*` + `AdminLayout` via `admin/` prefix + sidebar ability                       |
| New list               | Paginator + `resource-list/*` + domain row + empty/filtered-empty states                                           |
| New form               | `FormRequest` + `<Form>`/`useForm` + `Field`/`FieldError` + inline errors                                          |
| New dialog             | `Dialog` for forms, `AlertDialog` for destructive confirmation, `Sheet` for mobile panels                          |
| New chart              | Shared chart primitive; one series; no second library                                                              |
| New settings section   | `settings-form.ts` + section service/contract + `section.tsx`; no new shell                                        |
| New shared component   | Only after a second consumer; otherwise keep it in the domain folder                                               |
| New dependency         | Don't. Compose Radix/shadcn or use the framework. Ask only if the stack cannot do it.                              |
| New JSON endpoint      | Only for the API surface; Inertia pages use controller props, not a client fetch.                                  |
| New migration          | Additive, indexed FKs, safe defaults; no destructive change without approval.                                      |
| Unsure where code goes | Page composition → page; reusable UI → domain component; primitive → `ui`; pure logic → `lib`; contract → `types`. |

## Escalation

Ask before a destructive migration, a new dependency, a visual redesign, a public API/contract break, or scope expansion beyond the request. Otherwise decide, state the choice in one line, and continue. Stop only when the reachable work is done and verified, or a tool/environment blocks you. A failed check is not a blocker: report the exact error and the alternative. Never end with a stub, TODO, "MVP", "follow-up", or a narrowed test; shrink scope only with explicit approval.
