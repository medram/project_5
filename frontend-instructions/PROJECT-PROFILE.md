# Project profile

Project: LinkPilot. This is the only file a new project must rewrite for
paths, versions, commands, and bindings. The guides stay stack-scoped.

## Project identity

| Item | Value |
|---|---|
| Name | LinkPilot |
| Framework | Laravel `^13.17` (PHP `^8.3`) |
| Inertia Laravel | `^3.0` |
| Inertia React | `^3.0` |
| React | `^19.2` |
| Tailwind CSS | `^4.0` |
| shadcn/ui | `new-york`, locally themed |
| Wayfinder | `^0.1.14` |
| Vite+ | `0.3.0` with Vite `^8.0` |
| TypeScript | `^5.7` |
| Visual spec | `docs/DESIGN.md` §2–§9 |
| Compositions | `docs/reference/hero-dashboard.png`, `docs/reference/hero-dashboard-2.png` |

## Commands

Run from the repository root.

| Purpose | Command | Notes |
|---|---|---|
| Dev app + Vite | `compose.dev.yaml` stack; app `http://localhost:8000`, Vite `http://localhost:5173` | `docker compose -f compose.dev.yaml up -d` |
| Production build | `npm run build` | Runs Wayfinder generation and Vite |
| Type check | `npm run types:check` | `tsc --noEmit`; mandatory, `vp check` is not type-aware enough |
| Lint/format check | `npx vp check --no-fmt <paths>` | Only paths you changed |
| Lint/format fix | `npx vp check --fix <paths>` | Only paths you changed |
| Frontend tests | `npx vp test <explicit paths>` | Vitest via `vite-plus/test`; pass the test files, not a bare run |
| Backend tests | `docker compose -f compose.dev.yaml exec -T app php artisan test <path>` | PHPUnit |
| Full CI | `composer ci:check` | `npm run check`, `types:check`, Pint, PHPStan, backend tests |
| PHP lint | `vendor/bin/pint --test <paths>` | Repo-wide via CI |

`npx vp test` loads `vite.config.ts` and runs Wayfinder generation. Pass
explicit test paths: a bare run discovers `.superpowers/sdd/**` scratch suites
that import `bun:test` and fails. On a host without PHP, use one of:

```bash
# Container form (node is present in the app image)
docker compose -f compose.dev.yaml exec -T app npm run build
docker compose -f compose.dev.yaml exec -T app npx vp test resources/js/lib/money.test.ts

# Or a host shim that proxies php into the app container
PATH=/tmp/php-shim:$PATH npx vp test resources/js/lib/money.test.ts
```

If the Laravel Vite plugin refuses to start because it detects CI, prefix
`LARAVEL_BYPASS_ENV_CHECK=1` for the test run. Do not add CI-only shortcuts to
the config.

## Path map

| Area | Path |
|---|---|
| Pages | `resources/js/pages/**` |
| Layouts | `resources/js/layouts/**` |
| Domain components | `resources/js/components/**` (except `ui`) |
| UI primitives | `resources/js/components/ui/**` |
| Hooks | `resources/js/hooks/**` |
| Utilities | `resources/js/lib/**` |
| Types | `resources/js/types/**` |
| Generated route helpers | `resources/js/routes/**` |
| Generated controller actions | `resources/js/actions/**` |
| Generated Wayfinder internals | `resources/js/wayfinder/**` |
| CSS and tokens | `resources/css/app.css` |
| Controllers | `app/Http/Controllers/**` |
| Routes and middleware | `routes/**`, `app/Http/Middleware/**` |
| Form requests | `app/Http/Requests/**` |
| Backend tests | `tests/Feature/**`, `tests/Unit/**` |
| Frontend tests | colocated `*.test.ts` / `*.test.tsx` |
| Visual spec | `docs/DESIGN.md` |

## Change impact map

Used by `scripts/check-pack.mjs --changed` to scope an audit after code changes.

