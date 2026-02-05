import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { EmployeeStatusBadge } from '@/components/hr/employee-status-badge';
import { EmployeeDocumentsTab } from '@/components/hr/employee-documents-tab';
import { EmployeeHistoryTab } from '@/components/hr/employee-history-tab';
import { EmployeeArchiveDialog } from '@/components/hr/employee-archive-dialog';
import { RemarksSection } from '@/components/hr/forms/remarks-section';
import { DependentsSection } from '@/components/hr/forms/dependents-section';
import { ArrowLeft, Edit, Archive, FileText, History, User, Briefcase, MessageSquare, Users } from 'lucide-react';
import { useState } from 'react';
import { PermissionGate } from '@/components/permission-gate';

// ============================================================================
// Type Definitions
// ============================================================================

interface Profile {
    id: number;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    date_of_birth: string;
    place_of_birth: string | null;
    is_pwd: boolean | null;
    gender: string;
    civil_status: string;
    spouse_name: string | null;
    spouse_date_of_birth: string | null;
    spouse_contact_number: string | null;
    father_name: string | null;
    father_date_of_birth: string | null;
    mother_name: string | null;
    mother_date_of_birth: string | null;
    phone: string | null;
    mobile: string | null;
    current_address: string | null;
    permanent_address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_address: string | null;
    sss_number: string | null;
    tin_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    profile_picture_path: string | null;
}

interface Department {
    id: number;
    name: string;
    description: string | null;
}

interface Position {
    id: number;
    title: string;
    description: string | null;
}

interface Supervisor {
    id: number;
    employee_number: string;
    profile: {
        first_name: string;
        last_name: string;
    };
}

interface EmployeeDependent {
    id: number;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string;
    relationship: string;
    remarks?: string | null;
}

interface EmployeeRemark {
    id: number;
    remark: string;
    created_at: string;
    createdBy?: {
        id: number;
        name: string;
    };
}

interface Employee {
    id: number;
    employee_number: string;
    email: string;
    department_id: number;
    position_id: number;
    employment_type: string;
    date_hired: string;
    regularization_date: string | null;
    supervisor_id: number | null;
    status: 'active' | 'on_leave' | 'suspended' | 'terminated' | 'archived';
    profile: Profile;
    department: Department;
    position: Position;
    supervisor: Supervisor | null;
    dependents: EmployeeDependent[];
    remarks: EmployeeRemark[];
    created_at: string;
    updated_at: string;
}

interface ShowEmployeeProps {
    employee: Employee;
}

// ============================================================================
// Helper Functions
// ============================================================================

