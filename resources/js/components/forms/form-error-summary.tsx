import type { RefObject } from 'react';
import { cn } from '@/lib/utils';

export type FormErrorSummaryEntry = {
    key: string;
    label: string;
    message: string;
    interactive?: boolean;
};

export function FormErrorSummary({
    summaryRef,
    titleId,
    entries,
    onSelect,
    className,
}: {
    summaryRef: RefObject<HTMLDivElement | null>;
    titleId: string;
    entries: readonly FormErrorSummaryEntry[];
    onSelect: (key: string) => void;
    className?: string;
}) {
    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            ref={summaryRef}
            tabIndex={-1}
            role="alert"
            aria-labelledby={titleId}
            className={cn(
                'mx-6 mb-4 shrink-0 rounded-xl border border-danger/30 bg-danger-soft p-3 outline-none focus-visible:ring-3 focus-visible:ring-ring/30',
                className,
            )}
        >
            <p
                id={titleId}
                className="text-sm font-medium text-danger-soft-foreground"
            >
                Check the highlighted fields
            </p>
            <ul className="mt-1 flex flex-col gap-0.5">
                {entries.map((entry) => (
                    <li
                        key={entry.key}
                        className="text-sm text-danger-soft-foreground"
                    >
                        {entry.interactive ? (
                            <button
                                type="button"
                                className="rounded-sm text-left underline underline-offset-2 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                                onClick={() => onSelect(entry.key)}
                            >
                                {entry.label}: {entry.message}
                            </button>
                        ) : (
                            <span>
                                {entry.label}: {entry.message}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
