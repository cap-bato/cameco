import React, { useState } from 'react';
import { router } from '@inertiajs/react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertCircle,
    CheckCircle,
    Download,
    FileText,
    Loader,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { BIRPeriod } from '@/types/bir-pages';

import { BIR1601CEmployee } from '@/types/bir-pages';

interface BIR1601CGeneratorProps {
    period?: BIRPeriod;
    periodId: string;
    employees?: BIR1601CEmployee[];
}

/**
 * BIR 1601C Generator Component
 * Monthly Remittance of Income Tax Withheld
 * For submitting employee income tax withholding to BIR
 */
export const BIR1601CGenerator: React.FC<BIR1601CGeneratorProps> = ({ period, periodId, employees = [] }) => {
    const [isGenerating, setIsGenerating] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [statusMessage, setStatusMessage] = useState<{
        type: 'success' | 'error' | 'info';
        message: string;
    } | null>(null);
    const [rdo, setRdo] = useState('2421'); // Default RDO for Metro Manila

    const filteredEmployees = employees.filter((emp) =>
        emp.employee_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        emp.employee_id.toLowerCase().includes(searchTerm.toLowerCase()) ||
        emp.tin.includes(searchTerm)
    );

    const summary = {
        total_employees: employees.length,
        total_compensation: employees.reduce((sum, e) => sum + e.gross_compensation, 0),
        total_withholding_tax: employees.reduce((sum, e) => sum + e.withholding_tax, 0),
        average_tax_rate: employees.length > 0
            ? ((employees.reduce((sum, e) => sum + e.withholding_tax, 0) /
                employees.reduce((sum, e) => sum + e.gross_compensation, 0)) * 100).toFixed(2)
            : '0.00',
    };

    const handleGenerate = () => {
        setIsGenerating(true);
        router.post(
            `/payroll/government/bir/generate-1601c/${periodId}`,
            {
                rdo_code: rdo,
            },
            {
                onSuccess: () => {
                    setStatusMessage({
                        type: 'success',
                        message: 'Form 1601C generated successfully',
                    });
                    setTimeout(() => setStatusMessage(null), 3000);
                },
                onError: () => {
                    setStatusMessage({
                        type: 'error',
                        message: 'Failed to generate form. Please try again.',
                    });
                },
                onFinish: () => setIsGenerating(false),
            }
        );
    };

    const handleDownload = () => {
        router.get(`/payroll/government/bir/download-1601c/${periodId}`);
    };

    return (
        <div className="space-y-6">
            {/* Status Alert */}
            {statusMessage && (
                <Alert variant={statusMessage.type === 'error' ? 'destructive' : 'default'}>
                    {statusMessage.type === 'error' ? (
                        <AlertCircle className="h-4 w-4" />
                    ) : (
                        <CheckCircle className="h-4 w-4" />
                    )}
                    <AlertTitle>{statusMessage.type === 'error' ? 'Error' : 'Success'}</AlertTitle>
                    <AlertDescription>{statusMessage.message}</AlertDescription>
                </Alert>
            )}

            {/* Form Header */}
            <Card>
                <CardHeader>
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <CardTitle>BIR Form 1601C</CardTitle>
                            <CardDescription>
                                Monthly Remittance of Income Tax Withheld on Wages
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2 bg-blue-50 px-3 py-2 rounded">
                            <FileText className="w-4 h-4 text-blue-600" />
                            <span className="text-sm font-medium text-blue-900">
                                {period?.name || 'No period selected'}
                            </span>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Configuration Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Report Configuration</CardTitle>
                    <CardDescription>Set RDO and other parameters</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="rdo">Revenue District Office (RDO)</Label>
                            <Select value={rdo} onValueChange={setRdo}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="2421">2421 - NCR Metro Manila</SelectItem>
                                    <SelectItem value="0541">0541 - Cebu City</SelectItem>
                                    <SelectItem value="1451">1451 - Davao City</SelectItem>
                                    <SelectItem value="3701">3701 - Quezon City</SelectItem>
                                    <SelectItem value="0221">0221 - Makati City</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-gray-500">
                                Select the RDO where you will file the report
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tin">Company TIN</Label>
                            <Input
                                id="tin"
                                type="text"
                                value="123456789010"
                                disabled
                                className="bg-gray-50"
                            />
                            <p className="text-xs text-gray-500">Your company tax identification number</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-500 mb-1">Total Employees</p>
                            <p className="text-2xl font-bold">{summary.total_employees}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-500 mb-1">Total Compensation</p>
                            <p className="text-2xl font-bold">₱{(summary.total_compensation / 1000).toFixed(1)}K</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-500 mb-1">Total Tax Withheld</p>
                            <p className="text-2xl font-bold">₱{(summary.total_withholding_tax / 1000).toFixed(1)}K</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <p className="text-sm text-gray-500 mb-1">Average Tax Rate</p>
                            <p className="text-2xl font-bold">{summary.average_tax_rate}%</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Employee Details */}
            <Card>
                <CardHeader>
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <CardTitle className="text-base">Employee Withholding Details</CardTitle>
                            <CardDescription>Income tax withheld per employee</CardDescription>
                        </div>
                        <div className="flex gap-2 w-full md:w-auto">
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
                                    <TableHead>Employee ID</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>TIN</TableHead>
                                    <TableHead className="text-right">Gross Compensation</TableHead>
                                    <TableHead className="text-right">Tax Withheld</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredEmployees.map((emp) => (
                                    <TableRow key={emp.employee_id}>
                                        <TableCell className="font-mono text-sm">{emp.employee_id}</TableCell>
                                        <TableCell className="font-medium">{emp.employee_name}</TableCell>
                                        <TableCell className="font-mono text-sm">{emp.tin}</TableCell>
                                        <TableCell className="text-right">
                                            ₱{emp.gross_compensation.toLocaleString('en-PH')}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            ₱{emp.withholding_tax.toLocaleString('en-PH')}
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
                            onClick={handleGenerate}
                            disabled={isGenerating}
                            className="flex-1"
                        >
                            {isGenerating ? (
                                <>
                                    <Loader className="w-4 h-4 mr-2 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <FileText className="w-4 h-4 mr-2" />
                                    Generate Form 1601C
                                </>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={handleDownload}
                            className="flex-1"
                        >
                            <Download className="w-4 h-4 mr-2" />
                            Download
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Information */}
            <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertTitle>BIR Form 1601C Information</AlertTitle>
                <AlertDescription>
                    This form reports monthly income tax withheld from employee wages. Must be filed
                    with the BIR within 10 days from the end of the month being reported.
                </AlertDescription>
            </Alert>
        </div>
    );
};
