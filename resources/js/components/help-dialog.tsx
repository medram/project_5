import { Link } from '@inertiajs/react';
import { ArrowUpRight, CreditCard, Settings } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as billingIndex } from '@/routes/billing';
import { edit as profileEdit } from '@/routes/profile';

const shortcuts = [
    { keys: ['Ctrl', 'K'], label: 'Open global search' },
    { keys: ['Ctrl', 'B'], label: 'Toggle the sidebar' },
    { keys: ['Esc'], label: 'Close a dialog or menu' },
];

export function HelpDialog({
    children,
    open,
    onOpenChange,
    onCloseAutoFocus,
}: {
    children?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    onCloseAutoFocus?: ComponentProps<typeof DialogContent>['onCloseAutoFocus'];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {children && <DialogTrigger asChild>{children}</DialogTrigger>}
            <DialogContent
                className="sm:max-w-md"
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <DialogHeader>
                    <DialogTitle>Help and information</DialogTitle>
                    <DialogDescription>
                        Shortcuts and the places people ask for most.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-5">
                    <section className="grid gap-2">
                        <h3 className="text-sm font-semibold">Keyboard</h3>
                        <ul className="grid gap-1.5">
                            {shortcuts.map((shortcut) => (
                                <li
                                    key={shortcut.label}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="text-muted-foreground">
                                        {shortcut.label}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        {shortcut.keys.map((key) => (
                                            <kbd
                                                key={key}
                                                className="inline-flex h-6 min-w-6 items-center justify-center rounded-md border border-border bg-surface-secondary px-1.5 font-sans text-[11px] font-medium text-muted-foreground"
                                            >
                                                {key}
                                            </kbd>
                                        ))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="grid gap-1">
                        <h3 className="mb-1 text-sm font-semibold">
                            Quick links
                        </h3>
                        <Link
                            href={profileEdit()}
                            className="flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm text-muted-foreground transition-colors hover:bg-surface-secondary hover:text-foreground"
                        >
                            <Settings className="size-4" aria-hidden="true" />
                            Account settings
                            <ArrowUpRight
                                className="ml-auto size-3.5"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            href={billingIndex()}
                            className="flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm text-muted-foreground transition-colors hover:bg-surface-secondary hover:text-foreground"
                        >
                            <CreditCard className="size-4" aria-hidden="true" />
                            Plan and usage
                            <ArrowUpRight
                                className="ml-auto size-3.5"
                                aria-hidden="true"
                            />
                        </Link>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
