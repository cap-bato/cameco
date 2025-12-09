import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Calendar, Edit2, Shield, Plus, Eye } from 'lucide-react';
import { LeavePolicyFormModal } from '@/components/hr/leave-policy-form-modal';
import { LeavePolicyDetailsModal } from '@/components/hr/leave-policy-details-modal';
import { PermissionGate } from '@/components/permission-gate';
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
}

interface LeavePoliciesProps {
    policies: LeavePolicy[];
    canEdit?: boolean;
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Leave Management', href: '#' },
    { title: 'Policies', href: '/hr/leave/policies' },
];

const leaveTypeDescriptions: Record<string, string> = {
    'Vacation Leave': 'Annual leave for vacation and personal time',
    'Sick Leave': 'Leave for illness and medical appointments',
    'Emergency Leave': 'Leave for unexpected emergencies',
    'Maternity/Paternity Leave': 'Leave for new parents',
    'Privilege Leave': 'General personal leave',
    'Bereavement Leave': 'Leave for death of a family member',
    'Special Leave': 'Leave for special circumstances',
};

function getLeaveTypeIcon(type: string) {
    const icons: Record<string, React.ReactNode> = {
        'Vacation Leave': <Calendar className="h-5 w-5 text-blue-600" />,
        'Sick Leave': <Shield className="h-5 w-5 text-red-600" />,
        'Emergency Leave': <Shield className="h-5 w-5 text-yellow-600" />,
        'Maternity/Paternity Leave': <Calendar className="h-5 w-5 text-pink-600" />,
        'Privilege Leave': <Calendar className="h-5 w-5 text-green-600" />,
        'Bereavement Leave': <Calendar className="h-5 w-5 text-purple-600" />,
        'Special Leave': <Calendar className="h-5 w-5 text-gray-600" />,
    };
    return icons[type] || <Calendar className="h-5 w-5 text-gray-600" />;
}

export default function LeavePolicies({ policies }: LeavePoliciesProps) {
    const policiesData = Array.isArray(policies) ? policies : [];
    const { toast } = useToast();
    
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
    const [selectedPolicy, setSelectedPolicy] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');

    const handleCreatePolicy = () => {
        setSelectedPolicy(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };

    const handleEditPolicy = (policy: any) => {
        setSelectedPolicy(policy);
        setFormMode('edit');
        setIsFormModalOpen(true);
    };

    const handleEditFromDetails = (policy: any) => {
        setIsDetailsModalOpen(false);
        setSelectedPolicy(policy);
        setFormMode('edit');
        setIsFormModalOpen(true);
    };

    const handleViewDetails = (policy: any) => {
        setSelectedPolicy(policy);
        setIsDetailsModalOpen(true);
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
                        <h1 className="text-3xl font-bold tracking-tight">Leave Policies</h1>
                        <PermissionGate permission="hr.leave-policies.create">
                            <Button onClick={handleCreatePolicy}>
                                <Plus className="h-4 w-4 mr-2" />
                                Create Policy
                            </Button>
                        </PermissionGate>
                    </div>
                    <p className="text-muted-foreground">
                        Manage organization-wide leave policies and entitlements
                    </p>
                </div>

                {/* Leave Policies Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {policiesData && policiesData.length > 0 ? (
                        policiesData.map((policy) => (
                            <Card key={policy.id} className="hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3 flex-1">
                                            {getLeaveTypeIcon(policy.name)}
                                            <div>
                                                <CardTitle className="text-lg">{policy.name}</CardTitle>
                                                <CardDescription className="text-xs mt-1">
                                                    {policy.description || leaveTypeDescriptions[policy.name] || 'Leave policy'}
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Policy Details */}
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Annual Entitlement</span>
                                            <Badge variant="outline">{policy.annual_entitlement || 0} days</Badge>
                                        </div>

                                        {policy.max_carryover !== null && policy.max_carryover !== undefined && policy.max_carryover > 0 && (
                                            <div className="flex justify-between items-center">
                                                <span className="text-sm text-muted-foreground">Max Carryover</span>
                                                <Badge variant="outline">{policy.max_carryover} days</Badge>
                                            </div>
                                        )}

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Can Carry Forward</span>
                                            <Badge variant={policy.can_carry_forward ? 'default' : 'secondary'}>
                                                {policy.can_carry_forward ? 'Yes' : 'No'}
                                            </Badge>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Paid Leave</span>
                                            <Badge variant={policy.is_paid ? 'default' : 'secondary'}>
                                                {policy.is_paid ? 'Yes' : 'No'}
                                            </Badge>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex gap-2 pt-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="flex-1"
                                            onClick={() => handleViewDetails(policy)}
                                        >
                                            <Eye className="h-4 w-4 mr-1" />
                                            View Details
                                        </Button>
                                        <PermissionGate permission="hr.leave-policies.update">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                onClick={() => handleEditPolicy(policy)}
                                            >
                                                <Edit2 className="h-4 w-4 mr-1" />
                                                Edit
                                            </Button>
                                        </PermissionGate>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <div className="col-span-full">
                            <Card>
                                <CardContent className="text-center py-8">
                                    <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-2" />
                                    <p className="text-muted-foreground">No leave policies configured</p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Create your first leave policy to get started
                                    </p>
                                    <PermissionGate permission="hr.leave-policies.create">
                                        <Button onClick={handleCreatePolicy} className="mt-4">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Create Policy
                                        </Button>
                                    </PermissionGate>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>

                {/* Form Modal - Create/Edit */}
                {isFormModalOpen && (
                    <LeavePolicyFormModal
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

                {/* Details Modal */}
                {isDetailsModalOpen && selectedPolicy && (
                    <LeavePolicyDetailsModal
                        isOpen={isDetailsModalOpen}
                        onClose={() => setIsDetailsModalOpen(false)}
                        policy={selectedPolicy}
                        onEdit={handleEditFromDetails}
                    />
                )}

                {/* Standard Leave Types Reference */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Standard Leave Types</CardTitle>
                        <CardDescription>
                            Common leave types used in organizations
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="grid gap-3 md:grid-cols-2">
                                {Object.entries(leaveTypeDescriptions).map(([type, description]) => (
                                    <div key={type} className="flex gap-3 p-3 rounded-lg bg-muted/50">
                                        <div>{getLeaveTypeIcon(type)}</div>
                                        <div>
                                            <p className="text-sm font-medium">{type}</p>
                                            <p className="text-xs text-muted-foreground">{description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
