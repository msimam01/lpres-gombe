import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ConfirmDeleteDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description: React.ReactNode;
    confirmLabel?: string;
    onConfirm: () => void;
}

/**
 * Shared confirmation modal for destructive admin actions. Nothing is deleted
 * until the staff member explicitly confirms in this dialog.
 */
export default function ConfirmDeleteDialog({
    open,
    onOpenChange,
    title = 'Delete this record?',
    description,
    confirmLabel = 'Delete',
    onConfirm,
}: ConfirmDeleteDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-[#223645]">
                        <span aria-hidden="true" className="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <AlertTriangle className="size-5 text-red-600" />
                        </span>
                        {title}
                    </DialogTitle>
                    <DialogDescription className="pt-1">{description}</DialogDescription>
                </DialogHeader>
                <p className="text-sm font-medium text-red-600">This action cannot be undone.</p>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={onConfirm} className="bg-red-600 text-white hover:bg-red-700">
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Pairs a destructive action with the confirmation dialog: call
 * `confirmDelete(label, action)` from a Delete button and render `deleteDialog`
 * once in the page.
 */
export function useConfirmDelete() {
    const [pending, setPending] = useState<{ label: string; title?: string; run: () => void } | null>(null);

    const confirmDelete = (label: string, run: () => void, title?: string) => {
        setPending({ label, title, run });
    };

    const deleteDialog = (
        <ConfirmDeleteDialog
            open={pending !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setPending(null);
                }
            }}
            title={pending?.title}
            description={pending ? `“${pending.label}” will be permanently deleted.` : ''}
            onConfirm={() => {
                const action = pending?.run;
                setPending(null);
                action?.();
            }}
        />
    );

    return { confirmDelete, deleteDialog };
}
