import { cn } from '@/lib/utils';

export function CountryFlag({
    code,
    className,
}: {
    code: string;
    className?: string;
}) {
    const normalized = code.trim().toLowerCase();

    if (!/^[a-z]{2}$/.test(normalized)) {
        return null;
    }

    return (
        <span
            className={cn(
                'analytics-country-flag fi shrink-0 rounded-[2px] ring-1 ring-border/60',
                `fi-${normalized}`,
                className,
            )}
            aria-hidden="true"
        />
    );
}
