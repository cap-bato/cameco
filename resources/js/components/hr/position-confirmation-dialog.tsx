import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, Plus, Edit } from 'lucide-react';

interface PositionConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    isLoading?: boolean;
    mode: 'create' | 'edit';
    positionTitle?: string;
}

export function PositionConfirmationDialog({
    open,
    onOpenChange,
    onConfirm,
    isLoading = false,
    mode,
    positionTitle = '',
}: PositionConfirmationDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-950">
                            {mode === 'create' ? (
                                <Plus className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            ) : (
                                <Edit className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            )}
                        </div>
                        <div>
                            <DialogTitle>
                                {mode === 'create' ? 'Create New Position' : 'Edit Position'}
                            </DialogTitle>
                            {mode === 'edit' && positionTitle && (
                                <DialogDescription className="text-sm">
                                    {positionTitle}
                                </DialogDescription>
                            )}
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                        <AlertCircle className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                {mode === 'create'
                                    ? 'Create a new position'
                                    : 'Save changes to this position'}
                            </p>
                            <p className="text-sm text-blue-700 dark:text-blue-200">
                                {mode === 'create'
                                    ? 'This will add a new position to your organization structure.'
                                    : 'This will update the position details. Please ensure all information is correct.'}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isLoading}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        disabled={isLoading}
                    >
                        {isLoading ? 'Processing...' : mode === 'create' ? 'Create Position' : 'Save Changes'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
