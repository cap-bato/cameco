import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Save } from 'lucide-react';
import { CompanyForm, type CompanyFormData } from '@/components/admin/company-form';
import { useState } from 'react';
import { useToast } from '@/hooks/use-toast';

interface CompanyData {
    // Basic Information
    name: string;
    address: string;
    city: string;
    province: string;
    postal_code: string;
    phone: string;
    email: string;
    website: string;
    
    // Tax & Registration
    tin: string;
    bir_registration_number: string;
    bir_registration_date: string;
    business_permit_number: string;
    sec_registration_number: string;
    
    // Government Numbers
    sss_number: string;
    philhealth_number: string;
    pagibig_number: string;
    
    // Logo
    logo_url: string | null;
}

interface CompanyIndexProps {
    company: CompanyData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Company Setup',
        href: '/admin/company',
    },
];

export default function CompanyIndex({ company }: CompanyIndexProps) {
    const { toast } = useToast();
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (data: CompanyFormData) => {
        setIsSubmitting(true);

        // Use Inertia router to submit the form
        router.put('/admin/company', data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Company information updated successfully.',
                    variant: 'default',
                });
                setIsSubmitting(false);
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update company information. Please check the form and try again.',
                    variant: 'destructive',
                });
                setIsSubmitting(false);
                console.error('Company update errors:', errors);
            },
        });
    };

    const handleLogoUpload = (file: File) => {
        const formData = new FormData();
        formData.append('logo', file);

        router.post('/admin/company/logo', formData, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Company logo uploaded successfully.',
                    variant: 'default',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to upload logo. Please try again.',
                    variant: 'destructive',
                });
                console.error('Logo upload errors:', errors);
            },
        });
    };

    const handleLogoDelete = () => {
        router.delete('/admin/company/logo', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Company logo deleted successfully.',
                    variant: 'default',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete logo. Please try again.',
                    variant: 'destructive',
                });
                console.error('Logo deletion errors:', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company Setup" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit('/admin/dashboard')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Dashboard
                        </Button>
                    </div>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Company Setup</h1>
                        <p className="text-muted-foreground mt-1">
                            Configure company information, tax details, and government registration numbers
                        </p>
                    </div>
                </div>

                {/* Info Card */}
                <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/20">
                    <CardHeader>
                        <CardTitle className="text-base">Setup Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Complete all required fields to finish company setup. This information will be used for:
                        </p>
                        <ul className="list-disc list-inside space-y-1 ml-2">
                            <li>Government compliance and reporting (SSS, PhilHealth, Pag-IBIG, BIR)</li>
                            <li>Official documents, payslips, and employee correspondence</li>
                            <li>Company branding on system-generated reports and forms</li>
                        </ul>
                        <p className="text-xs pt-2">
                            <strong>Note:</strong> All changes are logged for audit purposes and require Office Admin permissions.
                        </p>
                    </CardContent>
                </Card>

                {/* Company Form */}
                <CompanyForm
                    initialData={company}
                    onSubmit={handleSubmit}
                    onLogoUpload={handleLogoUpload}
                    onLogoDelete={handleLogoDelete}
                    isSubmitting={isSubmitting}
                />
            </div>
        </AppLayout>
    );
}
