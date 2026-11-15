# LinkPilot User/Admin Forms and Data Surfaces Design

## Status

Approved for implementation; populated table and empty-state surface requirements remain part of the original request.

## Intent

Give User/Admin form controls a consistent, opaque LinkPilot appearance, use custom shadcn dropdowns instead of browser-native select menus, make populated data tables opaque, and keep no-data areas transparent. Preserve existing page structure, responsive behavior, validation, submissions, and business logic.

## Audit and direction

- **Product:** LinkPilot helps people and teams create, organize, and monitor short links and related pages.
- **Character:** precise, calm, practical.
- **World:** a routing console, with neutral surfaces and LinkPilot’s existing blue accent.
- **Moments:** route-to-destination rows and existing traffic summaries; forms remain quiet and legible.
- **Signature:** the route slug paired with its live or inactive status.

Current code already uses local shadcn `Input`, `Textarea`, Radix `Select`, `Checkbox`, and `Field` components. Most Admin forms use them. Remaining native controls are concentrated in User settings, workspace membership, link/folder controls, link-page and tracking-pixel forms, and a small number of Admin checkboxes. Inputs, textareas, and select triggers already use the opaque `bg-card` surface; the unchecked Checkbox primitive is the exception, so its base surface should use the same token. Populated tables should use `bg-card` at their narrowest shared boundary; empty-state containers must not use opaque or muted fills.

## Goals

- Use the existing shadcn primitives for visible inputs, textareas, single-choice selects, and checkboxes throughout authenticated User and Admin routes, including filters and inline forms.
- Use `FieldGroup` and `Field` for form-field grouping, with `FieldLabel`, descriptions, and validation states where applicable.
- Ensure dropdowns use the existing Radix-backed shadcn `Select`, not native `<select>` menus.
- Keep form controls opaque using existing semantic surface tokens; preserve accessible labels, focus states, disabled states, and validation feedback.
- Give populated User/Admin tables an opaque `bg-card` surface while preserving table hierarchy and responsive behavior.
- Keep no-data empty-state containers transparent; small icon surfaces inside empty states may retain their existing muted fill.
- Preserve current values, option labels, submit names, Inertia state updates, and form semantics.

## Non-goals and anti-goals

- No new dependency, registry, design-system preset, or replacement component library.
- No broad page redesign or changes to copy, routes, server validation, authorization, data, submission behavior, or table row geometry. The requested populated-table and transparent empty-state surface changes are in scope.
- Do not replace browser-native specialized behavior when shadcn’s existing primitive delegates to it, such as date/time and file pickers.

## Approaches considered

1. Global CSS for native controls: less code, but select menus remain native and component usage stays inconsistent.
2. Migrate User and Admin controls to the existing local shadcn components: selected. It meets the visual and consistency goals without duplicating the installed component system.
3. Reinstall or replace the component library: unnecessary and higher risk because the required primitives already exist.

## Constraints and implementation calls

- Existing `Select` is Radix-based and supports controlled/uncontrolled values. Select item values cannot be empty; placeholder/“all” choices must preserve the current submitted value semantics without using an empty `SelectItem`.
- Numeric select values must be converted at the boundary where current code expects numbers.
- Checkbox updates must remain boolean, including when adapting Radix checkbox state.
- `Input` remains the styled wrapper for native input types; hidden inputs remain native.
- The unchecked shared Checkbox uses the opaque card surface; its checked state remains the existing primary accent.
- Form geometry and option ordering stay as they are unless a minimal adjustment is needed to fit the shared component API.
- Populated tables use the existing `bg-card` token at the narrowest boundary that covers their header and body; no-data branches remain transparent.

## Verification

Build the frontend and exercise representative User and Admin forms and table views in-browser. Confirm custom select rendering, keyboard selection, field errors/disabled states, preserved form values, opaque populated tables, and transparent no-data areas.
