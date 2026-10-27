import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types/pagination';

type ResourceListPaginationProps = {
    links: PaginationLink[];
    total: number;
    ariaLabel: string;
    summaryLabel: string;
};

export function ResourceListPagination({
    links,
    total,
    ariaLabel,
    summaryLabel,
}: ResourceListPaginationProps) {
    return (
        <nav
            aria-label={ariaLabel}
            className="flex flex-wrap items-center gap-2"
        >
            {links.map((page, pageIndex) => {
                const label = page.label
                    .replace('&laquo;', '«')
                    .replace('&raquo;', '»');

                return page.url ? (
                    <Link
                        key={`${page.label}-${pageIndex}`}
                        href={page.url}
                        preserveScroll
                        aria-current={page.active ? 'page' : undefined}
                        className={`min-h-11 min-w-11 rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${page.active ? 'bg-primary text-primary-foreground' : 'bg-background'}`}
                    >
                        {label}
                    </Link>
                ) : (
                    <span
                        key={`${page.label}-${pageIndex}`}
                        className="px-3 py-2 text-sm text-muted-foreground"
                    >
                        {label}
                    </span>
                );
            })}
            <span className="ml-auto text-sm text-muted-foreground">
                {total} {summaryLabel}
            </span>
        </nav>
    );
}
