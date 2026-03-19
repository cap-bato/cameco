import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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



interface CreateBadgeProps {
    employees: Employee[];
    existingBadgeUids: string[];
}

export default function CreateBadge({ employees, existingBadgeUids }: CreateBadgeProps) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitResult, setSubmitResult] = useState<{
        success: boolean;
        message: string;
    } | null>(null);

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

    const handleSubmit = (formData: BadgeFormData) => {
        setIsSubmitting(true);

        const selectedEmployee = employees.find((emp) => emp.id === formData.employee_id);

        router.post(
            route('hr.timekeeping.badges.store'),
            {
                employee_id:               formData.employee_id,
                card_uid:                  formData.card_uid,
                card_type:                 formData.card_type,
                expires_at:                formData.expires_at ?? null,
                notes:                     formData.issue_notes ?? null, // map issue_notes → notes
                acknowledgement_signature: formData.acknowledgement_signature ?? null,
                replace_existing:          selectedEmployee?.badge?.is_active ? true : false,
            },
            {
                onSuccess: () => {
                    setIsSubmitting(false);
                    setIsModalOpen(false);
                    // Backend redirects to badges.index with a flash success message
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    setSubmitResult({
                        success: false,
                        message: errors.error
                            ?? errors.employee_id
                            ?? errors.card_uid
                            ?? 'Failed to issue badge. Please try again.',
                    });
                },
            }
        );
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
                {/* Success/Error Alert removed: backend handles flash message on redirect */}

                {/* Main Content */}
                <Card>
                    <CardHeader>
                        <CardTitle>Issue New Badge</CardTitle>
                        <CardDescription>
                            Open the badge issuance form to assign an RFID badge to an employee.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="pt-4 border-t">
                            <Button onClick={handleModalOpen} size="lg" className="gap-2">
                                <Plus className="h-5 w-5" />
                                Open Badge Issuance Form
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Badge Issuance Modal */}
            <BadgeIssuanceModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSubmit={handleSubmit}
                employees={employees}
                isLoading={isSubmitting}
                existingBadgeUids={existingBadgeUids}
            />
        </AppLayout>
    );
}
