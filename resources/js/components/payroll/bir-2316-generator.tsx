import React, { useState } from 'react';
import { BIRPeriod, BIR2316Certificate } from '@/types/bir-pages';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    AlertCircle,
    // CheckCircle,
    Download,
    FileText,
    Search,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { router } from '@inertiajs/react';

interface BIR2316GeneratorProps {
    period?: BIRPeriod;
    periodId: string;
    certificates?: BIR2316Certificate[];
}

/**
 * BIR 2316 Generator Component
 * Certificate of Compensation Income Withheld on Wages
import { BIRPeriod, BIR2316Certificate } from '@/types/bir-pages';
 */
export const BIR2316Generator: React.FC<BIR2316GeneratorProps> = ({ certificates = [], periodId }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [generateError, setGenerateError] = useState<string | null>(null);
    const [generateSuccess, setGenerateSuccess] = useState(false);
    // Inertia router is now imported at the top

    // Use real certificates from props (default empty array)
    const filteredCertificates = certificates.filter((cert) =>
        cert.employee_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        cert.employee_id.toLowerCase().includes(searchTerm.toLowerCase()) ||
        cert.tin.includes(searchTerm)
    );

    const summary = {
        total_certificates: certificates.length,
        total_gross_compensation: certificates.reduce((sum, c) => sum + c.gross_compensation, 0),
        total_taxable_compensation: certificates.reduce((sum, c) => sum + c.taxable_compensation, 0),
        total_tax_withheld: certificates.reduce((sum, c) => sum + c.tax_withheld, 0),
    };

    return (
        <div className="space-y-6">
                {/* Form Header */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <CardTitle>BIR Form 2316</CardTitle>
                                <CardDescription>
                                    Certificate of Compensation Income Withheld on Wages
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2 bg-green-50 px-3 py-2 rounded">
                                <FileText className="w-4 h-4 text-green-600" />
                                <span className="text-sm font-medium text-green-900">
                                    Tax Year {new Date().getFullYear()}
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-sm text-gray-500 mb-1">Total Employees</p>
                                <p className="text-2xl font-bold">{summary.total_certificates}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-sm text-gray-500 mb-1">Total Gross Compensation</p>
                                <p className="text-2xl font-bold">
                                    ₱{(summary.total_gross_compensation / 1000000).toFixed(2)}M
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-sm text-gray-500 mb-1">Total Taxable Compensation</p>
                                <p className="text-2xl font-bold">
                                    ₱{(summary.total_taxable_compensation / 1000000).toFixed(2)}M
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center">
                                <p className="text-sm text-gray-500 mb-1">Total Tax Withheld</p>
                                <p className="text-2xl font-bold">
                                    ₱{(summary.total_tax_withheld / 1000000).toFixed(2)}M
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Employee Certificates */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <CardTitle className="text-base">Employee Certificates</CardTitle>
                                <CardDescription>Annual certificates per employee</CardDescription>
                            </div>
                            <div className="flex items-center gap-2 w-full md:w-auto">
                                <Search className="w-4 h-4 text-gray-400" />
                                <Input
                                    placeholder="Search employee..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="flex-1"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee Name</TableHead>
                                        <TableHead>Employee ID</TableHead>
                                        <TableHead className="text-right">Gross Compensation</TableHead>
                                        <TableHead className="text-right">Non-Taxable</TableHead>
                                        <TableHead className="text-right">Taxable Compensation</TableHead>
                                        <TableHead className="text-right">Tax Withheld</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredCertificates.map((cert) => (
                                        <TableRow key={cert.id}>
                                            <TableCell className="font-medium">{cert.employee_name}</TableCell>
                                            <TableCell className="font-mono text-sm">{cert.employee_id}</TableCell>
                                            <TableCell className="text-right">
                                                ₱{cert.gross_compensation.toLocaleString('en-PH')}
                                            </TableCell>
                                            <TableCell className="text-right text-green-600">
                                                ₱{cert.non_taxable_compensation.toLocaleString('en-PH')}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                ₱{cert.taxable_compensation.toLocaleString('en-PH')}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                ₱{cert.tax_withheld.toLocaleString('en-PH')}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col md:flex-row gap-3">
                            <Button
                                onClick={async () => {
                                    setIsGenerating(true);
                                    setGenerateError(null);
                                    setGenerateSuccess(false);
                                    try {
                                        await router.post(
                                            `/payroll/government/bir/generate-2316/${encodeURIComponent((periodId || '').toString())}`,
                                            {},
                                            {
                                                onSuccess: () => {
                                                    setGenerateSuccess(true);
                                                    setTimeout(() => setGenerateSuccess(false), 4000);
                                                    // Refresh the page to show new certificates
                                                    router.reload({ only: ['bir_2316_certificates'] });
                                                },
                                                onError: (errors: any) => {
                                                    setGenerateError(errors || 'Failed to generate certificates');
                                                },
                                                onFinish: () => setIsGenerating(false),
                                            }
                                        );
                                    } catch (err: any) {
                                        setGenerateError(err?.message || 'Failed to generate certificates');
                                        setIsGenerating(false);
                                    }
                                }}
                                disabled={isGenerating}
                                className="flex-1"
                            >
                                {isGenerating ? 'Generating...' : 'Generate'}
                            </Button>
                            <a
                                href={`/payroll/government/bir/download-2316/${encodeURIComponent((periodId || '').toString())}`}
                                className="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md bg-white text-gray-900 hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                download
                                target="_blank"
                                rel="noopener noreferrer"
                                style={{ textAlign: 'center', textDecoration: 'none' }}
                            >
                                <Download className="w-4 h-4 mr-2" />
                                Download Certificates
                            </a>
                        </div>
                        {/* Generation status messages */}
                        {generateError && (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-3 mt-4">
                                <p className="text-sm text-red-800">{generateError}</p>
                            </div>
                        )}
                        {generateSuccess && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-3 mt-4">
                                <p className="text-sm text-green-800">✓ Certificates generated successfully</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Information */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>BIR Form 2316 Information</AlertTitle>
                    <AlertDescription>
                        This form certifies the annual compensation income and taxes withheld from each
                        employee. Must be issued to employees on or before January 31st of the following
                        tax year and filed with the BIR.
                    </AlertDescription>
                </Alert>

                {/* Important Notes */}
                <Card className="bg-blue-50 border-blue-200">
                    <CardHeader>
                        <CardTitle className="text-sm">Important Notes</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-gray-700 space-y-2">
                        <p>
                            • Non-taxable compensation should include 13th month pay and other allowances
                            within statutory limits
                        </p>
                        <p>• Ensure all employee TINs and addresses are complete and accurate</p>
                        <p>
                            • Taxable compensation should match employee annual gross wages less non-taxable
                            items
                        </p>
                        <p>• Keep copies of Form 2316 for at least 3 years for audit purposes</p>
                    </CardContent>
                </Card>
        </div>
    );
}
