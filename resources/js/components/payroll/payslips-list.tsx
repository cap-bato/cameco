import React from 'react';
import { Download, Mail, Printer, Eye, CheckCircle, XCircle, Clock, Send } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import type { Payslip } from '@/types/payroll-pages';

interface PayslipsListProps {
    payslips: Payslip[];
    selectedPayslips: number[];
    onSelectionChange: (ids: number[]) => void;
    onDownload?: (id: number) => void;
    onEmail?: (id: number) => void;
    onView?: (id: number) => void;
    onPrint?: (id: number) => void;
}

export function PayslipsList({
    payslips,
    selectedPayslips,
    onSelectionChange,
    onDownload,
    onEmail,
    onView,
    onPrint,
}: PayslipsListProps) {
    const formatPeso = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const formatDate = (date: string | null) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (status) {
            case 'acknowledged':
                return 'default';
            case 'sent':
                return 'secondary';
            case 'generated':
                return 'outline';
            case 'failed':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'acknowledged':
                return <CheckCircle className="mr-1 h-3 w-3" />;
            case 'sent':
                return <Send className="mr-1 h-3 w-3" />;
            case 'generated':
                return <Clock className="mr-1 h-3 w-3" />;
            case 'failed':
                return <XCircle className="mr-1 h-3 w-3" />;
            default:
                return null;
        }
    };

    const getDistributionMethodBadge = (method: string) => {
        switch (method) {
            case 'email':
                return (
                    <Badge variant="outline" className="gap-1">
                        <Mail className="h-3 w-3" />
                        Email
                    </Badge>
                );
            case 'portal':
                return (
                    <Badge variant="outline" className="gap-1">
                        <Download className="h-3 w-3" />
                        Portal
                    </Badge>
                );
            case 'printed':
                return (
                    <Badge variant="outline" className="gap-1">
                        <Printer className="h-3 w-3" />
                        Printed
                    </Badge>
                );
            default:
                return <Badge variant="outline">Unknown</Badge>;
        }
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            const allIds = payslips.map((p) => Number(p.id));
            onSelectionChange(allIds);
        } else {
            onSelectionChange([]);
        }
    };

    const handleSelectOne = (id: number, checked: boolean) => {
        if (checked) {
            onSelectionChange([...selectedPayslips, id]);
        } else {
            onSelectionChange(selectedPayslips.filter((selectedId) => selectedId !== id));
        }
    };

    const allSelected = payslips.length > 0 && selectedPayslips.length === payslips.length;
    const someSelected = selectedPayslips.length > 0 && selectedPayslips.length < payslips.length;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-lg">Payslips</CardTitle>
            </CardHeader>
            <CardContent>
                {payslips.length > 0 ? (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-gray-50">
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={allSelected}
                                            onCheckedChange={handleSelectAll}
                                            aria-label="Select all"
                                            className={someSelected ? 'data-[state=checked]:bg-gray-400' : ''}
                                        />
                                    </TableHead>
                                    <TableHead className="font-semibold">Payslip #</TableHead>
                                    <TableHead className="font-semibold">Employee</TableHead>
                                    <TableHead className="font-semibold">Period</TableHead>
                                    <TableHead className="text-right font-semibold">Net Pay</TableHead>
                                    <TableHead className="font-semibold">Distribution</TableHead>
                                    <TableHead className="font-semibold">Status</TableHead>
                                    <TableHead className="font-semibold">Generated</TableHead>
                                    <TableHead className="text-right font-semibold">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payslips.map((payslip) => (
                                    <TableRow key={payslip.id} className="hover:bg-gray-50">
                                        <TableCell>
                                            <Checkbox
                                                checked={selectedPayslips.includes(Number(payslip.id))}
                                                onCheckedChange={(checked) =>
                                                    handleSelectOne(Number(payslip.id), checked as boolean)
                                                }
                                                aria-label={`Select ${payslip.payslip_number}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-xs font-medium">
                                            {payslip.payslip_number}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col">
                                                <span className="font-semibold">{payslip.employee_name}</span>
                                                <span className="text-xs text-gray-600">
                                                    {payslip.employee_number} • {payslip.department}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">{payslip.period_name}</TableCell>
                                        <TableCell className="text-right font-semibold text-green-700">
                                            {formatPeso(payslip.net_pay)}
                                        </TableCell>
                                        <TableCell>{getDistributionMethodBadge(payslip.distribution_method)}</TableCell>
                                        <TableCell>
                                            <Badge variant={getStatusBadgeVariant(payslip.status)} className="gap-1">
                                                {getStatusIcon(payslip.status)}
                                                {payslip.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-gray-600">
                                            {formatDate(payslip.generated_at)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-2">
                                                {onView && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onView(Number(payslip.id))}
                                                        title="Preview"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {onDownload && payslip.pdf_file_path && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onDownload(Number(payslip.id))}
                                                        title="Download PDF"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {onEmail && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onEmail(Number(payslip.id))}
                                                        title="Send Email"
                                                        disabled={payslip.distribution_method === 'email' && !!payslip.distributed_at}
                                                    >
                                                        <Mail className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {onPrint && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onPrint(Number(payslip.id))}
                                                        title="Print"
                                                    >
                                                        <Printer className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <div className="rounded-full bg-gray-100 p-3">
                            <Download className="h-6 w-6 text-gray-400" />
                        </div>
                        <h3 className="mt-4 text-sm font-semibold text-gray-900">No payslips found</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Generate payslips for a period to see them here.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
