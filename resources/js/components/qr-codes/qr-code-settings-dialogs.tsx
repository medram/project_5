import { type RefObject, useState } from 'react';
import { SettingsPanelDialog as PanelShell } from '@/components/forms/settings-panel-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { QrCodeFormData, QrErrorCorrection } from './qr-code-form-data';

export type QrSettingsPanel = 'sharing' | 'style' | 'access' | 'schedule';

type ErrorMap = Record<string, string | undefined>;

type CommitSharing = Pick<QrCodeFormData, 'share_title' | 'share_description'>;
type CommitStyle = Pick<
    QrCodeFormData,
    | 'foreground_color'
    | 'background_color'
    | 'error_correction'
    | 'margin'
    | 'size'
    | 'logo'
    | 'logo_alt'
    | 'clear_logo'
>;
type CommitAccess = Pick<QrCodeFormData, 'access_code' | 'remove_access_code'>;
type CommitSchedule = Pick<
    QrCodeFormData,
    'available_from' | 'expires_at' | 'max_scans'
>;

const errorCorrectionOptions: ReadonlyArray<{
    value: QrErrorCorrection;
    label: string;
}> = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'quartile', label: 'Quartile' },
    { value: 'high', label: 'High' },
];

function FieldMessage({
    id,
    children,
}: {
    id: string;
    children: string | undefined;
}) {
    return children ? <FieldError id={id}>{children}</FieldError> : null;
}

function PanelFooter({
    close,
    save,
    processing,
}: {
    close: () => void;
    save: () => void;
    processing: boolean;
}) {
    return (
        <>
            <Button
                type="button"
                variant="outline"
                onClick={close}
                disabled={processing}
            >
                Cancel
            </Button>
            <Button type="button" onClick={save} disabled={processing}>
                Save changes
            </Button>
        </>
    );
}

function ColorField({
    id,
    label,
    value,
    onChange,
    disabled,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    error: string | undefined;
}) {
    const valid = /^#[0-9a-f]{6}$/i.test(value);

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <div className="flex items-center gap-2">
                <span
                    className="relative size-10 shrink-0 rounded-lg border border-border focus-within:ring-2 focus-within:ring-ring/40 focus-within:ring-offset-2"
                    style={{
                        backgroundColor: valid ? value : 'transparent',
                    }}
                >
                    <input
                        type="color"
                        value={valid ? value : '#000000'}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={disabled}
                        aria-label={`${label} color picker`}
                        className="absolute inset-0 size-full cursor-pointer opacity-0"
                    />
                </span>
                <Input
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    maxLength={7}
                    autoComplete="off"
                    spellCheck={false}
                    disabled={disabled}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                    className="h-10 font-mono uppercase"
                />
            </div>
            <FieldMessage id={`${id}-error`}>{error}</FieldMessage>
        </Field>
    );
}

