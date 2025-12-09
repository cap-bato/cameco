import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import { 
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { 
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Calendar, Plus, MoreVertical, Edit2, Trash2, Settings, CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { LeaveTypeFormModal } from '@/components/admin/leave-type-form-modal';
import { useToast } from '@/hooks/use-toast';

interface LeavePolicy {
    id: number;
    code: string;
    name: string;
    description: string;
    annual_entitlement: number;
    max_carryover: number;
    can_carry_forward: boolean;
    is_paid: boolean;
    is_active: boolean;
    effective_date: string;
    created_at: string;
}

interface ApprovalRules {
    duration_threshold_days: number;
    duration_tier2_days: number;
    balance_threshold_days: number;
    balance_warning_enabled: boolean;
    advance_notice_days: number;
    short_notice_requires_approval: boolean;
    coverage_threshold_percentage: number;
    coverage_warning_enabled: boolean;
    unpaid_leave_requires_manager: boolean;
    maternity_requires_admin: boolean;
    blackout_periods_enabled: boolean;
    blackout_dates: Array<{ start: string; end: string; reason: string }>;
    frequency_limit_enabled: boolean;
    frequency_max_requests: number;
    frequency_period_days: number;
}

interface LeavePoliciesIndexProps {
    policies: LeavePolicy[];
    approvalRules: ApprovalRules;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Leave Policies',
        href: '/admin/leave-policies',
    },
];

export default function LeavePoliciesIndex({ policies, approvalRules }: LeavePoliciesIndexProps) {
    const { toast } = useToast();
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [selectedPolicy, setSelectedPolicy] = useState<LeavePolicy | null>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [policyToDelete, setPolicyToDelete] = useState<LeavePolicy | null>(null);

    const handleCreatePolicy = () => {
        setSelectedPolicy(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };

    const handleEditPolicy = (policy: LeavePolicy) => {
        setSelectedPolicy(policy);
        setFormMode('edit');
        setIsFormModalOpen(true);
    };

    const handleDeleteClick = (policy: LeavePolicy) => {
        setPolicyToDelete(policy);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!policyToDelete) return;

        router.delete(`/admin/leave-policies/${policyToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Leave policy deleted successfully.',
                });
                setDeleteDialogOpen(false);
                setPolicyToDelete(null);
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.policy || 'Failed to delete leave policy.',
                    variant: 'destructive',
                });
                setDeleteDialogOpen(false);
                setPolicyToDelete(null);
            },
        });
    };

    const handleFormSuccess = () => {
        setIsFormModalOpen(false);
        setSelectedPolicy(null);
        toast({
            title: formMode === 'create' ? 'Policy Created' : 'Policy Updated',
            description: `Leave policy has been ${formMode === 'create' ? 'created' : 'updated'} successfully.`,
        });
        router.reload({ only: ['policies'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Policies" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Leave Policies</h1>
                            <p className="text-muted-foreground mt-1">
                                Configure leave types and approval workflow rules
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button 
                                variant="outline"
                                onClick={() => router.visit('/admin/leave-policies/approval-rules')}
                            >
                                <Settings className="h-4 w-4 mr-2" />
                                Approval Rules
                            </Button>
                            <Button onClick={handleCreatePolicy}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Leave Type
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Leave Types</CardDescription>
                            <CardTitle className="text-3xl">{policies.length}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                {policies.filter(p => p.is_active).length} active
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Approval Rules</CardDescription>
                            <CardTitle className="text-3xl">7</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Configurable workflow rules
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Auto-Approve Threshold</CardDescription>
                            <CardTitle className="text-3xl">{approvalRules.duration_threshold_days}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                days or less (HR Staff approval)
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Leave Policies Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Leave Types Configuration</CardTitle>
                        <CardDescription>
                            Manage leave types, entitlements, and carryover rules
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {policies.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Leave Type</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead className="text-right">Days per Year</TableHead>
                                        <TableHead>Accrual Method</TableHead>
                                        <TableHead className="text-right">Max Carryover</TableHead>
                                        <TableHead>Paid</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {policies.map((policy) => (
                                        <TableRow key={policy.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    <div>
                                                        <div>{policy.name}</div>
                                                        {policy.description && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {policy.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{policy.code}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {policy.annual_entitlement} days
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    Annual
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {policy.can_carry_forward ? (
                                                    <span>{policy.max_carryover} days</span>
                                                ) : (
                                                    <span className="text-muted-foreground">No carryover</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {policy.is_paid ? (
                                                    <Badge variant="default" className="gap-1">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        Paid
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="gap-1">
                                                        <XCircle className="h-3 w-3" />
                                                        Unpaid
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {policy.is_active ? (
                                                    <Badge variant="default" className="gap-1">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="gap-1">
                                                        <AlertCircle className="h-3 w-3" />
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => handleEditPolicy(policy)}>
                                                            <Edit2 className="h-4 w-4 mr-2" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem 
                                                            onClick={() => handleDeleteClick(policy)}
                                                            className="text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4 mr-2" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-12">
                                <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No leave policies configured</h3>
                                <p className="text-sm text-muted-foreground mb-4">
                                    Create your first leave policy to get started
                                </p>
                                <Button onClick={handleCreatePolicy}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Leave Type
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Standard Leave Types Reference */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Standard Philippine Leave Types</CardTitle>
                        <CardDescription>
                            Common leave types mandated by Philippine Labor Code
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-blue-600 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Vacation Leave (VL)</p>
                                    <p className="text-xs text-muted-foreground">15 days/year - Convertible to cash</p>
                                </div>
                            </div>
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-red-600 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Sick Leave (SL)</p>
                                    <p className="text-xs text-muted-foreground">15 days/year - Medical cert for 3+ days</p>
                                </div>
                            </div>
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-pink-600 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Maternity Leave</p>
                                    <p className="text-xs text-muted-foreground">105 days (60 paid, 45 unpaid)</p>
                                </div>
                            </div>
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-blue-500 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Paternity Leave</p>
                                    <p className="text-xs text-muted-foreground">7 days (paid)</p>
                                </div>
                            </div>
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-yellow-600 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Emergency Leave</p>
                                    <p className="text-xs text-muted-foreground">5 days/year - For urgent matters</p>
                                </div>
                            </div>
                            <div className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                <Calendar className="h-5 w-5 text-purple-600 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium">Bereavement Leave</p>
                                    <p className="text-xs text-muted-foreground">3-5 days - Immediate family</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Form Modal */}
                {isFormModalOpen && (
                    <LeaveTypeFormModal
                        isOpen={isFormModalOpen}
                        onClose={() => {
                            setIsFormModalOpen(false);
                            setSelectedPolicy(null);
                        }}
                        policy={selectedPolicy}
                        mode={formMode}
                        onSuccess={handleFormSuccess}
                    />
                )}

                {/* Delete Confirmation Dialog */}
                <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete Leave Policy</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to delete "{policyToDelete?.name}"? 
                                This action cannot be undone. If employees have active balances for this policy, 
                                deletion will be prevented.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={handleDeleteConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                                Delete
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
}
