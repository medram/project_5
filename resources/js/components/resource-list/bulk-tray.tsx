import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export function ResourceListBulkTray({
    count,
    singular,
    plural,
    onClear,
    children,
}: {
    count: number;
    singular: string;
    plural: string;
    onClear: () => void;
    children: ReactNode;
}) {
    if (count === 0) return null;

    return (
        <section
            aria-label="Bulk actions"
            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3"
        >
            <p role="status" className="text-sm font-medium">
                {count} {count === 1 ? singular : plural} selected
            </p>
            <div className="flex flex-wrap items-center gap-2">
                {children}
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11"
                    onClick={onClear}
                >
                    Clear selection
                </Button>
            </div>
        </section>
    );
}