function SharingPanel({
    data,
    errors,
    processing,
    close,
    triggerRef,
    onSave,
}: {
    data: QrCodeFormData;
    errors: ErrorMap;
    processing: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (next: CommitSharing) => void;
}) {
    const [shareTitle, setShareTitle] = useState(data.share_title);
    const [shareDescription, setShareDescription] = useState(
        data.share_description,
    );

    return (
        <PanelShell
            title="Sharing options"
            description="Control the title and description shown when someone opens this QR link."
            close={close}
            triggerRef={triggerRef}
            footer={
                <PanelFooter
                    close={close}
                    processing={processing}
                    save={() =>
                        onSave({
                            share_title: shareTitle,
                            share_description: shareDescription,
                        })
                    }
                />
            }
        >
            <FieldGroup>
                <Field data-invalid={Boolean(errors.share_title)}>
                    <FieldLabel htmlFor="qr-share-title">
                        Sharing title
                    </FieldLabel>
                    <Input
                        id="qr-share-title"
                        value={shareTitle}
                        onChange={(event) => setShareTitle(event.target.value)}
                        maxLength={160}
                        disabled={processing}
                        aria-invalid={Boolean(errors.share_title)}
                        aria-describedby={
                            errors.share_title
                                ? 'qr-share-title-error'
                                : undefined
                        }
                    />
                    <FieldDescription>
                        Used as the page title when the QR requires an access
                        code.
                    </FieldDescription>
                    <FieldMessage id="qr-share-title-error">
                        {errors.share_title}
                    </FieldMessage>
                </Field>

                <Field data-invalid={Boolean(errors.share_description)}>
                    <FieldLabel htmlFor="qr-share-description">
                        Sharing description
                    </FieldLabel>
                    <Textarea
                        id="qr-share-description"
                        value={shareDescription}
                        onChange={(event) =>
                            setShareDescription(event.target.value)
                        }
                        maxLength={2000}
                        rows={4}
                        disabled={processing}
                        aria-invalid={Boolean(errors.share_description)}
                        aria-describedby={
                            errors.share_description
                                ? 'qr-share-description-error'
                                : undefined
                        }
                    />
                    <FieldDescription>
                        Optional copy for the unlock page.
                    </FieldDescription>
                    <FieldMessage id="qr-share-description-error">
                        {errors.share_description}
                    </FieldMessage>
                </Field>
            </FieldGroup>
        </PanelShell>
    );
}

