# 04 — Forms and validation

Read this when you build any form, dialog form, settings section, or validation
error surface.

## Choose the API

| Surface | API | Why |
|---|---|---|
| Auth, settings, page-level form | `<Form {...Action.form()}>` | Uncontrolled, Wayfinder-native, reset helpers |
| Dialog, editor, multi-step controlled form | `useForm<T>()` | Controlled data, `setData`, `transform`, partial submit |
| One-shot action with no fields | `router.post/patch/delete` | No form state needed |

Never mix both APIs in one form. Never add Formik, React Hook Form, Zod, or a
client schema.

## Page form recipe

```tsx
import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

<Form
    {...ProfileController.update.form()}
    options={{ preserveScroll: true }}
    className="space-y-6"
>
    {({ processing, errors }) => (
        <>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="name">Name</FieldLabel>
                <Input
                    id="name"
                    name="name"
                    defaultValue={auth.user?.name ?? ''}
                    required
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? 'name-error' : undefined}
                />
                {errors.name && (
                    <FieldError id="name-error">{errors.name}</FieldError>
                )}
            </Field>
            <Button disabled={processing} data-test="update-profile-button">
                {processing && <Spinner />}
                Save
            </Button>
        </>
    )}
</Form>;
```

- `options={{ preserveScroll: true }}` unless scrolling to the top is intended.
- `resetOnSuccess` for create forms and secret fields; omit it when the saved
  values should stay visible.
- Disable the submit button with `processing`; add `Spinner` for slow actions.
- `disableWhileProcessing` is acceptable on auth forms with multiple controls.

## Dialog form recipe

```tsx
import { useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { store } from '@/routes/folders';

const form = useForm({ name: '' });
const [open, setOpen] = useState(false);
const summaryRef = useRef<HTMLDivElement>(null);
const [focusErrors, setFocusErrors] = useState(false);
useEffect(() => {
    if (focusErrors && summaryRef.current) {
        summaryRef.current.focus();
        setFocusErrors(false);
    }
}, [focusErrors, form.errors]);

function submit(event: FormEvent) {
    event.preventDefault();
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            setOpen(false);
        },
        onError: () => setFocusErrors(true),
    });
}

<Dialog open={open} onOpenChange={(next) => {
    setOpen(next);
    if (next) {
        form.clearErrors();
        form.reset();
    }
}}>
    <DialogContent className="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-[40rem]">
        <DialogHeader className="shrink-0 px-6 pt-6 pr-14 pb-4">
            <DialogTitle id="form-title" tabIndex={-1}>Create folder</DialogTitle>
            <DialogDescription className="sr-only">
                Set the required fields, then save.
            </DialogDescription>
        </DialogHeader>
        <FormErrorSummary summaryRef={summaryRef} titleId="form-errors" entries={entries} onSelect={focusField} />
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-6 py-1">
                {/* fields */}
            </div>
            <DialogFooter className="shrink-0 border-t px-6 py-4">
                <Button type="submit" disabled={form.processing}>Save</Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>;
```

- Fixed header, one scrollable body, fixed footer.
- Reset/clear errors on open, not on every render.
- Close and reset only on success. Keep values on failure.
- On mobile, focus the title when the dialog opens. After a failed submit,
  focus the summary, or the first invalid field when there is no summary.

## Field anatomy

```tsx
<Field data-invalid={Boolean(errors.email)}>
    <FieldLabel htmlFor="email">Email</FieldLabel>
    <Input id="email" aria-invalid={Boolean(errors.email)}
        aria-describedby={errors.email ? 'email-help email-error' : 'email-help'} />
    <FieldDescription id="email-help">We only use this for sign-in.</FieldDescription>
    {errors.email && <FieldError id="email-error">{errors.email}</FieldError>}
</Field>
```

- One label per control; `htmlFor` matches `id`.
- `Field` provides `gap-2`; do not add a wrapper grid for a single field.
- Use `Input`, `Textarea`, `Select`, `Checkbox`, `Switch` from `components/ui`.
- For new code add `aria-invalid` and `aria-describedby`; older pages are
  inconsistent, but do not copy the omission.
