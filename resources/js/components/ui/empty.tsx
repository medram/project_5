import * as React from 'react';
import { cn } from '@/lib/utils';

function Empty({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="empty"
            className={cn(
                'flex min-w-0 flex-1 flex-col items-center justify-center gap-6 rounded-lg border border-dashed p-6 text-center text-balance md:p-12',
                className,
            )}
            {...props}
        />
    );
}

function EmptyHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="empty-header"
            className={cn(
                'flex max-w-sm flex-col items-center gap-2 text-center',
                className,
            )}
            {...props}
        />
    );
}

function EmptyMedia({
    className,
    variant = 'default',
    ...props
}: React.ComponentProps<'div'> & { variant?: 'default' | 'icon' }) {
    return (
        <div
            data-slot="empty-media"
            className={cn(
                'mb-2 flex shrink-0 items-center justify-center',
                variant === 'icon' && 'bg-muted text-muted-foreground size-10 rounded-lg',
                className,
            )}
            {...props}
        />
    );
}

function EmptyTitle({ className, ...props }: React.ComponentProps<'h3'>) {
    return (
        <h3
            data-slot="empty-title"
            className={cn('text-lg leading-tight font-semibold', className)}
            {...props}
        />
    );
}

function EmptyDescription({
    className,
    ...props
}: React.ComponentProps<'p'>) {
    return (
        <p
            data-slot="empty-description"
            className={cn('text-muted-foreground text-sm', className)}
            {...props}
        />
    );
}

function EmptyContent({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="empty-content"
            className={cn('flex flex-col items-center gap-2', className)}
            {...props}
        />
    );
}

export {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
};
