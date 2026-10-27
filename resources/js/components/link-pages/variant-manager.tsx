import { router, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { show as showPage } from '@/routes/link-pages';
import {
    destroy as destroyVariant,
    store as storeVariant,
    update as updateVariant,
} from '@/routes/link-pages/variants';
import type { LinkPage, LinkPageVariant } from '@/types/link-pages';

type VariantFormData = {
    name: string;
    weight: number;
    is_active: boolean;
};

function VariantSettings({
    page,
    variant,
}: {
    page: LinkPage;
    variant: LinkPageVariant;
}) {
    const form = useForm<VariantFormData>({
        name: variant.name,
        weight: variant.weight,
        is_active: variant.isActive,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(
            updateVariant({
                linkPage: page.id,
                linkPageVariant: variant.id,
            }).url,
            { preserveScroll: true },
        );
    }

    function remove() {
        if (
            window.confirm(
                `Delete “${variant.name}” and move its content to Control?`,
            )
        ) {
            router.delete(
                destroyVariant({
                    linkPage: page.id,
                    linkPageVariant: variant.id,
                }).url,
                { preserveScroll: true },
            );
        }
    }

    return (
        <form onSubmit={submit} className="space-y-4 border-t pt-4">
            <FieldGroup>
                <Field data-invalid={Boolean(form.errors.name)}>
                    <FieldLabel htmlFor={`variant-name-${variant.id}`}>
                        Variant name
                    </FieldLabel>
                    <Input
                        id={`variant-name-${variant.id}`}
                        value={form.data.name}
                        maxLength={100}
                        disabled={form.processing}
                        aria-invalid={Boolean(form.errors.name)}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    {form.errors.name && (
                        <FieldError>{form.errors.name}</FieldError>
                    )}
                </Field>
                <Field data-invalid={Boolean(form.errors.weight)}>
                    <FieldLabel htmlFor={`variant-weight-${variant.id}`}>
                        Visitor share (%)
                    </FieldLabel>
                    <Input
                        id={`variant-weight-${variant.id}`}
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        value={form.data.weight}
                        readOnly={variant.isControl}
                        disabled={
                            form.processing ||
                            (!variant.isControl && !form.data.is_active)
                        }
                        aria-invalid={Boolean(form.errors.weight)}
                        onChange={(event) => {
                            const weight = event.currentTarget.valueAsNumber;
                            form.setData(
                                'weight',
                                Number.isFinite(weight) ? weight : 0,
                            );
                        }}
                    />
                    <FieldDescription>
                        {variant.isControl
                            ? 'Control receives the remaining visitor share.'
                            : 'Active variant shares cannot exceed 100% in total.'}
                    </FieldDescription>
                    {form.errors.weight && (
                        <FieldError>{form.errors.weight}</FieldError>
                    )}
                </Field>
                {!variant.isControl && (
                    <Field className="flex-row items-center">
                        <Checkbox
                            id={`variant-active-${variant.id}`}
                            checked={form.data.is_active}
                            disabled={form.processing}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <div className="grid gap-1.5 leading-none">
                            <FieldLabel
                                htmlFor={`variant-active-${variant.id}`}
                            >
                                Send visitors to this variant
                            </FieldLabel>
                            <FieldDescription>
                                Inactive variants remain editable but receive no
                                new visitors.
                            </FieldDescription>
                        </div>
                        {form.errors.is_active && (
                            <FieldError>{form.errors.is_active}</FieldError>
                        )}
                    </Field>
                )}
            </FieldGroup>
            <div className="flex flex-wrap justify-between gap-2">
                {!variant.isControl && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={remove}
                        disabled={form.processing}
                    >
                        <Trash2 data-icon="inline-start" />
                        Delete variant
                    </Button>
                )}
                <Button
                    type="submit"
                    className="ml-auto"
                    disabled={form.processing}
                >
                    {form.processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <Save data-icon="inline-start" />
                    )}
                    Save variant
                </Button>
            </div>
        </form>
    );
}

function AddVariantForm({ page }: { page: LinkPage }) {
    const form = useForm<{ name: string; weight: number }>({
        name: '',
        weight: 0,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(storeVariant(page).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="space-y-4 border-t pt-4">
            <div>
                <h3 className="text-sm font-medium">Add a variant</h3>
                <p className="text-sm text-muted-foreground">
                    Control automatically receives the share left over after
                    active variants.
                </p>
            </div>
            <FieldGroup>
                <Field data-invalid={Boolean(form.errors.name)}>
                    <FieldLabel htmlFor="new-variant-name">
                        Variant name
                    </FieldLabel>
                    <Input
                        id="new-variant-name"
                        value={form.data.name}
                        maxLength={100}
                        disabled={form.processing}
                        aria-invalid={Boolean(form.errors.name)}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Spring campaign"
                    />
                    {form.errors.name && (
                        <FieldError>{form.errors.name}</FieldError>
                    )}
                </Field>
                <Field data-invalid={Boolean(form.errors.weight)}>
                    <FieldLabel htmlFor="new-variant-weight">
                        Visitor share (%)
                    </FieldLabel>
                    <Input
                        id="new-variant-weight"
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        value={form.data.weight}
                        disabled={form.processing}
                        aria-invalid={Boolean(form.errors.weight)}
                        onChange={(event) => {
                            const weight = event.currentTarget.valueAsNumber;
                            form.setData(
                                'weight',
                                Number.isFinite(weight) ? weight : 0,
                            );
                        }}
                    />
                    {form.errors.weight && (
                        <FieldError>{form.errors.weight}</FieldError>
                    )}
                </Field>
            </FieldGroup>
            <Button type="submit" disabled={form.processing}>
                {form.processing ? (
                    <Spinner data-icon="inline-start" />
                ) : (
                    <Plus data-icon="inline-start" />
                )}
                Add variant
            </Button>
        </form>
    );
}

export function LinkPageVariantManager({
    page,
    variants,
    selectedVariantId,
}: {
    page: LinkPage;
    variants: LinkPageVariant[];
    selectedVariantId: number;
}) {
    const selected =
        variants.find((variant) => variant.id === selectedVariantId) ??
        variants.find((variant) => variant.isControl);

    if (!selected) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Traffic split</CardTitle>
                <CardDescription>
                    Choose the content variant to edit, then set how much
                    consent-independent traffic it receives.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <Field>
                    <FieldLabel htmlFor="selected-link-page-variant">
                        Editing variant
                    </FieldLabel>
                    <select
                        id="selected-link-page-variant"
                        className="h-11 w-full rounded-md border bg-background px-3"
                        value={selected.id}
                        onChange={(event) => {
                            const next = variants.find(
                                (variant) =>
                                    variant.id ===
                                    Number(event.currentTarget.value),
                            );
                            router.get(
                                showPage(page).url,
                                next?.isControl ? {} : { variant: next?.id },
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {variants.map((variant) => (
                            <option key={variant.id} value={variant.id}>
                                {variant.name} · {variant.weight}%
                                {!variant.isActive ? ' · inactive' : ''}
                            </option>
                        ))}
                    </select>
                </Field>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={selected.isControl ? 'default' : 'secondary'}
                    >
                        {selected.isControl
                            ? 'Control'
                            : selected.isActive
                              ? 'Active'
                              : 'Inactive'}
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                        {selected.weight}% of visitors
                    </span>
                </div>
                <VariantSettings
                    key={selected.id}
                    page={page}
                    variant={selected}
                />
                <AddVariantForm page={page} />
            </CardContent>
        </Card>
    );
}