- `FieldError` renders `text-destructive text-sm`. Do not hand-roll a
  `<p className="text-destructive">`.

## Validation

- The server `FormRequest` is the only schema. It owns required fields, formats,
  unique rules, authorization, and translated messages.
- Validation redirects back with session errors shared through Inertia; values
  are preserved. Do not duplicate rules in `onSubmit`.
- Client-side checks are limited to immediate affordances: disabling submit for
  an obviously empty required field, or a password confirmation mismatch.
- Never trust a client check for security or data integrity.

## Error presentation

- Inline `FieldError` for field errors.
- `FormErrorSummary` when the form has more than five fields or multiple
  panels. It is `role="alert"`, focusable, and its entries focus the owning
  field or open the owning panel.
- Validation errors are never toasts.
- Focus the summary after a failed submit; keep other page sections usable.
- Clicking a summary entry must open the panel that contains the field.

## Submission feedback

- Disable the submit control while `processing`.
- Show a spinner for actions over ~300 ms.
- Close/reset on success when the result is visible in the page.
- Use a server flash toast only when the UI does not visibly change (for
  example, a background copy or a redirect). See guide 07.
- Do not use `recentlySuccessful` copy.

## Transform

Use `transform` only when the payload differs from the form state:

```tsx
form.transform((data) => ({
    ...data,
    expires_at: data.expires_at || null,
    _method: 'PATCH',
}));
```

Keep the transform next to the submit handler. Do not create a shared
transform layer for one form. Empty strings become `null` only when the server
expects null, not as a blanket rule.

## Field types

| Control | Primitive | Notes |
|---|---|---|
| Text/email/password/URL | `Input` | `PasswordInput` adds the visibility toggle |
| Long text | `Textarea` | Set `rows`; do not auto-grow without a reason |
| Select | `Select` | Add a hidden input when the form posts natively |
| Checkbox | `Checkbox` | Label wraps or `htmlFor` matches |
| Switch | `Switch` | Pair with a label and a description |
| Combobox/tag picker | `Popover` + `Command` | Keep the selected chips outside the popover |
| File | `Input type="file"` | Show type/size limits in `FieldDescription` |
| OTP | `InputOTP` | One group, `REGEXP_ONLY_DIGITS` where numeric |

## Modal form layout

- `sm:max-w-[40rem]` for link/tag forms; `52rem` for QR; `SettingsPanelDialog`
  for nested settings panels.
- Header includes a title and an `sr-only` description.
- Body is the only scroll region.
- Footer is fixed and stacks to a two-column grid on mobile when there are two
  actions.
- Close button is always present and labelled.
- Focus returns to the trigger on close.

## Settings forms

- Use the admin settings schema (`settings-form.ts`) and `section.tsx`; do not
  build a new settings page shell.
- Sticky save bar at the bottom; show “All changes saved” / “Unsaved changes”
  with `aria-live="polite"`.
- Secret fields show a masked placeholder and a clear action, never the stored
  value.
- A version conflict keeps the draft and explains the reload path.
- Uploads show progress and a removable preview.

## Anti-patterns

| Anti-pattern | Replacement |
|---|---|
| Raw `<input>` without `Field` | `Field` + primitive |
| `<p className="text-destructive">` | `FieldError` |
| `window.confirm` | `AlertDialog` or `Dialog` |
| Zod/RHF/Formik schema | Server `FormRequest` |
| Duplicate validation in `onSubmit` | Remove it |
| Toast for validation | Inline `FieldError` / summary |
| Success toast for a visible list update | Close/reset silently |
| One giant form for unrelated panels | Panels with one submit per panel and a summary |

## Checklist

- [ ] API choice matches the surface.
- [ ] Labels, `htmlFor`, `aria-invalid`, `aria-describedby` present.
- [ ] Errors inline; summary for large forms; focus after failure.
- [ ] Submit disabled while processing; close/reset on success only.
- [ ] Transform (if any) is local and minimal.
- [ ] Dialog header/body/footer fixed; footer reachable at 390 px.
- [ ] No client schema or toast validation.
