import * as React from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

function FieldGroup({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="field-group"
            className={cn('flex flex-col gap-5', className)}
            {...props}
        />
    );
}

function Field({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="field"
            className={cn('flex flex-col gap-2', className)}
            {...props}
        />
    );
}

function FieldLabel({
    className,
    ...props
}: React.ComponentProps<typeof Label>) {
    return (
        <Label
            data-slot="field-label"
            className={cn(className)}
            {...props}
        />
    );
}

function FieldDescription({
    className,
    ...props
}: React.ComponentProps<'p'>) {
    return (
        <p
            data-slot="field-description"
            className={cn('text-muted-foreground text-sm', className)}
            {...props}
        />
    );
}

function FieldError({ className, ...props }: React.ComponentProps<'p'>) {
    return (
        <p
            data-slot="field-error"
            className={cn('text-destructive text-sm', className)}
            {...props}
        />
    );
}

export { Field, FieldDescription, FieldError, FieldGroup, FieldLabel };
