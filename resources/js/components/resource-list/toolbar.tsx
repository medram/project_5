import type { ReactNode } from 'react';

type ResourceListToolbarProps = {
    action: string;
    className: string;
    children: ReactNode;
};

export function ResourceListToolbar({
    action,
    className,
    children,
}: ResourceListToolbarProps) {
    return (
        <form method="get" action={action} role="search" className={className}>
            {children}
        </form>
    );
}
