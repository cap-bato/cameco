import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { FileText, ArrowLeft, AlertCircle, Loader2 } from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

interface DocumentType {
    value: string;
    label: string;
    description: string;
}

interface RequestFormPageProps {
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    documentTypes: DocumentType[];
}

// ============================================================================
// Main Component
// ============================================================================

export default function DocumentRequestForm({
    employee,
    documentTypes,
}: RequestFormPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        document_type: '',
        period: '',
        purpose: '',
    });

    const [selectedType, setSelectedType] = useState<DocumentType | null>(null);

    const handleDocumentTypeChange = (value: string) => {
        setData('document_type', value);
        const found = documentTypes.find((t) => t.value === value);
        setSelectedType(found || null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/employee/documents/request');
    };

    return (
        <AppLayout>
            <Head title="Request Document" />

            <div className="space-y-6">
                {/* Header with Back Button */}
                <div className="flex items-center gap-4">
                    <Button
                        onClick={() => window.history.back()}
                        variant="outline"
                        size="icon"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Request Document</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Submit a request for documents from HR
                        </p>
                    </div>
                </div>

                {/* Employee Info Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label className="text-gray-600">Employee Name</Label>
                                <p className="text-lg font-medium mt-1">{employee.name}</p>
                            </div>
                            <div>
                                <Label className="text-gray-600">Employee Number</Label>
                                <p className="text-lg font-medium mt-1">{employee.employee_number}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Form Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Select Document Type</CardTitle>
                        <CardDescription>
                            Choose the document you want to request
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Document Type Selection */}
                            <div className="space-y-3">
                                <Label htmlFor="document_type" className="text-base font-semibold">
                                    Document Type <span className="text-red-500">*</span>
                                </Label>

                                <Select value={data.document_type} onValueChange={handleDocumentTypeChange}>
                                    <SelectTrigger id="document_type">
                                        <SelectValue placeholder="Select a document type..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {documentTypes.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                <div className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4" />
                                                    {type.label}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {errors.document_type && (
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>{errors.document_type}</AlertDescription>
                                    </Alert>
                                )}

                                {selectedType && (
                                    <p className="text-sm text-gray-600 mt-2">
                                        {selectedType.description}
                                    </p>
                                )}
                            </div>

                            {/* Period Field (for Payslip) */}
                            {data.document_type === 'payslip' && (
                                <div className="space-y-3">
                                    <Label htmlFor="period" className="text-base font-semibold">
                                        Select Period <span className="text-red-500">*</span>
                                    </Label>

                                    <Input
                                        id="period"
                                        type="month"
                                        value={data.period}
                                        onChange={(e) => setData('period', e.target.value)}
                                        required
                                    />

                                    {errors.period && (
                                        <Alert variant="destructive">
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertDescription>{errors.period}</AlertDescription>
                                        </Alert>
                                    )}

                                    <p className="text-sm text-gray-600">
                                        Select the month and year for the payslip you want to request
                                    </p>
                                </div>
                            )}

                            {/* Purpose Field */}
                            <div className="space-y-3">
                                <Label htmlFor="purpose" className="text-base font-semibold">
                                    Purpose (Optional)
                                </Label>

                                <Textarea
                                    id="purpose"
                                    placeholder="Explain why you need this document (e.g., for loan application, government filing, etc.)"
                                    value={data.purpose}
                                    onChange={(e) => setData('purpose', e.target.value)}
                                    maxLength={500}
                                    rows={4}
                                />

                                <p className="text-xs text-gray-500">
                                    {data.purpose?.length || 0}/500 characters
                                </p>

                                {errors.purpose && (
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>{errors.purpose}</AlertDescription>
                                    </Alert>
                                )}
                            </div>

                            {/* Info Alert */}
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Processing Time:</strong> Document requests are typically processed within 1-2 business days. You will receive a notification once your document is ready for download.
                                </AlertDescription>
                            </Alert>

                            {/* Action Buttons */}
                            <div className="flex gap-3 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || !data.document_type}
                                    className="gap-2"
                                >
                                    {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                                    {processing ? 'Submitting...' : 'Submit Request'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Available Documents Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Available Documents</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <div>
                                <h4 className="font-semibold text-sm">Certificate of Employment</h4>
                                <p className="text-sm text-gray-600">
                                    Official document confirming your employment with the company. Commonly used for loan applications, visa applications, and government requirements.
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold text-sm">Payslip</h4>
                                <p className="text-sm text-gray-600">
                                    Detailed salary statement for a specific month. Shows gross pay, deductions, and net pay. Required for many administrative processes.
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold text-sm">BIR Form 2316</h4>
                                <p className="text-sm text-gray-600">
                                    Tax form from the Bureau of Internal Revenue showing your annual compensation and tax withholdings. Required for personal income tax filing.
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold text-sm">Government Compliance Documents</h4>
                                <p className="text-sm text-gray-600">
                                    Documents related to SSS, PhilHealth, and Pag-IBIG contributions. Contact HR if you need specific compliance documentation.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