function StylePanel({
    data,
    errors,
    processing,
    hasExistingLogo,
    close,
    triggerRef,
    onSave,
}: {
    data: QrCodeFormData;
    errors: ErrorMap;
    processing: boolean;
    hasExistingLogo: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (next: CommitStyle) => void;
}) {
    const [foreground, setForeground] = useState(data.foreground_color);
    const [background, setBackground] = useState(data.background_color);
    const [correction, setCorrection] = useState<QrErrorCorrection>(
        data.error_correction,
    );
    const [margin, setMargin] = useState(data.margin);
    const [size, setSize] = useState(data.size);
    const [logo, setLogo] = useState<File | null>(data.logo);
    const [logoAlt, setLogoAlt] = useState(data.logo_alt);
    const [clearLogo, setClearLogo] = useState(data.clear_logo);

    return (
        <PanelShell
            title="Style"
            description="Set the QR colors, density, and embedded logo."
            close={close}
            triggerRef={triggerRef}
            footer={
                <PanelFooter
                    close={close}
                    processing={processing}
                    save={() =>
                        onSave({
                            foreground_color: foreground,
                            background_color: background,
                            error_correction: correction,
                            margin,
                            size,
                            logo,
                            logo_alt: logoAlt,
                            clear_logo: clearLogo,
                        })
                    }
                />
            }
        >
            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <ColorField
                        id="qr-foreground"
                        label="Foreground"
                        value={foreground}
                        onChange={setForeground}
                        disabled={processing}
                        error={errors.foreground_color}
                    />
                    <ColorField
                        id="qr-background"
                        label="Background"
                        value={background}
                        onChange={setBackground}
                        disabled={processing}
                        error={errors.background_color}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={Boolean(errors.error_correction)}>
                        <FieldLabel htmlFor="qr-correction">
                            Error correction
                        </FieldLabel>
                        <Select
                            value={correction}
                            onValueChange={(value: QrErrorCorrection) =>
                                setCorrection(value)
                            }
                            disabled={processing}
                        >
                            <SelectTrigger
                                id="qr-correction"
                                className="h-10 w-full"
                                aria-invalid={Boolean(errors.error_correction)}
                                aria-describedby={
                                    errors.error_correction
                                        ? 'qr-error-correction-error'
                                        : undefined
                                }
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {errorCorrectionOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldDescription>
                            Higher correction survives more damage.
                        </FieldDescription>
                        <FieldMessage id="qr-error-correction-error">
                            {errors.error_correction}
                        </FieldMessage>
                    </Field>

                    <Field data-invalid={Boolean(errors.size)}>
                        <FieldLabel htmlFor="qr-size">Size (pixels)</FieldLabel>
                        <Input
                            id="qr-size"
                            type="number"
                            min={128}
                            max={1024}
                            value={size}
                            onChange={(event) => setSize(event.target.value)}
                            disabled={processing}
                            aria-invalid={Boolean(errors.size)}
                            aria-describedby={
                                errors.size ? 'qr-size-error' : undefined
                            }
                        />
                        <FieldDescription>
                            128–1024 px for downloads.
                        </FieldDescription>
                        <FieldMessage id="qr-size-error">
                            {errors.size}
                        </FieldMessage>
                    </Field>
                </div>

                <Field data-invalid={Boolean(errors.margin)}>
                    <FieldLabel htmlFor="qr-margin">
                        Quiet-zone margin
                    </FieldLabel>
                    <Input
                        id="qr-margin"
                        type="number"
                        min={0}
                        max={20}
                        value={margin}
                        onChange={(event) => setMargin(event.target.value)}
                        disabled={processing}
                        aria-invalid={Boolean(errors.margin)}
                        aria-describedby={
                            errors.margin ? 'qr-margin-error' : undefined
                        }
                    />
                    <FieldDescription>
                        Keep at least 4 modules so scanners can find the code.
                    </FieldDescription>
                    <FieldMessage id="qr-margin-error">
                        {errors.margin}
                    </FieldMessage>
                </Field>

                <Field data-invalid={Boolean(errors.logo || errors.logo_alt)}>
                    <FieldLabel htmlFor="qr-logo">Logo image</FieldLabel>
                    <Input
                        id="qr-logo"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="h-10 cursor-pointer text-transparent file:mr-3 file:rounded-md file:border-0 file:bg-default file:px-3 file:text-sm file:font-medium file:text-foreground hover:file:bg-surface-tertiary"
                        onChange={(event) => {
                            const file = event.target.files?.[0] ?? null;
                            setLogo(file);
                            if (file) {
                                setClearLogo(false);
                            }
                        }}
                        disabled={processing || clearLogo}
                        aria-invalid={Boolean(errors.logo)}
                        aria-describedby={
                            errors.logo ? 'qr-logo-error' : undefined
                        }
                    />
                    {logo && (
                        <p className="truncate text-xs text-muted-foreground">
                            {logo.name}
                        </p>
                    )}
                    <FieldDescription>
                        Optional PNG, JPEG, or WebP image up to 2 MB and 1024 ×
                        1024 px.
                    </FieldDescription>
                    {hasExistingLogo && (
                        <Field className="flex-row items-center gap-2">
                            <Checkbox
                                id="qr-clear-logo"
                                checked={clearLogo}
                                onCheckedChange={(checked) => {
                                    const next = checked === true;
                                    setClearLogo(next);
                                    if (next) {
                                        setLogo(null);
                                    }
                                }}
                                disabled={processing}
                            />
                            <FieldLabel
                                htmlFor="qr-clear-logo"
                                className="font-normal"
                            >
                                Remove current logo
                            </FieldLabel>
                        </Field>
                    )}
                    <FieldLabel htmlFor="qr-logo-alt">
                        Logo description
                    </FieldLabel>
                    <Input
                        id="qr-logo-alt"
                        value={logoAlt}
                        onChange={(event) => setLogoAlt(event.target.value)}
                        maxLength={160}
                        disabled={processing}
                        aria-invalid={Boolean(errors.logo_alt)}
                        aria-describedby={
                            errors.logo_alt ? 'qr-logo-alt-error' : undefined
                        }
                    />
                    <FieldDescription>
                        Used by screen readers and download metadata.
                    </FieldDescription>
                    <FieldMessage id="qr-logo-error">
                        {errors.logo}
                    </FieldMessage>
                    <FieldMessage id="qr-logo-alt-error">
                        {errors.logo_alt}
                    </FieldMessage>
                </Field>
            </FieldGroup>
        </PanelShell>
    );
}

function AccessPanel({
    data,
    errors,
    processing,
    hasExistingAccessCode,
    close,
    triggerRef,
    onSave,
}: {
    data: QrCodeFormData;
    errors: ErrorMap;
    processing: boolean;
    hasExistingAccessCode: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (next: CommitAccess) => void;
}) {
    const [accessCode, setAccessCode] = useState(data.access_code);
    const [removeAccessCode, setRemoveAccessCode] = useState(
        data.remove_access_code,
    );

    return (
        <PanelShell
            title="Access"
            description="Require a code before the QR resolves to its destination."
            close={close}
            triggerRef={triggerRef}
            footer={
                <PanelFooter
                    close={close}
                    processing={processing}
                    save={() =>
                        onSave({
                            access_code: accessCode,
                            remove_access_code: removeAccessCode,
                        })
                    }
                />
            }
        >
            <FieldGroup>
                <Field data-invalid={Boolean(errors.access_code)}>
                    <FieldLabel htmlFor="qr-access-code">
                        {hasExistingAccessCode
                            ? 'Replace access code'
                            : 'Access code'}
                    </FieldLabel>
                    <Input
                        id="qr-access-code"
                        type="password"
                        value={accessCode}
                        onChange={(event) => {
                            setAccessCode(event.target.value);
                            if (event.target.value !== '') {
                                setRemoveAccessCode(false);
                            }
                        }}
                        autoComplete="new-password"
                        maxLength={255}
                        placeholder={
                            hasExistingAccessCode
                                ? 'Leave blank to keep current'
                                : 'Optional'
                        }
                        disabled={processing || removeAccessCode}
                        aria-invalid={Boolean(errors.access_code)}
                        aria-describedby={
                            errors.access_code
                                ? 'qr-access-code-error'
                                : undefined
                        }
                    />
                    <FieldDescription>
                        Visitors must unlock the QR before the scan is counted.
                    </FieldDescription>
                    {hasExistingAccessCode && (
                        <Field className="flex-row items-center gap-2">
                            <Checkbox
                                id="qr-remove-access-code"
                                checked={removeAccessCode}
                                onCheckedChange={(checked) => {
                                    const next = checked === true;
                                    setRemoveAccessCode(next);
                                    if (next) {
                                        setAccessCode('');
                                    }
                                }}
                                disabled={processing}
                            />
                            <FieldLabel
                                htmlFor="qr-remove-access-code"
                                className="font-normal"
                            >
                                Remove the current access code
                            </FieldLabel>
                        </Field>
                    )}
                    <FieldMessage id="qr-access-code-error">
                        {errors.access_code}
                    </FieldMessage>
                </Field>
            </FieldGroup>
        </PanelShell>
    );
}

function SchedulePanel({
    data,
    errors,
    processing,
    close,
    triggerRef,
    onSave,
}: {
    data: QrCodeFormData;
    errors: ErrorMap;
    processing: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (next: CommitSchedule) => void;
}) {
    const [availableFrom, setAvailableFrom] = useState(data.available_from);
    const [expiresAt, setExpiresAt] = useState(data.expires_at);
    const [maxScans, setMaxScans] = useState(data.max_scans);

    return (
        <PanelShell
            title="Schedule"
            description="Set when the QR is available and how many scans it accepts."
            close={close}
            triggerRef={triggerRef}
            footer={
                <PanelFooter
                    close={close}
                    processing={processing}
                    save={() =>
                        onSave({
                            available_from: availableFrom,
                            expires_at: expiresAt,
                            max_scans: maxScans,
                        })
                    }
                />
            }
        >
            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={Boolean(errors.available_from)}>
                        <FieldLabel htmlFor="qr-available-from">
                            Available from
                        </FieldLabel>
                        <Input
                            id="qr-available-from"
                            type="datetime-local"
                            value={availableFrom}
                            onChange={(event) =>
                                setAvailableFrom(event.target.value)
                            }
                            disabled={processing}
                            aria-invalid={Boolean(errors.available_from)}
                            aria-describedby={
                                errors.available_from
                                    ? 'qr-available-from-error'
                                    : undefined
                            }
                        />
                        <FieldMessage id="qr-available-from-error">
                            {errors.available_from}
                        </FieldMessage>
                    </Field>
                    <Field data-invalid={Boolean(errors.expires_at)}>
                        <FieldLabel htmlFor="qr-expires-at">
                            Expires at
                        </FieldLabel>
                        <Input
                            id="qr-expires-at"
                            type="datetime-local"
                            value={expiresAt}
                            onChange={(event) =>
                                setExpiresAt(event.target.value)
                            }
                            disabled={processing}
                            aria-invalid={Boolean(errors.expires_at)}
                            aria-describedby={
                                errors.expires_at
                                    ? 'qr-expires-at-error'
                                    : undefined
                            }
                        />
                        <FieldMessage id="qr-expires-at-error">
                            {errors.expires_at}
                        </FieldMessage>
                    </Field>
                </div>

                <Field data-invalid={Boolean(errors.max_scans)}>
                    <FieldLabel htmlFor="qr-max-scans">
                        Maximum scans
                    </FieldLabel>
                    <Input
                        id="qr-max-scans"
                        type="number"
                        min={1}
                        step={1}
                        value={maxScans}
                        onChange={(event) => setMaxScans(event.target.value)}
                        placeholder="Unlimited"
                        disabled={processing}
                        aria-invalid={Boolean(errors.max_scans)}
                        aria-describedby={
                            errors.max_scans ? 'qr-max-scans-error' : undefined
                        }
                    />
                    <FieldDescription>
                        Leave blank for unlimited scans.
                    </FieldDescription>
                    <FieldMessage id="qr-max-scans-error">
                        {errors.max_scans}
                    </FieldMessage>
                </Field>
            </FieldGroup>
        </PanelShell>
    );
}

export function QrCodeSettingsDialogs({
    activePanel,
    onActivePanelChange,
    settingsTriggerRef,
    data,
    errors,
    processing,
    hasExistingAccessCode,
    hasExistingLogo,
    onCommitSharing,
    onCommitStyle,
    onCommitAccess,
    onCommitSchedule,
}: {
    activePanel: QrSettingsPanel | null;
    onActivePanelChange: (panel: QrSettingsPanel | null) => void;
    settingsTriggerRef: RefObject<HTMLButtonElement | null>;
    data: QrCodeFormData;
    errors: ErrorMap;
    processing: boolean;
    hasExistingAccessCode: boolean;
    hasExistingLogo: boolean;
    onCommitSharing: (next: CommitSharing) => void;
    onCommitStyle: (next: CommitStyle) => void;
    onCommitAccess: (next: CommitAccess) => void;
    onCommitSchedule: (next: CommitSchedule) => void;
}) {
    const close = () => onActivePanelChange(null);

    if (activePanel === null) {
        return null;
    }

    if (activePanel === 'sharing') {
        return (
            <SharingPanel
                data={data}
                errors={errors}
                processing={processing}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={onCommitSharing}
            />
        );
    }

    if (activePanel === 'style') {
        return (
            <StylePanel
                data={data}
                errors={errors}
                processing={processing}
                hasExistingLogo={hasExistingLogo}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={onCommitStyle}
            />
        );
    }

    if (activePanel === 'access') {
        return (
            <AccessPanel
                data={data}
                errors={errors}
                processing={processing}
                hasExistingAccessCode={hasExistingAccessCode}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={onCommitAccess}
            />
        );
    }

    return (
        <SchedulePanel
            data={data}
            errors={errors}
            processing={processing}
            close={close}
            triggerRef={settingsTriggerRef}
            onSave={onCommitSchedule}
        />
    );
}
