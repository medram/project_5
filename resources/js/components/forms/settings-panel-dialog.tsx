import { type ReactNode, type RefObject } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export function SettingsPanelDialog({
    title,
    description,
    close,
    footer,
    triggerRef,
    children,
}: {
    title: string;
    description: string;
    close: () => void;
    footer: ReactNode;
    triggerRef: RefObject<HTMLButtonElement | null>;
    children: ReactNode;
}) {
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent
                className="z-[60] flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    triggerRef.current?.focus();
                }}
            >
                <DialogHeader className="shrink-0 px-6 pt-6 pr-14 pb-4 text-left">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-1">
                    {children}
                </div>
                <DialogFooter className="shrink-0 flex-col border-t border-border px-6 py-4">
                    {footer}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
