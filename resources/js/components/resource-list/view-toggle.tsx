import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export function ResourceListViewToggle({
    value,
    cardsHref,
    tableHref,
}: {
    value: 'cards' | 'table';
    cardsHref: string;
    tableHref: string;
}) {
    return (
        <div
            role="group"
            aria-label="List view"
            className="hidden gap-2 min-[1100px]:flex"
        >
            <Button
                asChild
                variant={value === 'cards' ? 'default' : 'outline'}
                className="min-h-11 min-w-11"
            >
                <Link
                    href={cardsHref}
                    aria-current={value === 'cards' ? 'page' : undefined}
                >
                    Cards
                </Link>
            </Button>
            <Button
                asChild
                variant={value === 'table' ? 'default' : 'outline'}
                className="min-h-11 min-w-11"
            >
                <Link
                    href={tableHref}
                    aria-current={value === 'table' ? 'page' : undefined}
                >
                    Table
                </Link>
            </Button>
        </div>
    );
}
