import { Link } from '@inertiajs/react';
import { LayoutList, Rows3 } from 'lucide-react';

export function ResourceListViewToggle({
    value,
    cardsHref,
    tableHref,
}: {
    value: 'cards' | 'table';
    cardsHref: string;
    tableHref: string;
}) {
    const optionClass = (active: boolean) =>
        `inline-flex h-8 items-center gap-1.5 rounded-[13px] px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none ${
            active
                ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                : 'text-muted-foreground hover:text-foreground'
        }`;

    return (
        <div
            role="group"
            aria-label="List view"
            className="hidden items-center gap-[3px] rounded-2xl bg-default p-[3px] min-[1100px]:inline-flex"
        >
            <Link
                href={tableHref}
                aria-current={value === 'table' ? 'page' : undefined}
                className={optionClass(value === 'table')}
            >
                <Rows3 className="size-4" aria-hidden="true" />
                Table
            </Link>
            <Link
                href={cardsHref}
                aria-current={value === 'cards' ? 'page' : undefined}
                className={optionClass(value === 'cards')}
            >
                <LayoutList className="size-4" aria-hidden="true" />
                Cards
            </Link>
        </div>
    );
}
