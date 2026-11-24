# 07 — Feedback, states, and overlays

Read this when you add a success/failure message, dialog, confirmation,
loading state, or empty state.

## Feedback decision table

| Situation | Channel |
|---|---|
| Validation failed | Inline `FieldError`; `FormErrorSummary` for large forms |
| Mutation changes the visible surface | Close/reset silently; the list or card updates |
| Mutation does not change the visible surface | Server flash toast |
| Client-only copy/export | Sonner `toast.*` |
| Background failure with no field | `Alert` on the page |
| Destructive action | `AlertDialog` (simple) or `Dialog` (needs a form) |

Do not stack a toast on top of a visible list update. Do not toast validation.

## Toasts

Server:

```php
Inertia::flash('toast', [
    'type' => 'success',
    'message' => __('Profile updated.'),
]);
```

Client:

```ts
import { toast } from 'sonner';

toast.success('Copied to clipboard');
toast.error('Could not copy the link.');
```

`components/ui/sonner.tsx` mounts `Toaster` and `useFlashToast`; do not mount a
second `<Toaster>` or call `useFlashToast` in a page. Types are `success`,
`error`, `info`, `warning`. Copy is one sentence, no exclamation mark, no emoji.

## Dialogs

Choose the overlay:

| Need | Overlay |
|---|---|
| Focused create/edit with fields | `Dialog` |
| Simple yes/no destructive confirm | `AlertDialog` |
| Confirmation that needs a password or a form | `Dialog` |
| Nested settings | `SettingsPanelDialog` (dialog above the base dialog) |
| Mobile-only navigation | `Sheet` |

Anatomy:

- `DialogContent` with `role="dialog"`, `aria-labelledby` from `DialogTitle`,
  and `aria-describedby` from `DialogDescription` (use `sr-only` if the visual
  description is redundant).
- Fixed header, one scrollable body, fixed footer.
- Close button always present; title gets `tabIndex={-1}` for focus on mobile.
- Focus is trapped while open and returns to the trigger on close.
- Sizes: link form `sm:max-w-[40rem]`, QR `sm:max-w-[52rem]`, simple confirm
  default.

Do not use `window.confirm`, `window.alert`, or a page navigation for a
confirmation that can be a dialog.

## Nested settings panels

`SettingsPanelDialog` renders a child dialog above the base form. The child
owns one settings group; the base form stays mounted. On close, focus returns
to the trigger, not the document body. Do not nest more than one level.

## Destructive confirmation

Simple:

```tsx
<AlertDialog>
    <AlertDialogTrigger asChild>
        <Button variant="destructive">Delete</Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Delete this link?</AlertDialogTitle>
            <AlertDialogDescription>
                This removes the link and its analytics. This cannot be undone.
            </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={destroy}>Delete</AlertDialogAction>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```

With a form/password: use `Dialog` and `Form`, disable the destructive button
while `processing`, keep the dialog open on error, and focus the password field.
The button label repeats the action (“Schedule deletion”), not “OK”.

## Loading

- Navigation: Inertia progress bar; do not add a page-level spinner.
- Button action: disable + `Spinner` for actions over ~300 ms.
- Table reload: keep the current rows; Inertia swaps them when ready.
- No skeleton screens and no layout shift. Reserve the height of a chart or
  card body while loading.
- `processing` from `useForm`/`Form` is the source of truth; do not keep a
  second boolean.

## Empty states

Use `Empty`:

```tsx
<Empty>
    <EmptyHeader>
        <EmptyTitle>No QR campaigns yet</EmptyTitle>
        <EmptyDescription>
            Create a campaign to start tracking scans.
        </EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
        <Button>Create campaign</Button>
    </EmptyContent>
</Empty>
```

- One sentence, one action, sentence case.
- Distinguish “no data” from “no matches”: the filtered state offers clear
  filters.
- No demo rows and no fake totals.
- Charts use an empty block, not an empty axis.

## Errors

- Field error → `FieldError`.
- Form-wide error → `FormErrorSummary` (`role="alert"`, focusable).
- Page-section error → `Alert` with a recovery action.
- Keep the rest of the page usable; do not replace the whole page with an error.
- Never show a raw exception message, stack trace, or provider payload.
- There is no custom Inertia error page yet. A failed visit renders the
  framework fallback; do not invent a client error boundary as a substitute.

## Status display

- Use `Badge` variants: `success`, `warning`, `destructive`, `secondary`,
  `outline`.
- A status dot is paired with text; never color alone.
- Counts and states name their denominator (“3 of 12 workspaces”).
- Independent flags are not summed into one headline.

## Copy and tone

- Sentence case; verb-first buttons (“Create short link”, “Save changes”).
- One primary action per surface.
- Icons are optional: add one when it clarifies the action; never decorate
  every label, KPI, or section heading.
- No exclamation marks, emoji, or marketing adjectives.
- Empty states explain the next action in one sentence.
- Errors say what to fix, not “Invalid input”.
- Numbers use the page’s unit and locale formatting.

## Known debt

- Existing `window.confirm` call sites remain in
  `resources/js/components/bio-pages/editor.tsx`,
  `resources/js/components/link-pages/variant-manager.tsx`,
  `resources/js/pages/admin/settings/section.tsx`,
  `resources/js/pages/settings/api-keys/index.tsx`, and
  `resources/js/pages/settings/workspaces/index.tsx`. The current count is
  recorded in `verified.json`. Replace them with `AlertDialog` or a shared
  confirm-dialog primitive when touched; do not add another call site.

## Checklist

- [ ] Feedback channel matches the decision table.
- [ ] No toast for validation; no duplicate success message.
- [ ] Dialog has a label, description, fixed footer, focus return.
- [ ] Destructive action confirms; button label repeats the action.
- [ ] Loading state uses `processing`; no skeleton, no layout shift.
- [ ] Empty vs filtered-empty copy is correct.
- [ ] Status is not color-only.