function getInitials(firstName: string, lastName: string): string {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

function getFullName(employee: Employee): string {
    const { first_name, middle_name, last_name, suffix } = employee.profile;
    let name = `${first_name}`;
    if (middle_name) name += ` ${middle_name}`;
    name += ` ${last_name}`;
    if (suffix) name += ` ${suffix}`;
    return name;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function maskID(id: string | null, visibleChars: number = 4): string {
    if (!id) return 'Not provided';
    if (id.length <= visibleChars) return id;
    const masked = '*'.repeat(id.length - visibleChars);
    return masked + id.slice(-visibleChars);
}

// ============================================================================
// Overview Tab Component
// ============================================================================

function OverviewTab({ employee }: { employee: Employee }) {
    const [showSSS, setShowSSS] = useState(false);
    const [showTIN, setShowTIN] = useState(false);
    const [showPhilHealth, setShowPhilHealth] = useState(false);
    const [showPagIBIG, setShowPagIBIG] = useState(false);

    return (
        <div className="space-y-6">
            {/* Personal Information Card */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
                    <User className="h-5 w-5" />
                    Personal Information
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Full Name</label>
                        <p className="text-sm mt-1">{getFullName(employee)}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Date of Birth</label>
                        <p className="text-sm mt-1">{formatDate(employee.profile.date_of_birth)}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Place of Birth</label>
                        <p className="text-sm mt-1">{employee.profile.place_of_birth || 'Not provided'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Gender</label>
                        <p className="text-sm mt-1">{employee.profile.gender}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Civil Status</label>
                        <p className="text-sm mt-1">{employee.profile.civil_status}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">PWD Status</label>
                        <p className="text-sm mt-1">{employee.profile.is_pwd ? 'Yes' : 'No'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Email</label>
                        <p className="text-sm mt-1">{employee.email}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Phone</label>
                        <p className="text-sm mt-1">{employee.profile.phone || 'Not provided'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Mobile</label>
                        <p className="text-sm mt-1">{employee.profile.mobile || 'Not provided'}</p>
                    </div>
                    <div className="md:col-span-2">
                        <label className="text-sm font-medium text-muted-foreground">Current Address</label>
                        <p className="text-sm mt-1">{employee.profile.current_address || 'Not provided'}</p>
                    </div>
                    <div className="md:col-span-2">
                        <label className="text-sm font-medium text-muted-foreground">Permanent Address</label>
                        <p className="text-sm mt-1">{employee.profile.permanent_address || 'Not provided'}</p>
                    </div>
                </div>
            </div>

            {/* Employment Information Card */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
                    <Briefcase className="h-5 w-5" />
                    Employment Information
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Employee Number</label>
                        <p className="text-sm mt-1 font-mono">{employee.employee_number}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Status</label>
                        <div className="mt-1">
                            <EmployeeStatusBadge status={employee.status} />
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Department</label>
                        <p className="text-sm mt-1">{employee.department.name}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Position</label>
                        <p className="text-sm mt-1">{employee.position?.title || 'Not assigned'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Employment Type</label>
                        <p className="text-sm mt-1">{employee.employment_type}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Date Hired</label>
                        <p className="text-sm mt-1">{formatDate(employee.date_hired)}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Regularization Date</label>
                        <p className="text-sm mt-1">
                            {employee.regularization_date ? formatDate(employee.regularization_date) : 'Not yet regularized'}
                        </p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Immediate Supervisor</label>
                        <p className="text-sm mt-1">
                            {employee.supervisor 
                                ? `${employee.supervisor.profile.first_name} ${employee.supervisor.profile.last_name} (${employee.supervisor.employee_number})`
                                : 'No supervisor assigned'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Emergency Contact Card */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Emergency Contact</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Contact Name</label>
                        <p className="text-sm mt-1">{employee.profile.emergency_contact_name || 'Not provided'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Relationship</label>
                        <p className="text-sm mt-1">{employee.profile.emergency_contact_relationship || 'Not provided'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Phone</label>
                        <p className="text-sm mt-1">{employee.profile.emergency_contact_phone || 'Not provided'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Address</label>
                        <p className="text-sm mt-1">{employee.profile.emergency_contact_address || 'Not provided'}</p>
                    </div>
                </div>
            </div>

            {/* Spouse Information Card (shown if married) */}
            {employee.profile.civil_status === 'married' && (
                <div className="bg-card rounded-lg border p-6">
                    <h3 className="text-lg font-semibold mb-4">Spouse Information</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Spouse Name</label>
                            <p className="text-sm mt-1">{employee.profile.spouse_name || 'Not provided'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Spouse Date of Birth</label>
                            <p className="text-sm mt-1">
                                {employee.profile.spouse_date_of_birth 
                                    ? formatDate(employee.profile.spouse_date_of_birth)
                                    : 'Not provided'}
                            </p>
                        </div>
                        <div className="md:col-span-2">
                            <label className="text-sm font-medium text-muted-foreground">Spouse Contact Number</label>
                            <p className="text-sm mt-1">{employee.profile.spouse_contact_number || 'Not provided'}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Parents Information Card */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Parents Information</h3>
                <div className="space-y-6">
                    {/* Father */}
                    <div>
                        <h4 className="text-sm font-medium mb-3">Father</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 ml-4">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground">Father's Name</label>
                                <p className="text-sm mt-1">{employee.profile.father_name || 'Not provided'}</p>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-muted-foreground">Father's Date of Birth</label>
                                <p className="text-sm mt-1">
                                    {employee.profile.father_date_of_birth
                                        ? formatDate(employee.profile.father_date_of_birth)
                                        : 'Not provided'}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    {/* Mother */}
                    <div>
                        <h4 className="text-sm font-medium mb-3">Mother</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 ml-4">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground">Mother's Name</label>
                                <p className="text-sm mt-1">{employee.profile.mother_name || 'Not provided'}</p>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-muted-foreground">Mother's Date of Birth</label>
                                <p className="text-sm mt-1">
                                    {employee.profile.mother_date_of_birth
                                        ? formatDate(employee.profile.mother_date_of_birth)
                                        : 'Not provided'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Government IDs Card */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Government IDs</h3>
                <PermissionGate 
                    permission="hr.employees.view_government_ids"
                    fallback={
                        <p className="text-sm text-muted-foreground italic">
                            You do not have permission to view government IDs. Contact HR Manager for access.
                        </p>
                    }
                >
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">SSS Number</label>
                        <div className="flex items-center gap-2 mt-1">
                            <p className="text-sm font-mono">
                                {showSSS ? employee.profile.sss_number || 'Not provided' : maskID(employee.profile.sss_number)}
                            </p>
                            {employee.profile.sss_number && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowSSS(!showSSS)}
                                    className="h-6 text-xs"
                                >
                                    {showSSS ? 'Hide' : 'Show'}
                                </Button>
                            )}
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">TIN</label>
                        <div className="flex items-center gap-2 mt-1">
                            <p className="text-sm font-mono">
                                {showTIN ? employee.profile.tin_number || 'Not provided' : maskID(employee.profile.tin_number)}
                            </p>
                            {employee.profile.tin_number && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowTIN(!showTIN)}
                                    className="h-6 text-xs"
                                >
                                    {showTIN ? 'Hide' : 'Show'}
                                </Button>
                            )}
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">PhilHealth Number</label>
                        <div className="flex items-center gap-2 mt-1">
                            <p className="text-sm font-mono">
                                {showPhilHealth ? employee.profile.philhealth_number || 'Not provided' : maskID(employee.profile.philhealth_number)}
                            </p>
                            {employee.profile.philhealth_number && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowPhilHealth(!showPhilHealth)}
                                    className="h-6 text-xs"
                                >
                                    {showPhilHealth ? 'Hide' : 'Show'}
                                </Button>
                            )}
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Pag-IBIG Number</label>
                        <div className="flex items-center gap-2 mt-1">
                            <p className="text-sm font-mono">
                                {showPagIBIG ? employee.profile.pagibig_number || 'Not provided' : maskID(employee.profile.pagibig_number)}
                            </p>
                            {employee.profile.pagibig_number && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowPagIBIG(!showPagIBIG)}
                                    className="h-6 text-xs"
                                >
                                    {showPagIBIG ? 'Hide' : 'Show'}
                                </Button>
                            )}
                        </div>
                    </div>
                    </div>
                </PermissionGate>
            </div>
        </div>
    );
}

// ============================================================================
// Employment Tab Component
// ============================================================================

function EmploymentTab({ employee }: { employee: Employee }) {
    const calculateTenure = () => {
        const hireDate = new Date(employee.date_hired);
        const today = new Date();
        const years = today.getFullYear() - hireDate.getFullYear();
        const months = today.getMonth() - hireDate.getMonth();
        
        const totalMonths = years * 12 + months;
        const tenureYears = Math.floor(totalMonths / 12);
        const tenureMonths = totalMonths % 12;
        
        if (tenureYears > 0 && tenureMonths > 0) {
            return `${tenureYears} year${tenureYears > 1 ? 's' : ''} and ${tenureMonths} month${tenureMonths > 1 ? 's' : ''}`;
        } else if (tenureYears > 0) {
            return `${tenureYears} year${tenureYears > 1 ? 's' : ''}`;
        } else {
            return `${tenureMonths} month${tenureMonths > 1 ? 's' : ''}`;
        }
    };

    return (
        <div className="space-y-6">
            {/* Department & Position Details */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Department & Position</h3>
                <div className="space-y-4">
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Department</label>
                        <p className="text-base font-medium mt-1">{employee.department.name}</p>
                        {employee.department.description && (
                            <p className="text-sm text-muted-foreground mt-1">{employee.department.description}</p>
                        )}
                    </div>
                    <div>
                        <label className="text-sm font-medium text-muted-foreground">Position</label>
                        <p className="text-base font-medium mt-1">{employee.position.title}</p>
                        {employee.position.description && (
                            <p className="text-sm text-muted-foreground mt-1">{employee.position.description}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Employment Dates */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Employment Timeline</h3>
                <div className="space-y-4">
                    <div className="flex items-start gap-4">
                        <div className="bg-primary/10 rounded-full p-2 mt-1">
                            <Briefcase className="h-4 w-4 text-primary" />
                        </div>
                        <div className="flex-1">
                            <p className="font-medium">Date Hired</p>
                            <p className="text-sm text-muted-foreground">{formatDate(employee.date_hired)}</p>
                            <p className="text-xs text-muted-foreground mt-1">Tenure: {calculateTenure()}</p>
                        </div>
                    </div>
                    
                    {employee.regularization_date && (
                        <div className="flex items-start gap-4">
                            <div className="bg-green-500/10 rounded-full p-2 mt-1">
                                <Briefcase className="h-4 w-4 text-green-600" />
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">Regularization Date</p>
                                <p className="text-sm text-muted-foreground">{formatDate(employee.regularization_date)}</p>
                            </div>
                        </div>
                    )}

                    <div className="flex items-start gap-4">
                        <div className="bg-blue-500/10 rounded-full p-2 mt-1">
                            <User className="h-4 w-4 text-blue-600" />
                        </div>
                        <div className="flex-1">
                            <p className="font-medium">Employment Type</p>
                            <p className="text-sm text-muted-foreground">{employee.employment_type}</p>
                        </div>
                    </div>

                    <div className="flex items-start gap-4">
                        <div className="bg-purple-500/10 rounded-full p-2 mt-1">
                            <User className="h-4 w-4 text-purple-600" />
                        </div>
                        <div className="flex-1">
                            <p className="font-medium">Current Status</p>
                            <div className="mt-1">
                                <EmployeeStatusBadge status={employee.status} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Supervisor Information */}
            <div className="bg-card rounded-lg border p-6">
                <h3 className="text-lg font-semibold mb-4">Reporting Structure</h3>
                {employee.supervisor ? (
                    <div className="space-y-2">
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Reports To</label>
                            <p className="text-base font-medium mt-1">
                                {employee.supervisor.profile.first_name} {employee.supervisor.profile.last_name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Employee #: {employee.supervisor.employee_number}
                            </p>
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">No immediate supervisor assigned</p>
                )}
            </div>
        </div>
    );
}

// ============================================================================
// Main Component
// ============================================================================

export default function ShowEmployee({ employee }: ShowEmployeeProps) {
    const [archiveDialogOpen, setArchiveDialogOpen] = useState(false);

    return (
        <AppLayout>
            <Head title={`${getFullName(employee)} - Employee Details`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <Link href="/hr/employees">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div className="flex items-start gap-4">
                            <Avatar className="h-16 w-16">
                                {employee.profile.profile_picture_path ? (
                                    <AvatarImage 
                                        src={`/storage/${employee.profile.profile_picture_path}`} 
                                        alt={getFullName(employee)}
                                    />
                                ) : null}
                                <AvatarFallback className="text-lg">
                                    {getInitials(employee.profile.first_name, employee.profile.last_name)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">{getFullName(employee)}</h1>
                                <div className="flex items-center gap-3 mt-1">
                                    <p className="text-muted-foreground font-mono">{employee.employee_number}</p>
                                    <EmployeeStatusBadge status={employee.status} />
                                </div>
                                <p className="text-sm text-muted-foreground mt-1">
                                    {employee.position?.title || 'No position'} • {employee.department?.name || 'No department'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <PermissionGate permission="hr.employees.update">
                            <Link href={`/hr/employees/${employee.id}/edit`}>
                                <Button variant="outline">
                                    <Edit className="h-4 w-4 mr-2" />
                                    Edit
                                </Button>
                            </Link>
                        </PermissionGate>
                        <PermissionGate permission="hr.employees.delete">
                            <Button 
                                variant="outline"
                                onClick={() => setArchiveDialogOpen(true)}
                            >
                                <Archive className="h-4 w-4 mr-2" />
                                Archive
                            </Button>
                        </PermissionGate>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="overview" className="w-full">
                    <TabsList>
                        <TabsTrigger value="overview" className="gap-2">
                            <User className="h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger value="employment" className="gap-2">
                            <Briefcase className="h-4 w-4" />
                            Employment
                        </TabsTrigger>
                        <TabsTrigger value="dependents" className="gap-2">
                            <Users className="h-4 w-4" />
                            Dependents
                        </TabsTrigger>
                        <TabsTrigger value="remarks" className="gap-2">
                            <MessageSquare className="h-4 w-4" />
                            Remarks
                        </TabsTrigger>
                        <TabsTrigger value="documents" className="gap-2">
                            <FileText className="h-4 w-4" />
                            Documents
                        </TabsTrigger>
                        <TabsTrigger value="history" className="gap-2">
                            <History className="h-4 w-4" />
                            History
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="mt-6">
                        <OverviewTab employee={employee} />
                    </TabsContent>

                    <TabsContent value="employment" className="mt-6">
                        <EmploymentTab employee={employee} />
                    </TabsContent>

                    <TabsContent value="dependents" className="mt-6">
                        <DependentsSection dependents={employee.dependents || []} onChange={() => {}} isEditMode={false} />
                    </TabsContent>

                    <TabsContent value="remarks" className="mt-6">
                        <RemarksSection remarks={employee.remarks || []} onChange={() => {}} isEditMode={false} />
                    </TabsContent>

                    <TabsContent value="documents" className="mt-6">
                        <EmployeeDocumentsTab employeeId={employee.id} />
                    </TabsContent>

                    <TabsContent value="history" className="mt-6">
                        <EmployeeHistoryTab employeeId={employee.id} />
                    </TabsContent>
                </Tabs>

                {/* Archive Confirmation Dialog */}
                <EmployeeArchiveDialog
                    open={archiveDialogOpen}
                    onOpenChange={setArchiveDialogOpen}
                    employeeId={employee.id}
                    employeeName={getFullName(employee)}
                    employeeNumber={employee.employee_number}
                />
            </div>
        </AppLayout>
    );
}
