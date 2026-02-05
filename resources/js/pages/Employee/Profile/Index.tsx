import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ProfileSection } from '@/components/employee/profile-section';
import { ProfileUpdateModal } from '@/components/employee/profile-update-modal';
import { 
    User,
    Briefcase,
    CreditCard,
    Phone,
    Edit,
    AlertCircle,
} from 'lucide-react';
import { useState } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

interface DepartmentInfo {
    name: string;
    code: string;
}

interface PositionInfo {
    title: string;
}

interface SupervisorInfo {
    employee_number: string;
    full_name: string;
}

interface PersonalInfo {
    full_name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    birthdate: string;
    age: number;
    gender: string;
    civil_status: string;
    nationality: string;
}

interface ContactInfo {
    contact_number: string | null;
    email: string;
    address: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    country: string;
}

interface GovernmentIDs {
    sss_number: string;
    philhealth_number: string;
    pagibig_number: string;
    tin: string;
}

interface EmergencyContact {
    name: string;
    relationship: string;
    phone: string;
    address: string;
}

interface EmployeeData {
    employee_number: string;
    email: string;
    department: DepartmentInfo | null;
    position: PositionInfo | null;
    employment_type: string;
    status: string;
    date_hired: string;
    regularization_date: string | null;
    supervisor: SupervisorInfo | null;
    personal_info: PersonalInfo | null;
    contact_info: ContactInfo | null;
    government_ids: GovernmentIDs | null;
    emergency_contact: EmergencyContact | null;
}

interface ProfileIndexProps {
    employee: EmployeeData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
    {
        title: 'My Profile',
        href: '/employee/profile',
    },
];

// ============================================================================
// Main Component
// ============================================================================

export default function ProfileIndex({ employee }: ProfileIndexProps) {
    const [isUpdateModalOpen, setIsUpdateModalOpen] = useState(false);

    // Employment Information Fields
    const employmentFields = [
        { label: 'Employee Number', value: employee.employee_number },
        { label: 'Email', value: employee.email },
        { label: 'Department', value: employee.department ? `${employee.department.name} (${employee.department.code})` : 'N/A' },
        { label: 'Position', value: employee.position?.title || 'N/A' },
        { label: 'Employment Type', value: employee.employment_type },
        { label: 'Status', value: employee.status },
        { label: 'Date Hired', value: employee.date_hired },
        { label: 'Regularization Date', value: employee.regularization_date || 'N/A' },
        { label: 'Supervisor', value: employee.supervisor ? `${employee.supervisor.full_name} (${employee.supervisor.employee_number})` : 'N/A' },
    ];

    // Personal Information Fields
    const personalFields = employee.personal_info ? [
        { label: 'Full Name', value: employee.personal_info.full_name },
        { label: 'First Name', value: employee.personal_info.first_name },
        { label: 'Middle Name', value: employee.personal_info.middle_name || 'N/A' },
        { label: 'Last Name', value: employee.personal_info.last_name },
        { label: 'Suffix', value: employee.personal_info.suffix || 'N/A' },
        { label: 'Date of Birth', value: employee.personal_info.birthdate },
        { label: 'Age', value: employee.personal_info.age?.toString() || 'N/A' },
        { label: 'Gender', value: employee.personal_info.gender },
        { label: 'Civil Status', value: employee.personal_info.civil_status },
        { label: 'Nationality', value: employee.personal_info.nationality },
    ] : [];

    // Contact Information Fields (Editable)
    const contactFields = employee.contact_info ? [
        { label: 'Contact Number', value: employee.contact_info.contact_number || 'Not set' },
        { label: 'Email', value: employee.contact_info.email },
        { label: 'Address', value: employee.contact_info.address || 'Not set' },
        { label: 'City', value: employee.contact_info.city || 'Not set' },
        { label: 'Province', value: employee.contact_info.province || 'Not set' },
        { label: 'Postal Code', value: employee.contact_info.postal_code || 'Not set' },
        { label: 'Country', value: employee.contact_info.country },
    ] : [];

    // Government IDs Fields
    const governmentIDFields = employee.government_ids ? [
        { label: 'SSS Number', value: employee.government_ids.sss_number },
        { label: 'PhilHealth Number', value: employee.government_ids.philhealth_number },
        { label: 'Pag-IBIG Number', value: employee.government_ids.pagibig_number },
        { label: 'TIN', value: employee.government_ids.tin },
    ] : [];

    // Emergency Contact Fields (Editable)
    const emergencyContactFields = employee.emergency_contact ? [
        { label: 'Contact Person Name', value: employee.emergency_contact.name },
        { label: 'Relationship', value: employee.emergency_contact.relationship },
        { label: 'Phone Number', value: employee.emergency_contact.phone },
        { label: 'Address', value: employee.emergency_contact.address },
    ] : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Profile" />

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">My Profile</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            View your personal information and employment details
                        </p>
                    </div>
                </div>

                {/* Info Alert */}
                <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-900/10">
                    <CardContent className="pt-6">
                        <div className="flex gap-3">
                            <AlertCircle className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                    Contact Information Updates
                                </p>
                                <p className="text-sm text-blue-800 dark:text-blue-200">
                                    You can update your contact information and emergency contact details. 
                                    Changes require HR Staff approval before being applied to your profile.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Profile Sections Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Employment Information */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Employment Information</CardTitle>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Non-editable
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ProfileSection fields={employmentFields} />
                        </CardContent>
                    </Card>

                    {/* Personal Information */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <User className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Personal Information</CardTitle>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Non-editable
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ProfileSection fields={personalFields} />
                        </CardContent>
                    </Card>

                    {/* Contact Information (Editable) */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Contact Information</CardTitle>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setIsUpdateModalOpen(true)}
                                >
                                    <Edit className="h-4 w-4 mr-1" />
                                    Edit
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ProfileSection fields={contactFields} />
                        </CardContent>
                    </Card>

                    {/* Government IDs */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <CreditCard className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Government IDs</CardTitle>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Non-editable
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ProfileSection fields={governmentIDFields} />
                            <p className="text-xs text-muted-foreground mt-4 pt-4 border-t">
                                To update government ID numbers, please contact HR Staff with supporting documents.
                            </p>
                        </CardContent>
                    </Card>

                    {/* Emergency Contact (Editable) */}
                    <Card className="lg:col-span-2">
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle>Emergency Contact</CardTitle>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setIsUpdateModalOpen(true)}
                                >
                                    <Edit className="h-4 w-4 mr-1" />
                                    Edit
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ProfileSection fields={emergencyContactFields} columns={4} />
                        </CardContent>
                    </Card>
                </div>

                {/* Help Note */}
                <Card className="border-muted">
                    <CardContent className="pt-6">
                        <div className="flex gap-3">
                            <AlertCircle className="h-5 w-5 text-muted-foreground flex-shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Need to update non-editable fields?</p>
                                <p className="text-sm text-muted-foreground">
                                    For changes to your name, date of birth, government IDs, or employment details, 
                                    please contact HR Staff with supporting documents.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Profile Update Modal */}
            <ProfileUpdateModal
                isOpen={isUpdateModalOpen}
                onClose={() => setIsUpdateModalOpen(false)}
                employee={employee}
            />
        </AppLayout>
    );
}
