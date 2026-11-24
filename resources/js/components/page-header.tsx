import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    actions?: ReactNode;
};

export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex max-w-3xl min-w-0 flex-1 flex-col gap-2">
                <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em] text-balance">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-2xl text-sm leading-5 text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex min-w-0 flex-wrap items-center gap-2 sm:justify-end">
                    {actions}
                </div>
            )}
        </header>
    );
}