| Changed path | Re-verify guides |
|---|---|
| `resources/js/pages/**` | 02, 05, 06 |
| `resources/js/components/**` (except `ui`) | 01, 06 |
| `resources/js/components/ui/**` | 06 |
| `resources/js/layouts/**` | 01, 02, 08 |
| `resources/js/app.tsx` | 01, 02, `PROJECT-PROFILE.md` |
| `resources/js/hooks/**` | 01, 03 |
| `resources/js/lib/**` | 01, 09 |
| `resources/js/types/global.d.ts` | 03, `PROJECT-PROFILE.md` |
| `resources/js/types/**` | 03 |
| `resources/css/app.css` | 06, `docs/DESIGN.md` |
| `app/Http/Controllers/**`, `app/Http/Requests/**`, `routes/**` | 03, 04 |
| `tests/**` | 09 |
| `package.json`, `composer.json`, `vite.config.ts` | `PROJECT-PROFILE.md`, 09 |

## Layout registry

`resources/js/app.tsx` owns one `layout: (name) => …` callback. It is the only
place that maps a page name to a shell.

| Page name | Layout |
|---|---|
| `welcome`, `bio-pages/public`, `link-pages/public`, `links/overlay`, `links/unlock`, `qr-codes/unlock`, `content/show` | `null` |
| `auth/*` | `AuthLayout` |
| `admin/*` | `AdminLayout` |
| `settings/*`, `webhooks/*`, `billing/*` | `[UserLayout, SettingsLayout]` |
| Everything else | `UserLayout` |

A page supplies layout props through its static `.layout` object:
`breadcrumbs` for product pages, `title`/`description` for auth pages, and
`setLayoutProps()` at runtime where a page changes its own title (two-factor
challenge). `links/revisions.tsx` replaces the layout for a full-screen diff.
Do not add a second registry.

## Design tokens

`docs/DESIGN.md` owns the token tables. Code uses the CSS variables declared in
`resources/css/app.css`:

`background`, `foreground`, `card`, `card-foreground`, `popover`, `primary`,
`primary-foreground`, `primary-soft`, `primary-soft-foreground`, `secondary`,
`muted`, `muted-foreground`, `accent`, `border`, `input`, `ring`,
`surface-secondary`, `surface-tertiary`, `separator`, `success`,
`success-soft`, `success-soft-foreground`, `warning`, `warning-soft`,
`warning-soft-foreground`, `danger`, `danger-soft`, `danger-soft-foreground`,
`chart-1…5`, `sidebar*`.

Radius: card `1rem`, control `0.5–0.75rem`, chip pill. Type: page title
24/600, section title 20/600 or 16/600, body 14/400, meta 12/500, KPI 24/600.
Never hard-code a hex in a component.

## Shared props and permissions

`app/Http/Middleware/HandleInertiaRequests.php` shares:

| Prop | Shape | Consumer |
|---|---|---|
| `auth` | `{ user, isAdmin, canSearchPlatform, adminAbilities }` | Shell, permissions |
| `platform` | branding, capabilities, captcha | Shell, feature gates |
| `locale` | `{ code, direction }` | Root direction |
| `translations` | dictionary | `useTranslation` |
| `notificationUnreadCount` | number | Header bell |
| `notificationPreview` | list | Header bell |
| `workspace` | current workspace + membership + plan usage | Sidebar, switcher |
| `sidebarOpen` | boolean | Sidebar default |
| `googleAuthEnabled` | boolean | Login |
| `errors` | `Record<string, string>` | Inertia forms |
| `name` | app name | Titles |

They are globally typed in `resources/js/types/global.d.ts`; call `usePage()`
without a generic. UI hiding is not authorization. Backend middleware and
`Gate`/policies decide access; `auth.adminAbilities` and page `can*` props only
decide what to render.

## Route helpers

```ts
// Named import for navigation.
import { index as linksIndex } from '@/routes/links';
<Link href={linksIndex()}>Short links</Link>

// Namespace import for an admin action.
import files from '@/routes/admin/files';
router.get(files.index().url, { q: query }, { preserveScroll: true });

// Controller-derived form definition for <Form>.
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
<Form {...ProfileController.update.form()}>…</Form>

// RouteDefinition works in <Link> and breadcrumbs; use .url for router.*.
const definition = linksIndex({ query: { page: 2 } });
router.visit(definition.url, { preserveScroll: true });
```

Do not use Ziggy or a global `route()`. Product URLs are generated; server-
provided pagination and export URLs are the only allowed raw URLs.

