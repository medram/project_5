import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types/pagination';

type ResourceListPaginationProps = {
    links: PaginationLink[];
    total: number;
    ariaLabel: string;
    singular: string;
    plural: string;
};

export function ResourceListPagination({
    links,
    total,
    ariaLabel,
    singular,
    plural,
}: ResourceListPaginationProps) {
    return (
        <nav
            aria-label={ariaLabel}
            className="flex flex-wrap items-center gap-2"
        >
            {links.map((page, pageIndex) => {
                const label = page.label
                    .replace('&laquo; Previous', 'Previous')
                    .replace('Previous &raquo;', 'Next')
                    .replace('&laquo;', '‹')
                    .replace('&raquo;', '›');

                return page.url ? (
                    <Link
                        key={`${page.label}-${pageIndex}`}
                        href={page.url}
                        preserveScroll
                        aria-current={page.active ? 'page' : undefined}
                        className={`inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${page.active ? 'border-transparent bg-primary font-medium text-primary-foreground' : 'bg-card text-muted-foreground hover:text-foreground'}`}
                    >
                        {label}
                    </Link>
                ) : (
                    <span
                        key={`${page.label}-${pageIndex}`}
                        className="px-2.5 py-2 text-sm text-muted-foreground/60"
                    >
                        {label}
                    </span>
                );
            })}
            <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                {total} {total === 1 ? singular : plural}
            </span>
        </nav>
    );
}
