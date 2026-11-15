import { usePage } from '@inertiajs/react';

export function useTranslation() {
    const { translations } = usePage().props;

    return (key: string, replacements?: Record<string, string | number>) => {
        let value = translations?.[key] ?? key;

        if (replacements) {
            for (const [name, replacement] of Object.entries(replacements)) {
                value = value.replaceAll(`:${name}`, String(replacement));
            }
        }

        return value;
    };
}