Account settings live under `/account-settings/*`, not `/settings/*`:

| Page | URL |
|---|---|
| Profile | `/account-settings/general` |
| Security | `/account-settings/security` |
| Appearance | `/account-settings/appearance` |
| Billing | `/account-settings/billing` |
| Workspaces | `/account-settings/workspaces` |
| API keys | `/account-settings/api-keys` |
| Webhooks | `/account-settings/webhooks` |
| Restore | `/account-settings/restore` |

Page names still use the `settings/` prefix (`pages/settings/profile.tsx`);
only the URLs use `account-settings`.

## Generated zones

| Path | Status |
|---|---|
| `resources/js/routes/**` | Generated by Wayfinder; disposable, gitignored |
| `resources/js/actions/**` | Generated by Wayfinder; disposable, gitignored |
| `resources/js/wayfinder/**` | Generated internals; never edit |
| `resources/js/components/ui/**` | shadcn-derived; locally themed, lint/fmt excluded, not disposable |

Regenerate routes/actions with the app running or by invoking Wayfinder
through the build. Never patch generated files to fix a bug; fix the Laravel
route or controller and regenerate.

## Data contracts

`PaginatedCollection<T>` and `PaginationLink` live in
`resources/js/types/pagination.ts`. Laravel paginators serialize snake_case
keys (`current_page`, `last_page`, `per_page`, `total`, `links`). Domain types
live in `resources/js/types/*` and use camelCase for nested payloads where the
controller maps them. Dates arrive as ISO strings; format them in the page or a
shared helper, not in the controller. Money uses `formatMoney` from
`resources/js/lib/money.ts`.

## Utilities

| Helper | Import | Use |
|---|---|---|
| `cn` | `@/lib/utils` | Conditional class composition |
| `toUrl` | `@/lib/utils` | Normalize `Link` hrefs |
| `formatMoney` | `@/lib/money` | Currency display |
| `countryName` | `@/lib/countries` | Country labels |
| `useCurrentUrl` | `@/hooks/use-current-url` | `aria-current`, nav state |
| `useClipboard` | `@/hooks/use-clipboard` | Copy buttons |
| `useInitials` | `@/hooks/use-initials` | Avatar fallback |
| `useTranslation` | `@/hooks/use-translation` | Shared translations |
| `useAppearance` | `@/hooks/use-appearance` | Theme state |
| `useIsMobile` | `@/hooks/use-mobile` | Mobile shell behavior |

## Verification prerequisites

- Compose services: `app`, `vite`, `queue`, `scheduler`.
- App at `http://localhost:8000`; Vite at `http://localhost:5173`.
- Seed local demo accounts with `php artisan db:seed --class=DemoAccountSeeder`.
  The password is defined in `database/seeders/DemoAccountSeeder.php`. Never
  copy it into a committed file.
- Grant admin access with `php artisan linkpilot:grant-admin <email>`.
- Email: configure SMTP in the administrator settings console under **Email**;
  unconfigured mail writes to the application log.
- Use the seeded admin or user account for browser proof; log out between
  roles.

## Verifying the pack

Run from the repository root:

```bash
node frontend-instructions/scripts/check-pack.mjs
node frontend-instructions/scripts/check-pack.mjs --changed
```

The first command validates structure, versions, commands, paths, symbols,
recipes, budgets, and the conformance baseline. `--changed` adds guides for
commits since `verified.json.verifiedAt` and all current uncommitted changes.
After a full audit, update `verified.json` with the current timestamp, the pack
hash printed by the checker, and the current conformance counts.

## Adaptation checklist

1. Copy `frontend-instructions/` into the new repository.
2. Rewrite this file: identity, commands, paths, layout registry, shared props,
   route helpers, generated zones, contracts, utilities, verification.
3. Rewrite `PROJECT-SURFACES.md`; if there are no domain surfaces, say so in
   that file.
4. Add the snippet from `AGENTS_FRONTEND_SNIPPET.md` to `AGENTS.md`.
5. Confirm `docs/DESIGN.md` exists and is the visual authority; otherwise
   replace the design references in the guides.
6. Run the verification commands and fix the profile where they differ.
7. Delete examples that name LinkPilot-only files if the new project lacks
   them; keep the rule, change the path.
