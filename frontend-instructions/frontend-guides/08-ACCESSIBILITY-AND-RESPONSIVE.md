# 08 — Accessibility and responsive behavior

Read this when you touch an interactive surface, a form, a dialog, navigation,
or any responsive layout.

## Baseline

- A skip link targets `<main id="main-content">` in the shell.
- One `h1` per page, rendered by `PageHeader` or an allowed exception.
- Product pages have a `<main>` landmark in the layout; `null`-layout pages
  render their own.
- Navigation marks the current page with `aria-current="page"` and a parent
  match with `aria-current="location"`.
- Icon-only controls have `aria-label`; decorative icons have
  `aria-hidden="true"`.
- Visually hidden text uses `sr-only`; do not use `display:none` for content a
  screen reader needs.

## Focus

- Every interactive element has a visible focus ring. Do not use
  `outline-none` without a `focus-visible:ring-*` replacement.
- Dialogs trap focus, set initial focus, and return focus to the trigger.
- A failed form submit focuses `FormErrorSummary` (or the first invalid field
  when there is no summary).
- Do not move focus on every render; only on open, close, or failure.
- The password visibility button is in the tab order. Do not restore the old
  `tabIndex={-1}`.

## Forms

- Every control has a label associated with `htmlFor`/`id`.
- New code sets `aria-invalid` and `aria-describedby` for errors and help.
  Older pages are inconsistent; do not copy the omission.
- Error text is adjacent to the field and also referenced by
  `aria-describedby`.
- Required fields use the native `required` attribute; do not rely on an
  asterisk alone.
- Password, passkey, and 2FA flows keep their labels and error text. The
  password toggle has an accessible name that changes with state.
- Grouped controls use `fieldset`/`legend` or a `FieldSet`/`FieldLegend`.

## Keyboard

- Menus, dialogs, selects, and popovers use Radix behavior; do not reimplement
  arrow keys.
- Segmented controls use roving tabindex or a radiogroup; the selected item is
  reachable and announced.
- Tab lists use `role="tablist"`, `role="tab"`, `aria-selected`, and
  `aria-controls`. The bio editor tabs already do.
- Reordering is keyboard-accessible: provide move-up/move-down actions or
  Alt+Arrow. Drag-only is not acceptable.
- Escape closes overlays; Enter submits the focused form.
- Tab order follows the visual order. Do not add positive `tabIndex` values.

## Touch

- Minimum target: 44 px on mobile (`min-h-11` / `size-11`).
- Bottom nav is 56 px plus safe-area inset.
- Drawer rows currently shrink to 36–40 px between 768 and 1024 px. Do not use
  `max-md:` for drawer row height; use `max-lg:` or a min-height that holds.
- Checkbox hit areas wrap the label; the visual box may be smaller than the
  target.
- Do not rely on hover to reveal an action on touch. Provide a visible trigger.

## Contrast

- Use semantic tokens; they are tuned for both themes.
- On a tinted background use `*-soft-foreground`.
- The destructive `Alert` uses `bg-danger-soft` with
  `text-danger-soft-foreground`; it is readable in light and dark.
- Status is never color-only; add text or an icon with a label.
- Do not use `text-muted-foreground` for essential body copy.

## Motion

- A global reduced-motion rule disables animations. Do not add a JS animation
  that ignores it.
- Charts have `isAnimationActive={false}`.
- Do not implement smooth scrolling without checking
  `prefers-reduced-motion`.
- Hover transitions are limited to color/background; no bouncing or scaling.

## Responsive

| Width | Check                                                                    |
| ----- | ------------------------------------------------------------------------ |
| 1440  | Full sidebar, three-up cards, table columns readable                     |
| 1024  | Admin table/card switch; sidebar persistent; settings rail vertical      |
| 768   | Admin tables still show cards; user lists may switch; drawer rows ≥44 px |
| 390   | Single column, no page-level horizontal scroll, dialog footer reachable  |

Rules:

- `min-w-0` on flex/grid children with text or tables.
- `overflow-x-clip` on the shell content; internal scroll containers are
  allowed for tables.
- Bound popovers to the viewport (`max-h`, `overflow-y-auto`).
- Safe-area padding on the mobile bottom nav.
- Do not hide a primary action on mobile without an equivalent in the page.

## Manual accessibility check

1. Tab through the page: skip link → nav → header actions → content → footer.
2. Confirm the focus ring is visible on every stop.
3. Submit an invalid form; confirm the summary is announced and focused.
4. Open and close a dialog; confirm focus returns to the trigger.
5. At 390 px, confirm no horizontal page scroll and every control is reachable.
6. Zoom to 200%; confirm text does not clip and the layout still works.
7. Check the password toggle, icon-only buttons, and pagination names.

## Known gaps

- The user sidebar is a `<div>`-based shell, not a labelled `<nav>` landmark in
  every state. Add `aria-label` when touching it.
- `aria-describedby` coverage is partial on legacy forms.
- Bio editor and appearance tabs are valid; do not “fix” their `aria-selected`.
- Admin pagination labels were added; older bespoke paginations may still lack
  one.
- The admin settings save bar uses negative margins and can add ~4 px of
  internal overflow at 768 px; page-level scroll is still 0.
- Drawer row height between 768 and 1024 px is below the 44 px target.

## Checklist

- [ ] Skip link, main landmark, one `h1`.
- [ ] Labels and names on every control.
- [ ] Focus visible; dialog focus returns; error summary focused.
- [ ] Keyboard reachable reorder and tabs.
- [ ] 44 px touch targets; password toggle in tab order.
- [ ] Semantic tokens; status not color-only.
- [ ] Reduced motion respected; no chart animation.
- [ ] 390 px no page scroll; 200% zoom works.
