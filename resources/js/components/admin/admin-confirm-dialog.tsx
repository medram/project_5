import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AdminConfirmation = {
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
};

export function AdminConfirmDialog({
    confirmation,
    processing,
    onCancel,
    onConfirm,
}: {
    confirmation: AdminConfirmation | null;
    processing: boolean;
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <Dialog
            open={confirmation !== null}
            onOpenChange={(open) => {
                if (!open && !processing) {
                    onCancel();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{confirmation?.title}</DialogTitle>
                    <DialogDescription>
                        {confirmation?.description}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={
                            confirmation?.destructive
                                ? 'destructive'
                                : 'default'
                        }
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {processing
                            ? 'Working…'
                            : (confirmation?.confirmLabel ?? 'Confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
