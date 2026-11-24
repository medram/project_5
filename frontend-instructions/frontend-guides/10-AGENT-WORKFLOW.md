# 10 — Agent workflow

Read this before starting any frontend task. It is the operating loop, not a
style guide.

## Read order

1. `frontend-instructions/FRONTEND_INSTRUCTIONS.md`.
2. `PROJECT-PROFILE.md` for commands, paths, and shared props.
3. The task guide from the router (02–09 as applicable).
4. `docs/DESIGN.md` §2–§9 for the visual contract.
5. The owning page/component, its controller, its types, and its tests.

Do not open a random similar page and copy it. Read the owner.

## Seven-step workflow

1. **Understand** — restate the behavior, the affected surface, and the
   non-goals. Identify the authorization and data source.
2. **Locate the owner** — page, component, hook, or primitive. If the task
   spans two owners, split it.
3. **Inventory reuse** — search for an existing primitive, dialog, list, chart,
   or empty state before adding one.
4. **Choose the pattern** — use the decision tables in guides 02–07. State the
   choice when two are viable.
5. **Implement in dependency order** — types → server contract (only if
   required) → component → page → registry. Keep intermediate states buildable.
6. **Verify** — guide 09: types, focused lint, tests, build, browser proof.
7. **Report** — behavior, files, checks actually run, known limitations.

## Implementation order by task

**New page**

1. Route + controller (existing pattern).
2. Page file with `<Head>`, wrapper, `PageHeader`.
3. Domain components and empty state.
4. Layout registry check.
5. Type check + browser proof.

**New list**

1. Server paginator and filters.
2. Page header + KPI row (if any).
3. Toolbar, chips, table/cards, pagination.
4. Selection and bulk actions (if any).
5. Empty vs filtered-empty states.

**New form**

1. `FormRequest` and route.
2. API choice (`<Form>` vs `useForm`).
3. Fields with `Field`/`FieldLabel`/`FieldError`.
4. Summary for large forms; focus handling.
5. Submit/reset/close behavior.

**New dialog**

1. Decide `Dialog` vs `AlertDialog` vs `Sheet`.
2. Header/body/footer layout.
3. Form state and reset-on-open.
4. Focus title on mobile; return focus on close.
5. 390 px footer proof.

**New chart**

1. Confirm the metric and denominator.
2. Use `ChartContainer` + chart ramp.
3. One series; segmented control for exclusive metrics.
4. Empty state and `role="img"` label.
5. No animation; no second library.

**New settings section**

1. Add the schema to `settings-form.ts`.
2. Add the group to the section service/contract.
3. Render through `section.tsx`; no new page shell.
4. Dirty tracking, sticky save bar, version conflict.
5. Backend test for validation/persistence.

**New admin page**

1. Route under the admin prefix with `platform:*` middleware.
2. Add the sidebar item in
   `resources/js/components/admin/admin-sidebar.tsx` and gate it with the
   matching `auth.adminAbilities` capability.
3. Page header, KPI row, filters, table/cards, pagination.
4. Empty state, labels, responsive proof.

## Reuse search

Run these before creating anything:

```bash
rg "PageHeader" resources/js
rg "FieldError|FormErrorSummary" resources/js
rg "StatCard" resources/js
rg "EmptyTitle|EmptyDescription" resources/js
rg "ResourceList" resources/js/components/resource-list
rg "<Dialog|<AlertDialog|SettingsPanelDialog" resources/js/components
rg "useForm<|useForm\(" resources/js/pages resources/js/components
rg "router\.(get|post|patch|delete)" resources/js/pages
rg "from '@/routes" resources/js/pages | head
```

Search behavior, not names: `find` for “settings save bar”, “pagination
footer”, “country map”. Use `grep` for exact symbols.

## Scope control

- No redesign while changing behavior. Fix the pattern you touched, not the
  page’s visual language.
- An explicit unslop/polish request is a visual change, not a feature change:
  read the `unslop` skill, keep features and content order, and still obey
  `docs/DESIGN.md` for tokens and composition.
- No new library. If a primitive is missing, compose Radix/shadcn first.
- No backend contract change without coordinating the controller, request,
  types, and tests.
- No worktrees. Work in the current checkout; other agents share the tree.
- No repo-wide formatter. `npx vp check --fix <paths>` only.
- Respect other agents’ uncommitted edits: re-read a file immediately before
  editing and preserve unrelated hunks.
- Do not delete dead layouts/components in a feature change; document them as
  “do not copy”.

## File ownership

- One owner per file per task. If two tasks need the same shared primitive,
  sequence them or assign one owner.
- Read the current working-tree version, not the last commit.
- Before handoff, `git diff -- <paths>` to confirm only your hunks.
- Never commit credentials, generated files, or another agent’s scratch work.

## Definition of done

- Behavior works end to end, including error and empty states.
- Responsive at 1440/1024/768/390; no page-level horizontal scroll.
- Keyboard and screen-reader names verified; focus behavior correct.
- `types:check`, focused `vp check`, relevant tests, build when needed.
- Docs/profile updated if a shared pattern or command moved.
- Report contains observed results, not assumptions.

## Completion report template

```md
## What changed

- <behavior> in <surface>

## Files

- <path> — <why>

## Checks

- `npm run types:check` → exit 0
- `npx vp check --no-fmt <paths>` → pass
- `npx vp test <paths>` → N passed
- Browser: <pages> at <widths>; console: <none/…>

## Known limitations

- <unverified surface or follow-up>
```

## Common failure modes

| Failure                     | Prevention                                   |
| --------------------------- | -------------------------------------------- |
| Raw palette for status      | Semantic tokens; grep `text-(green           | red | blue)-` |
| Raw product URL             | Wayfinder; grep `'/dashboard/`               |
| Duplicate page title        | One `PageHeader`; no eyebrow `<h1>`          |
| Client validation schema    | Server `FormRequest` only                    |
| Toast for validation        | Inline `FieldError`                          |
| Table overflows page at 390 | Cards below `md`; `min-w-0`; internal scroll |
| Missing accessible name     | `aria-label` on icon-only control            |
| Editing generated files     | Fix the Laravel route, regenerate            |
| Repo-wide reformat          | Scope `vp check --fix` to changed paths      |

## Checklist

- [ ] Read order followed; owner identified.
- [ ] Reuse searched before creation.
- [ ] Dependency order respected; buildable at each step.
- [ ] Scope limited to the request; no drive-by redesign.
- [ ] Verification run and reported with evidence.
