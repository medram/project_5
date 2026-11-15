import { Check } from 'lucide-react';
import type { BioPageAppearance } from '@/types/bio-pages';

type AppearanceEditorProps = {
    value: BioPageAppearance;
    onChange: (value: BioPageAppearance) => void;
};

const themes: ReadonlyArray<{
    value: BioPageAppearance['theme'];
    label: string;
}> = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'soft', label: 'Soft' },
];

const typographyOptions: ReadonlyArray<{
    value: BioPageAppearance['typography'];
    label: string;
    sample: string;
}> = [
    { value: 'sans', label: 'Sans', sample: 'Aa' },
    { value: 'serif', label: 'Serif', sample: 'Aa' },
];

const colors: ReadonlyArray<{
    value: BioPageAppearance['color'];
    label: string;
}> = [
    { value: 'blue', label: 'Blue' },
    { value: 'violet', label: 'Violet' },
    { value: 'green', label: 'Green' },
    { value: 'amber', label: 'Amber' },
    { value: 'rose', label: 'Rose' },
];

const backgrounds: ReadonlyArray<{
    value: BioPageAppearance['background'];
    label: string;
}> = [
    { value: 'plain', label: 'Plain' },
    { value: 'sand', label: 'Sand' },
    { value: 'lavender', label: 'Lavender' },
    { value: 'midnight', label: 'Midnight' },
];

function update<Key extends keyof BioPageAppearance>(
    value: BioPageAppearance,
    key: Key,
    next: BioPageAppearance[Key],
    onChange: (value: BioPageAppearance) => void,
) {
    onChange({ ...value, [key]: next });
}

function ThemeCard({
    value,
    label,
    checked,
    onChange,
}: {
    value: BioPageAppearance['theme'];
    label: string;
    checked: boolean;
    onChange: () => void;
}) {
    return (
        <label className="relative cursor-pointer">
            <input
                type="radio"
                name="bio-appearance-theme"
                value={value}
                checked={checked}
                onChange={onChange}
                className="peer sr-only"
            />
            <span className="block rounded-xl border bg-card p-2 transition-[border-color,box-shadow] peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary/15 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
                <span
                    className={`bio-theme-${value} bio-background-plain block overflow-hidden rounded-lg border`}
                    aria-hidden="true"
                >
                    <span className="bio-page-identity block !rounded-lg !p-3 !shadow-none">
                        <span className="bio-page-handle block !text-[10px]">
                            @handle
                        </span>
                        <span className="bio-page-name !mt-2 block !text-2xl !leading-none">
                            Aa
                        </span>
                    </span>
                </span>
                <span className="mt-2 block px-1 pb-0.5 text-xs font-medium">
                    {label}
                </span>
            </span>
            <Check
                className="absolute top-3 right-3 size-3.5 text-primary opacity-0 peer-checked:opacity-100"
                aria-hidden="true"
            />
        </label>
    );
}

export function AppearanceEditor({ value, onChange }: AppearanceEditorProps) {
    return (
        <div className="grid gap-6">
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Theme</legend>
                <p className="text-xs text-muted-foreground">
                    Sets the identity card surface and contrast.
                </p>
                <div className="grid grid-cols-3 gap-2">
                    {themes.map((theme) => (
                        <ThemeCard
                            key={theme.value}
                            value={theme.value}
                            label={theme.label}
                            checked={value.theme === theme.value}
                            onChange={() =>
                                update(value, 'theme', theme.value, onChange)
                            }
                        />
                    ))}
                </div>
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Typography</legend>
                <p className="text-xs text-muted-foreground">
                    Applies to the bio copy while the display name stays
                    expressive.
                </p>
                <div className="grid grid-cols-2 gap-2">
                    {typographyOptions.map((option) => (
                        <label
                            key={option.value}
                            className="relative cursor-pointer"
                        >
                            <input
                                type="radio"
                                name="bio-appearance-typography"
                                value={option.value}
                                checked={value.typography === option.value}
                                onChange={() =>
                                    update(
                                        value,
                                        'typography',
                                        option.value,
                                        onChange,
                                    )
                                }
                                className="peer sr-only"
                            />
                            <span className="flex min-h-20 items-center gap-3 rounded-xl border bg-card p-3 transition-[border-color,box-shadow] peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary/15 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
                                <span
                                    className={`text-2xl ${option.value === 'serif' ? 'font-serif' : 'font-sans'}`}
                                    aria-hidden="true"
                                >
                                    {option.sample}
                                </span>
                                <span className="text-sm font-medium">
                                    {option.label}
                                </span>
                            </span>
                            <Check
                                className="absolute top-2.5 right-2.5 size-3.5 text-primary opacity-0 peer-checked:opacity-100"
                                aria-hidden="true"
                            />
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Accent color</legend>
                <p className="text-xs text-muted-foreground">
                    Used for the identity rule and interactive accents.
                </p>
                <div className="flex flex-wrap gap-2">
                    {colors.map((color) => (
                        <label
                            key={color.value}
                            className="relative flex min-w-16 cursor-pointer flex-col items-center gap-2 rounded-xl border bg-card px-3 py-2 transition-[border-color,box-shadow] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/15 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring"
                        >
                            <input
                                type="radio"
                                name="bio-appearance-color"
                                value={color.value}
                                checked={value.color === color.value}
                                onChange={() =>
                                    update(
                                        value,
                                        'color',
                                        color.value,
                                        onChange,
                                    )
                                }
                                className="peer sr-only"
                            />
                            <span
                                className={`bio-color-${color.value} size-7 rounded-full border-4 border-card bg-[var(--bio-accent)] shadow-sm`}
                                aria-hidden="true"
                            />
                            <span className="text-[11px] font-medium">
                                {color.label}
                            </span>
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Background</legend>
                <p className="text-xs text-muted-foreground">
                    Changes the page canvas without changing the identity theme.
                </p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {backgrounds.map((background) => (
                        <label
                            key={background.value}
                            className="relative cursor-pointer"
                        >
                            <input
                                type="radio"
                                name="bio-appearance-background"
                                value={background.value}
                                checked={value.background === background.value}
                                onChange={() =>
                                    update(
                                        value,
                                        'background',
                                        background.value,
                                        onChange,
                                    )
                                }
                                className="peer sr-only"
                            />
                            <span
                                className={`bio-background-${background.value} block overflow-hidden rounded-xl border bg-card p-2 transition-[border-color,box-shadow] peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary/15 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring`}
                            >
                                <span
                                    className="block rounded-lg border border-black/5 p-2"
                                    aria-hidden="true"
                                >
                                    <span className="block h-10 rounded-md bg-card/80" />
                                </span>
                                <span className="mt-2 block px-1 pb-0.5 text-xs font-medium">
                                    {background.label}
                                </span>
                            </span>
                            <Check
                                className="absolute top-3 right-3 size-3.5 text-primary opacity-0 peer-checked:opacity-100"
                                aria-hidden="true"
                            />
                        </label>
                    ))}
                </div>
            </fieldset>
        </div>
    );
}
