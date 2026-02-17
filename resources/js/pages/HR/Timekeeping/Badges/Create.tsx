import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Plus, CheckCircle, AlertCircle } from 'lucide-react';
import { BadgeIssuanceModal, type BadgeFormData } from '@/components/hr/badge-issuance-modal';

interface Employee {
    id: string;
    name: string;
    employee_id: string;
    department: string;
    position: string;
    photo?: string;
    badge?: {
        card_uid: string;
        issued_at: string;
        expires_at: string | null;
        last_used_at: string | null;
        is_active: boolean;
    };
}

interface BadgeSubmitResult {
    employeeName: string;
    employeeId: string;
    cardUid: string;
    cardType: string;
    expiresAt: string;
    issuedAt: string;
}

export default function CreateBadge() {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitResult, setSubmitResult] = useState<{
        success: boolean;
        message: string;
        badgeData?: BadgeSubmitResult;
    } | null>(null);

    // Mock employees data for Phase 1
    const [mockEmployees] = useState<Employee[]>([
        {
            id: '1',
            name: 'Juan Dela Cruz',
            employee_id: 'EMP-2024-001',
            department: 'Operations',
            position: 'Warehouse Supervisor',
            photo: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Juan',
            badge: {
                card_uid: '04:3A:B2:C5:D8',
                issued_at: '2024-01-15T10:00:00',
                expires_at: '2026-01-15',
                last_used_at: '2024-02-12T08:05:23',
                is_active: true,
            },
        },
        {
            id: '2',
            name: 'Maria Santos',
            employee_id: 'EMP-2024-002',
            department: 'HR',
            position: 'HR Manager',
            photo: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Maria',
        },
        {
            id: '3',
            name: 'Pedro Garcia',
            employee_id: 'EMP-2024-003',
            department: 'Engineering',
            position: 'Systems Engineer',
            photo: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Pedro',
        },
        {
            id: '4',
            name: 'Angela Lopez',
            employee_id: 'EMP-2024-004',
            department: 'Operations',
            position: 'Forklift Operator',
            photo: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Angela',
        },
        {
            id: '5',
            name: 'Ramon Reyes',
            employee_id: 'EMP-2024-005',
            department: 'Warehouse',
            position: 'Warehouse Staff',
            photo: 'https://api.dicebear.com/7.x/avataaars/svg?seed=Ramon',
        },
    ]);

    // Subtask 1.3.4: Extract existing badge UIDs for uniqueness validation
    const existingBadgeUids = mockEmployees
        .filter((emp) => emp.badge?.is_active)
        .map((emp) => emp.badge!.card_uid);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'RFID Badges', href: '/hr/timekeeping/badges' },
        { title: 'Issue New Badge', href: '#' },
    ];

    const handleModalOpen = () => {
        setIsModalOpen(true);
        setSubmitResult(null);
    };

    const handleSubmit = async (formData: BadgeFormData) => {
        setIsSubmitting(true);

        // Simulate API call (Phase 1 - mock data)
        setTimeout(() => {
            try {
                // Mock success response
                const selectedEmployee = mockEmployees.find((emp) => emp.id === formData.employee_id);

                setSubmitResult({
                    success: true,
                    message: `Badge successfully issued to ${selectedEmployee?.name}`,
                    badgeData: {
                        employeeName: selectedEmployee?.name,
                        employeeId: selectedEmployee?.employee_id,
                        cardUid: formData.card_uid,
                        cardType: formData.card_type,
                        expiresAt: formData.expires_at,
                        issuedAt: new Date().toISOString(),
                    },
                });

                setIsSubmitting(false);
                setIsModalOpen(false);

                // Auto-clear success message after 5 seconds
                setTimeout(() => {
                    setSubmitResult(null);
                }, 5000);
            } catch {
                setSubmitResult({
                    success: false,
                    message: 'Failed to issue badge. Please try again.',
                });
                setIsSubmitting(false);
            }
        }, 1000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Issue New Badge" />

            <div className="container mx-auto py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/hr/timekeeping/badges">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Badges
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">Issue New Badge</h1>
                            <p className="text-muted-foreground mt-1">
                                Assign an RFID badge to an employee
                            </p>
                        </div>
                    </div>
                </div>

                {/* Success/Error Alert */}
                {submitResult && (
                    <Alert className={submitResult.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}>
                        <div className="flex gap-3">
                            {submitResult.success ? (
                                <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0" />
                            ) : (
                                <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0" />
                            )}
                            <div>
                                <AlertTitle className={submitResult.success ? 'text-green-900' : 'text-red-900'}>
                                    {submitResult.success ? 'Badge Issued Successfully' : 'Error'}
                                </AlertTitle>
                                <AlertDescription className={submitResult.success ? 'text-green-800' : 'text-red-800'}>
                                    {submitResult.message}
                                </AlertDescription>
                                {submitResult.badgeData && (
                                    <div className="mt-3 bg-white/50 rounded p-3 text-sm space-y-1">
                                        <p>
                                            <strong>Employee:</strong> {submitResult.badgeData.employeeName} ({submitResult.badgeData.employeeId})
                                        </p>
                                        <p>
                                            <strong>Card UID:</strong>{' '}
                                            <code className="font-mono">{submitResult.badgeData.cardUid}</code>
                                        </p>
                                        <p>
                                            <strong>Card Type:</strong> {submitResult.badgeData.cardType}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </Alert>
                )}

                {/* Main Content */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span>Issue New Badge</span>
                            <Badge variant="outline">Phase 1 - Task 1.3</Badge>
                        </CardTitle>
                        <CardDescription>
                            Click the button below to open the badge issuance form modal
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-3">
                            <h3 className="font-semibold text-sm">Available Features:</h3>
                            <ul className="list-disc list-inside space-y-2 text-sm text-muted-foreground">
                                <li>✅ Employee selection with searchable autocomplete</li>
                                <li>✅ Card UID input with format validation (XX:XX:XX:XX:XX)</li>
                                <li>✅ Mock RFID scanner with 2-second scanning animation</li>
                                <li>✅ Card type selector (Mifare, DESFire, EM4100)</li>
                                <li>✅ Optional expiration date picker with future date validation</li>
                                <li>✅ Optional issue notes textarea</li>
                                <li>✅ Employee acknowledgment checkbox with signature field</li>
                                <li>✅ Badge tested checkbox</li>
                                <li>✅ Existing badge warning with replacement option</li>
                                <li>✅ Real-time form validation with error messages</li>
                            </ul>
                        </div>

                        <div className="pt-4 border-t">
                            <Button onClick={handleModalOpen} size="lg" className="gap-2">
                                <Plus className="h-5 w-5" />
                                Open Badge Issuance Form
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Instructions Card */}
                <Card className="bg-blue-50 border-blue-200">
                    <CardHeader>
                        <CardTitle className="text-base">How to Use</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p>
                            <strong>1. Open the form:</strong> Click the "Open Badge Issuance Form" button above
                        </p>
                        <p>
                            <strong>2. Select an employee:</strong> Search by name, employee ID, or department
                        </p>
                        <p>
                            <strong>3. Enter badge information:</strong> Manually enter Card UID or use the scanner
                        </p>
                        <p>
                            <strong>4. Mock scanner (Phase 1):</strong> Click the QR code icon to simulate scanning
                        </p>
                        <p>
                            <strong>5. Optional fields:</strong> Fill in expiration date, notes, and verification checkboxes
                        </p>
                        <p>
                            <strong>6. Submit:</strong> Click "Issue Badge" to save the badge assignment
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Badge Issuance Modal */}
            <BadgeIssuanceModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSubmit={handleSubmit}
                employees={mockEmployees}
                isLoading={isSubmitting}
                existingBadgeUids={existingBadgeUids}
            />
        </AppLayout>
    );
}
