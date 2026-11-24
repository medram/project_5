# 09 — Testing and verification

Read this when you finish any change. Evidence before claims.

## What exists

- Vitest 4 via `vite-plus/test`; tests are colocated `*.test.ts` / `*.test.tsx`.
- Tests are colocated next to their subject. Current examples:
  `resources/js/lib/money.test.ts`,
  `resources/js/pages/admin/settings/settings-form.test.ts`,
  `resources/js/pages/admin/settings/section.test.tsx`. Discover the current
  set with `find resources/js -name '*.test.ts' -o -name '*.test.tsx'`.
- Component tests use `renderToStaticMarkup` inside the Inertia `<App>`; there
  is no jsdom, no React Testing Library, and no Playwright.
- Backend contracts use PHPUnit under `tests/Feature` and `tests/Unit`.
- `npx vp test` loads `vite.config.ts` and runs Wayfinder generation, so it
  needs a working `php` on `PATH`. On a host without PHP, use the app container
  or a `php` shim that runs `docker compose … exec -T app php`.
- The repo also contains `.superpowers/sdd/**` scratch suites that import
  `bun:test`. A bare `npx vp test` discovers them and fails; pass explicit
  paths for the repository tests.

## What to test

Test:

- Pure utilities and schema transforms (`money`, settings values/diff).
- Server payload shape, permissions, range validation, and zero-fill behavior.
- A component branch that is expensive to verify manually, using the SSR
  string pattern.

Do not test:

- CSS classes, Tailwind output, or exact markup snapshots.
- Wiring that the framework already covers.
- Tautologies (`expect(true)`), bare “does not throw”, or source text.

A bug fix gets a regression test only when no existing test covers the path.
A one-off feature is proven with a throwaway script or browser scenario.

## Commands

| Purpose             | Command                                                                  | Prerequisite                      |
| ------------------- | ------------------------------------------------------------------------ | --------------------------------- |
| Type check          | `npm run types:check`                                                    | `node_modules`                    |
| Focused lint/format | `npx vp check --no-fmt <changed paths>`                                  | `node_modules`                    |
| Fix formatting      | `npx vp check --fix <changed paths>`                                     | Only your files                   |
| Frontend tests      | `npx vp test <paths>`                                                    | PHP on `PATH` (see below)         |
| Production build    | `npm run build`                                                          | PHP on `PATH` (see below)         |
| Backend test        | `docker compose -f compose.dev.yaml exec -T app php artisan test <path>` | Compose stack                     |
| Full CI             | `composer ci:check`                                                      | Full stack; runs repo-wide checks |

`npm run types:check` is mandatory; `vp check` does not replace it. `vp test`
and `vp build` load `vite.config.ts`, whose Wayfinder plugin runs
`php artisan wayfinder:generate`. When the host has no PHP, run them through
the app container or a shim:

```bash
docker compose -f compose.dev.yaml exec -T app npm run build
docker compose -f compose.dev.yaml exec -T app npx vp test resources/js/lib/money.test.ts

PATH=/tmp/php-shim:$PATH npx vp test resources/js/lib/money.test.ts
```

If the Laravel Vite plugin refuses to start because it detects CI, prefix
`LARAVEL_BYPASS_ENV_CHECK=1` for the test run.

## Focused checks

- Lint only the files you changed. Other agents may have unformatted work in the
  tree; never run a repo-wide formatter.
- Generated dirs are excluded: `resources/js/routes`, `actions`, `wayfinder`,
  and `components/ui/*`.
- Run the smallest test file that covers the change. When the change touches
  settings or shared utilities, run the tests for those areas.
- Run `npm run build` when you changed routing, Wayfinder usage, or the CSS
  entry.
- Backend changes require the relevant PHPUnit file, not just a type check.

## Manual proof

1. Confirm the stack is running: app `:8000`, Vite `:5173`, queue,
   scheduler.
2. Log in with a seeded account from `database/seeders/DemoAccountSeeder.php`.
   Grant admin with `php artisan linkpilot:grant-admin <email>`; never commit
   credentials.
3. Check 1440, 1024, 768, and 390 px.
4. Watch the browser console on each page; report every error.
5. Exercise the interaction: filter applies, range changes the URL, metric
   switch changes the total, dialog submits, validation focuses the summary.
6. Confirm table → cards below the page breakpoint (`lg` admin, `md` user
   lists) and no page-level horizontal scroll at 390 px.
7. For dialogs, confirm the footer is reachable at 390 px.

## Surface-specific proof

| Surface          | Proof                                                                   |
| ---------------- | ----------------------------------------------------------------------- |
| Page header      | One `h1`, title/description/actions, no duplicate title                 |
| List             | Filters, chips, pagination label, empty state, table→cards              |
| Form             | Invalid submit preserves values, inline error, focus, no toast          |
| Dialog           | Open/close, focus trap/return, fixed footer at 390 px                   |
| Settings section | Rail/chooser, sticky save bar, `h2`/`h3`, version conflict              |
| Insights         | One window control, KPI row, one main series, map, donuts, privacy note |
| Auth             | Layout title/description, no `PageHeader`, password toggle in tab order |

## Reporting

State exactly what you ran and what you observed:

```text
Checks:
- npm run types:check → exit 0
- npx vp check --no-fmt <paths> → pass, 42 files
- npx vp test <3 paths> → 3 files, 17 tests passed
- npm run build → built in 14.2s
- Browser: /admin/users at 1440/1024/768/390, no console errors, no page scroll

Not verified:
- /admin/content pagination nav has no runtime instance (single page); markup
  label verified by source.
```

Never claim a check you did not run. If a command is unavailable, report the
exact error and the alternative you used.

## Known limits

- No browser test runner and no DOM environment; interactive behavior is proven
  manually or with a throwaway script.
- SSR string tests cannot verify focus, hover, or responsive CSS.
- The working tree commonly contains other agents’ untracked tests and
  unformatted files; scope commands to your paths.
- `vp check` and `vp test` both load the Laravel Vite plugin, which invokes
  `php artisan`. A broken PHP path is an environment failure, not a code
  failure.

## Checklist

- [ ] `npm run types:check` exits 0.
- [ ] Focused `vp check --no-fmt` exits 0.
- [ ] The relevant test files pass; new utility edge cases covered.
- [ ] Build passes when routing/CSS changed.
- [ ] Browser proof at four widths with console checked.
- [ ] Report lists commands and observed results, including gaps.
