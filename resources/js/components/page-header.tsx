import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    eyebrow?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
};

export function PageHeader({
    title,
    description,
    eyebrow,
    icon: Icon,
    actions,
}: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="flex max-w-2xl min-w-0 flex-col gap-2">
                {(eyebrow || Icon) && (
                    <div className="flex items-center gap-2 text-linkpilot-creator">
                        {Icon && <Icon className="size-4" aria-hidden="true" />}
                        {eyebrow && (
                            <span className="text-sm font-medium">
                                {eyebrow}
                            </span>
                        )}
                    </div>
                )}
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
