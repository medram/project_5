import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type ResourceListFilter = {
    key: string;
    label: string;
    value: string;
    href: string;
};

export function ResourceListFilterChips({
    filters,
}: {
    filters: ResourceListFilter[];
}) {
    if (filters.length === 0) return null;

    return (
        <div
            role="group"
            aria-label="Active filters"
            className="flex flex-wrap gap-2"
        >
            {filters.map((filter) => (
                <Button
                    key={filter.key}
                    asChild
                    variant="outline"
                    size="sm"
                    className="min-h-11 max-w-full min-w-0"
                >
                    <Link
                        href={filter.href}
                        aria-label={`Remove ${filter.label} filter: ${filter.value}`}
                    >
                        <span className="min-w-0 truncate">
                            {filter.label}: {filter.value}
                        </span>
                        <X aria-hidden="true" />
                    </Link>
                </Button>
            ))}
        </div>
    );
}
