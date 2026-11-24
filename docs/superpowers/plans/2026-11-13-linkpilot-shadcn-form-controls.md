# LinkPilot Shadcn Form Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize User/Admin form controls, make populated data tables opaque, and keep no-data areas transparent.

**Architecture:** Reuse local shadcn `Input`, `Textarea`, Radix `Select`, `Checkbox`, `Field`, and `FieldGroup` components. Give populated table boundaries the existing `bg-card` surface and keep empty-state containers transparent. Preserve layouts, data, and business logic.

**Tech Stack:** React 19, TypeScript, Inertia 3, Radix UI, local shadcn components, Tailwind CSS 4, Laravel.

**Spec:** `docs/superpowers/specs/2026-11-13-linkpilot-shadcn-form-controls-design.md`

## Global Constraints

- No new dependency, registry, design-system preset, or replacement component library.
- No broad page redesign, copy changes, or changes to routes, server validation, authorization, data, or submission behavior.
- Do not convert hidden inputs or unrelated navigation. Apply the requested opaque surface only to populated data tables; keep empty-state containers transparent and preserve existing row geometry and content.
- Do not replace browser-native specialized behavior when shadcn’s existing primitive delegates to it, such as date/time and file pickers.
- Preserve current values, option labels, submit names, Inertia state updates, and form semantics.

## Review Focus

1. **Blank/all options in named selects:** GET filters and optional profile fields must keep their current empty-value semantics. Task 1 verifies submitted form data and resulting filters.
2. **Numeric option values:** link-page variant and member IDs must still reach existing callbacks as numbers where required. Task 1 verifies the selected IDs and resulting state.
3. **Conditional option sets:** tracking-pixel targets still constrain trigger choices; Task 1 changes the target and checks the available triggers.
4. **Disabled and invalid controls:** existing disabled/error states remain visible and prevent interaction; Task 1 checks a disabled select and an invalid field in the browser.
5. **Checkbox values and row selection:** the profile `remove_avatar=1` field appears only when checked, Admin locale values remain booleans, and link/folder select-all matches row selection; Tasks 1 and 2 verify state and serialization.

---

### Task 1: Migrate User controls to the local shadcn components

**Files:**

- Modify: `resources/js/components/bio-pages/editor.tsx`
- Modify: `resources/js/components/link-pages/block-form.tsx`
- Modify: `resources/js/components/link-pages/lead-form.tsx`
- Modify: `resources/js/components/link-pages/variant-manager.tsx`
- Modify: `resources/js/components/qr-codes/qr-code-form-dialog.tsx`
- Modify: `resources/js/components/resource-list/sort-menu.tsx`
- Modify: `resources/js/components/two-factor-setup-modal.tsx`
- Modify: `resources/js/components/ui/checkbox.tsx`
- Modify: `resources/js/pages/folders/index.tsx`
- Modify: `resources/js/pages/links/index.tsx`
- Modify: `resources/js/pages/link-pages/edit.tsx`
- Modify: `resources/js/pages/link-pages/insights.tsx`
- Modify: `resources/js/pages/settings/profile.tsx`
- Modify: `resources/js/pages/settings/workspaces/index.tsx`
- Modify: `resources/js/pages/tracking-pixels/index.tsx`

**Interfaces:**

- Consumes: `@/components/ui/{input,textarea,select,checkbox,field}` as currently implemented.
- Produces: User forms and form-like filters use the same local primitives with their existing values and callbacks.

