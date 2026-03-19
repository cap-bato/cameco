import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle, Archive } from 'lucide-react';

interface PositionArchiveDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    positionId: number;
    positionTitle: string;
    routePrefix?: string;
    employeeCount?: number;
}

export function PositionArchiveDialog({
    open,
    onOpenChange,
    positionId,
    positionTitle,
    routePrefix = '/hr',
    employeeCount = 0,
}: PositionArchiveDialogProps) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const hasEmployees = employeeCount > 0;

    const handleArchive = () => {
        setIsSubmitting(true);
        
        const url = `${routePrefix}/positions/${positionId}`;

        router.delete(url, {
            data: { reason },
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash?.error) {
                    toast.error(page.props.flash.error);
                    setIsSubmitting(false);
                    return;
                }
                
                toast.success('Position archived successfully');
                onOpenChange(false);
                setReason('');
                
                setTimeout(() => {
                    router.reload();
                }, 500);
            },
            onError: (errors) => {
                if (errors.message) {
                    toast.error(errors.message);
                } else if (errors.policy) {
                    toast.error(errors.policy);
                } else {
                    const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to archive position';
                    toast.error(errorMessage);
                }
                setIsSubmitting(false);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
                            <Archive className="h-5 w-5 text-destructive" />
                        </div>
                        <div>
                            <DialogTitle>Archive Position</DialogTitle>
                            <DialogDescription className="text-sm">
                                {positionTitle}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {hasEmployees ? (
                        <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                            <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-red-900 dark:text-red-100">
                                    Cannot archive: Position has {employeeCount} {employeeCount === 1 ? 'employee' : 'employees'}
                                </p>
                                <p className="text-sm text-red-700 dark:text-red-200">
                                    All employees must be transferred to another position before you can archive this position.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                            <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                    This action will archive the position
                                </p>
                                <p className="text-sm text-amber-700 dark:text-amber-200">
                                    The position will be marked as archived and will no longer appear in the active position list. 
                                    You can restore it later if needed.
                                </p>
                            </div>
                        </div>
                    )}

                    {!hasEmployees && (
                        <div className="space-y-2">
                            <Label htmlFor="archive-reason">
                                Reason for archiving (optional)
                            </Label>
                            <Textarea
                                id="archive-reason"
                                placeholder="Enter the reason for archiving this position..."
                                value={reason}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setReason(e.target.value)}
                                rows={4}
                                disabled={isSubmitting}
                            />
                            <p className="text-xs text-muted-foreground">
                                This will be recorded in the audit log for future reference.
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleArchive}
                        disabled={isSubmitting || hasEmployees}
                        title={hasEmployees ? `Cannot archive: ${employeeCount} ${employeeCount === 1 ? 'employee' : 'employees'} assigned` : undefined}
                    >
                        <Archive className="h-4 w-4 mr-2" />
                        {isSubmitting ? 'Archiving...' : 'Archive Position'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
