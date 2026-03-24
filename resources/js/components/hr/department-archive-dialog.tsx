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

interface DepartmentArchiveDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    departmentId: number;
    departmentName: string;
    routePrefix?: string;
    employeeCount?: number;
}

export function DepartmentArchiveDialog({
    open,
    onOpenChange,
    departmentId,
    departmentName,
    routePrefix = '/hr',
    employeeCount = 0,
}: DepartmentArchiveDialogProps) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const hasEmployees = employeeCount > 0;

    const handleArchive = () => {
        setIsSubmitting(true);
        
        // Debug: Log all parameters
        console.log('[DepartmentArchive] Archive parameters:', {
            departmentId,
            departmentName,
            routePrefix,
            reason,
        });

        // Construct URL explicitly
        const urlParts = [routePrefix, 'departments', String(departmentId)];
        const url = urlParts.join('/');
        console.log('[DepartmentArchive] Constructed URL parts:', urlParts);
        console.log('[DepartmentArchive] Final URL:', url);

        // Use Inertia router which handles redirects properly
        router.delete(url, {
            data: { reason },
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('[DepartmentArchive] Archive successful, page props:', {
                    flash: page.props.flash,
                    errors: page.props.errors,
                });
                
                // Check if there's a flash message indicating error
                const flashMessage = (page.props.flash as Record<string, any>)?.error;
                if (flashMessage) {
                    console.error('[DepartmentArchive] Backend error:', flashMessage);
                    toast.error(flashMessage);
                    setIsSubmitting(false);
                    return;
                }
                
                toast.success('Department archived successfully');
                onOpenChange(false);
                setReason('');
                
                // Reload the page to refresh the department list from server
                setTimeout(() => {
                    console.log('[DepartmentArchive] Reloading page');
                    router.reload();
                }, 500);
            },
            onError: (errors) => {
                console.error('[DepartmentArchive] Archive error:', errors);
                
                if (errors.message) {
                    toast.error(errors.message);
                } else if (errors.policy) {
                    toast.error(errors.policy);
                } else {
                    const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to archive department';
                    toast.error(errorMessage);
                }
                setIsSubmitting(false);
            },
            onFinish: () => {
                console.log('[DepartmentArchive] Request finished');
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
                            <DialogTitle>Archive Department</DialogTitle>
                            <DialogDescription className="text-sm">
                                {departmentName}
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
                                    Cannot archive: Department has {employeeCount} {employeeCount === 1 ? 'employee' : 'employees'}
                                </p>
                                <p className="text-sm text-red-700 dark:text-red-200">
                                    All employees must be transferred to another department or archived before you can archive this department.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                            <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                    This action will archive the department
                                </p>
                                <p className="text-sm text-amber-700 dark:text-amber-200">
                                    The department will be marked as archived and will no longer appear in the active department list. 
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
                                placeholder="Enter the reason for archiving this department..."
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
                        {isSubmitting ? 'Archiving...' : 'Archive Department'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