- [ ] Replace each native single-choice `<select>` with `Select`, `SelectTrigger`, `SelectValue`, `SelectContent`, `SelectGroup`, and `SelectItem`. Keep option labels/order, ids, accessible names, controlled/default values, disabled state, and callbacks. Convert string values to numbers only at current numeric boundaries.
- [ ] Preserve form names and blank/all-option behavior. Use Radix Select’s form support; verify `FormData` and GET query values. If an explicitly blank option is not serialized identically, use a hidden input as the sole submitted value for that field rather than an empty `SelectItem`.
- [ ] Replace visible native checkboxes with `Checkbox`, mapping `onCheckedChange` to the existing boolean state. Keep hidden inputs native.
- [ ] Give the existing Checkbox primitive an opaque `bg-card` base when unchecked; retain its current checked, invalid, disabled, and focus styling.
- [ ] Replace the raw textarea and plain/date-time/file/read-only text inputs with `Textarea` or `Input` while retaining browser picker behavior and existing props.
- [ ] Use `Field`/`FieldGroup` for the labels, errors, and field groups being migrated; retain existing responsive layouts.
- [ ] Browser-check `/dashboard/links` and `/dashboard/folders`: keyboard-open the custom menus, choose filters, apply, and confirm query values and selected-all/row-selection behavior.
- [ ] Browser-check `/account-settings/general` and `/account-settings/workspaces`, `/dashboard/link-pages/{linkPage}`, `/dashboard/link-pages/{linkPage}/insights`, and `/dashboard/pixels`: custom menus show the correct selection; optional values, numeric IDs, disabled/error states, and target-dependent tracking triggers remain correct. Confirm the profile checkbox’s form value without saving destructive changes.

### Task 2: Migrate the remaining Admin checkboxes

**Files:**

- Modify: `resources/js/pages/admin/localizations/index.tsx`

**Interfaces:**

- Consumes: local shadcn `Checkbox` and `Field` components.
- Produces: Locale `Published` and `Default locale` controls retain their boolean values and submit behavior.

- [ ] Replace both native checkbox inputs with controlled `Checkbox` components and preserve their current `useForm` boolean updates.
- [ ] Pair each checkbox with an accessible `Field`/`FieldLabel` without changing the form’s field order or submit behavior.
- [ ] Browser-check `/admin/localizations`: both checkboxes toggle with pointer and keyboard, and the form reports the expected boolean values before submission.

### Task 3: Make populated data tables opaque and no-data areas transparent

**Files:**

- Inspect/modify all User/Admin table surfaces returned by `grep "<table\\b"` in `resources/js/pages` and `resources/js/components`, including the table view in `resources/js/pages/links/index.tsx`.
- Inspect/modify User/Admin empty-state surfaces in `resources/js/pages` and `resources/js/components`.

**Interfaces:**

- Consumes: existing `bg-card`, `bg-muted`, and transparent empty-state styling.
- Produces: populated tables have an opaque card surface; empty-state containers have no opaque fill.

- [ ] Audit every rendered table and table-style links list. Add `bg-card` at the narrowest table boundary that covers the header and body; do not duplicate an already-opaque parent surface.
- [ ] Keep table headers distinct with the existing muted surface token and preserve row selection, hover, focus, responsive layouts, and empty-state branches.
- [ ] Remove non-transparent fills from no-data containers, including explicit `bg-muted/*` on `Empty`; retain icon-only muted surfaces.

### Task 4: Final verification and remaining-native control audit

**Files:**

- Inspect: `resources/js/components/workspaces/workspace-switcher.tsx`
- Inspect: `resources/css/app.css`

**Interfaces:**

- Consumes: User/Admin form controls, populated table surfaces, and transparent no-data areas.
- Produces: shadcn controls cover in-scope forms/filters; populated tables are opaque; empty states are transparent; shell workspace switcher remains native.

- [ ] Confirm no raw `<select>` remains in forms or filters. Keep the native workspace switcher because it is shell navigation outside this form-focused scope, and retain the existing global select rules it uses.
- [ ] Run `docker compose exec -T app npm run types:check` and `docker compose exec -T app npm run build` once after all implementation tasks.
- [ ] Inspect actual User and Admin forms/tables in a browser in light and dark themes; confirm custom select popups, opaque populated tables and controls, and transparent empty states.

## Verification Notes

The repository has no frontend test script; `package.json` exposes `check`, `types:check`, and `build`. The native-select appearance in the supplied screenshots is the baseline regression. Use the browser checks above for the client-side interaction and form serialization rather than adding a test dependency. If authentication blocks a route, report the exact route and failed access attempt; do not claim visual verification for an inaccessible surface.
