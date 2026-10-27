import { useForm } from '@inertiajs/react';
import { type FormEvent, useId, useRef, useState } from 'react';
import { usePublicTrackingEvent } from '@/components/analytics/public-tracking-pixels';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { LinkPageFormField } from '@/types/link-pages';

type LeadFormData = {
    lead_consent: boolean;
    values: Record<string, string>;
};

type Props = {
    fields: LinkPageFormField[];
    submitUrl?: string;
    privacyUrl: string | null;
    preview?: boolean;
};

export function LeadForm({
    fields,
    submitUrl,
    privacyUrl,
    preview = false,
}: Props) {
    const prefix = useId();
    const errorSummary = useRef<HTMLDivElement>(null);
    const [submitted, setSubmitted] = useState(false);
    const form = useForm<LeadFormData>({ lead_consent: false, values: {} });
    const trackEvent = usePublicTrackingEvent();
    const errors = form.errors as Record<string, string | undefined>;
    const errorEntries = Object.entries(errors).filter(
        (entry): entry is [string, string] => typeof entry[1] === 'string',
    );

    function fieldDomId(field: LinkPageFormField): string {
        return `${prefix}-${field.id}`;
    }

    function setField(fieldId: string, value: string) {
        form.setData('values', { ...form.data.values, [fieldId]: value });
        form.clearErrors(`values.${fieldId}` as keyof LeadFormData, 'values');
        setSubmitted(false);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (preview || !submitUrl) return;

        form.post(submitUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSubmitted(true);
                trackEvent('lead_submit');
            },
            onError: () => {
                requestAnimationFrame(() => errorSummary.current?.focus());
            },
        });
    }

    return (
        <section
            aria-label="Contact form"
            className="min-w-0 rounded-xl border bg-background p-4 shadow-sm sm:p-5"
        >
            <form onSubmit={submit} className="space-y-4">
                {errorEntries.length > 0 && (
                    <div
                        ref={errorSummary}
                        tabIndex={-1}
                        role="alert"
                        aria-labelledby={`${prefix}-error-title`}
                        className="rounded-md border border-destructive/50 bg-destructive/5 p-3 text-sm"
                    >
                        <h2
                            id={`${prefix}-error-title`}
                            className="font-medium text-destructive"
                        >
                            Check your response
                        </h2>
                        <ul className="mt-2 list-inside list-disc space-y-1">
                            {errorEntries.map(([key, message]) => {
                                const fieldId = key.startsWith('values.')
                                    ? key.slice('values.'.length)
                                    : null;
                                const field = fields.find(
                                    (candidate) => candidate.id === fieldId,
                                );
                                const target = field
                                    ? fieldDomId(field)
                                    : key === 'lead_consent'
                                      ? `${prefix}-consent`
                                      : `${prefix}-responses`;
                                const label =
                                    field?.label ??
                                    (key === 'lead_consent'
                                        ? 'Consent'
                                        : 'Form response');

                                return (
                                    <li key={key}>
                                        <a
                                            className="underline underline-offset-4"
                                            href={`#${target}`}
                                        >
                                            {label}: {message}
                                        </a>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}

                {errors.values && (
                    <FieldError id={`${prefix}-responses`} role="alert">
                        {errors.values}
                    </FieldError>
                )}

                <div className="grid gap-4">
                    {fields.map((field) => {
                        const id = fieldDomId(field);
                        const error = errors[`values.${field.id}`];
                        const errorId = `${id}-error`;
                        const common = {
                            id,
                            required: field.required,
                            disabled: preview || form.processing,
                            'aria-invalid': Boolean(error),
                            'aria-describedby': error ? errorId : undefined,
                            value: form.data.values[field.id] ?? '',
                        };

                        return (
                            <div key={field.id} className="grid gap-1.5">
                                <label
                                    htmlFor={id}
                                    className="text-sm font-medium"
                                >
                                    {field.label}
                                    {field.required && (
                                        <span className="ml-1 text-muted-foreground">
                                            (required)
                                        </span>
                                    )}
                                </label>
                                {field.type === 'textarea' ? (
                                    <Textarea
                                        {...common}
                                        maxLength={5000}
                                        onChange={(event) =>
                                            setField(
                                                field.id,
                                                event.target.value,
                                            )
                                        }
                                    />
                                ) : field.type === 'select' ? (
                                    <select
                                        {...common}
                                        className="min-h-11 rounded-md border border-input bg-background px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                        onChange={(event) =>
                                            setField(
                                                field.id,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Choose an option
                                        </option>
                                        {(field.options ?? []).map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <Input
                                        {...common}
                                        type={field.type}
                                        maxLength={
                                            field.type === 'email' ? 254 : 250
                                        }
                                        autoComplete={
                                            field.type === 'email'
                                                ? 'email'
                                                : 'off'
                                        }
                                        onChange={(event) =>
                                            setField(
                                                field.id,
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                                {error && (
                                    <FieldError id={errorId} role="alert">
                                        {error}
                                    </FieldError>
                                )}
                            </div>
                        );
                    })}
                </div>

                <div className="space-y-2">
                    <label
                        htmlFor={`${prefix}-consent`}
                        className="flex items-start gap-3 text-sm leading-6"
                    >
                        <Checkbox
                            id={`${prefix}-consent`}
                            checked={form.data.lead_consent}
                            disabled={preview || form.processing}
                            aria-invalid={Boolean(errors.lead_consent)}
                            aria-describedby={
                                errors.lead_consent
                                    ? `${prefix}-consent-error`
                                    : undefined
                            }
                            onCheckedChange={(checked) => {
                                form.setData('lead_consent', checked === true);
                                form.clearErrors('lead_consent');
                                setSubmitted(false);
                            }}
                        />
                        <span>
                            I agree to share these details with the page owner
                            and authorized workspace administrators. Responses
                            are kept for up to 30 days.
                        </span>
                    </label>
                    {errors.lead_consent && (
                        <FieldError id={`${prefix}-consent-error`} role="alert">
                            {errors.lead_consent}
                        </FieldError>
                    )}
                    {privacyUrl && (
                        <a
                            href={privacyUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-block text-sm text-primary underline underline-offset-4"
                        >
                            Read the privacy notice
                        </a>
                    )}
                </div>

                {submitted && !preview && (
                    <p
                        role="status"
                        className="text-sm font-medium text-foreground"
                    >
                        Your response was submitted.
                    </p>
                )}

                <Button
                    type="submit"
                    disabled={preview || form.processing || !submitUrl}
                    aria-label={
                        form.processing ? 'Sending response' : undefined
                    }
                >
                    {form.processing ? 'Sending…' : 'Send response'}
                </Button>
            </form>
        </section>
    );
}
