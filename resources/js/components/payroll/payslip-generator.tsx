import React, { useState } from 'react';
import { FileText, Mail, Globe, Printer, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { PayslipGenerationRequest } from '@/types/payroll-pages';

interface PayslipGeneratorProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onGenerate: (data: PayslipGenerationRequest) => void;
    periods: Array<{
        id: number;
        period_name: string;
        period_start: string;
        period_end: string;
        payment_date: string;
    }>;
    employees?: Array<{
        id: number;
        employee_number: string;
        full_name: string;
        department: string;
    }>;
    isLoading?: boolean;
}

export function PayslipGenerator({
    open,
    onOpenChange,
    onGenerate,
    periods,
    employees,
    isLoading = false,
}: PayslipGeneratorProps) {
    const [periodId, setPeriodId] = useState<number | null>(null);
    const [distributionMethod, setDistributionMethod] = useState<'email' | 'portal' | 'printed'>('email');
    const [regenerate, setRegenerate] = useState(false);
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [generationScope, setGenerationScope] = useState<'all' | 'selected'>('all');

    const handleGenerate = () => {
        if (!periodId) {
            return;
        }

        const data: PayslipGenerationRequest = {
            period_id: periodId,
            distribution_method: distributionMethod,
            regenerate,
        };

        if (generationScope === 'selected' && selectedEmployees.length > 0) {
            data.employee_ids = selectedEmployees;
        }

        onGenerate(data);
    };

    const handleEmployeeSelection = (employeeId: number, checked: boolean) => {
        if (checked) {
            setSelectedEmployees([...selectedEmployees, employeeId]);
        } else {
            setSelectedEmployees(selectedEmployees.filter((id) => id !== employeeId));
        }
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked && employees) {
            setSelectedEmployees(employees.map((e) => e.id));
        } else {
            setSelectedEmployees([]);
        }
    };

    const selectedPeriod = periods.find((p) => p.id === periodId);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Generate Payslips
                    </DialogTitle>
                    <DialogDescription>
                        Generate DOLE-compliant payslips with detailed earnings and deductions breakdown.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Period Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="period">Payroll Period *</Label>
                        <Select
                            value={periodId?.toString()}
                            onValueChange={(value) => setPeriodId(Number(value))}
                            disabled={isLoading}
                        >
                            <SelectTrigger id="period">
                                <SelectValue placeholder="Select payroll period" />
                            </SelectTrigger>
                            <SelectContent>
                                {periods.map((period) => (
                                    <SelectItem key={period.id} value={period.id.toString()}>
                                        {period.period_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {selectedPeriod && (
                            <p className="text-xs text-gray-600">
                                Period: {selectedPeriod.period_start} to {selectedPeriod.period_end} • Pay Date:{' '}
                                {selectedPeriod.payment_date}
                            </p>
                        )}
                    </div>

                    {/* Distribution Method */}
                    <div className="space-y-3">
                        <Label>Distribution Method *</Label>
                        <RadioGroup
                            value={distributionMethod}
                            onValueChange={(value: string) => setDistributionMethod(value as 'email' | 'portal' | 'printed')}
                            disabled={isLoading}
                        >
                            <div className="flex items-center space-x-2 rounded-lg border p-3 hover:bg-gray-50">
                                <RadioGroupItem value="email" id="email" />
                                <Label htmlFor="email" className="flex flex-1 cursor-pointer items-center gap-2">
                                    <Mail className="h-4 w-4 text-blue-600" />
                                    <div className="flex-1">
                                        <div className="font-medium">Email</div>
                                        <div className="text-xs text-gray-600">
                                            Send payslips to employee email addresses
                                        </div>
                                    </div>
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2 rounded-lg border p-3 hover:bg-gray-50">
                                <RadioGroupItem value="portal" id="portal" />
                                <Label htmlFor="portal" className="flex flex-1 cursor-pointer items-center gap-2">
                                    <Globe className="h-4 w-4 text-green-600" />
                                    <div className="flex-1">
                                        <div className="font-medium">Employee Portal</div>
                                        <div className="text-xs text-gray-600">
                                            Make available for download in employee portal
                                        </div>
                                    </div>
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2 rounded-lg border p-3 hover:bg-gray-50">
                                <RadioGroupItem value="printed" id="printed" />
                                <Label htmlFor="printed" className="flex flex-1 cursor-pointer items-center gap-2">
                                    <Printer className="h-4 w-4 text-gray-600" />
                                    <div className="flex-1">
                                        <div className="font-medium">Print Queue</div>
                                        <div className="text-xs text-gray-600">Add to print queue for hard copies</div>
                                    </div>
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Generation Scope */}
                    <div className="space-y-3">
                        <Label>Generation Scope</Label>
                        <RadioGroup
                            value={generationScope}
                            onValueChange={(value: string) => setGenerationScope(value as 'all' | 'selected')}
                            disabled={isLoading}
                        >
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="all" id="all" />
                                <Label htmlFor="all" className="cursor-pointer font-normal">
                                    All employees in period
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="selected" id="selected" />
                                <Label htmlFor="selected" className="cursor-pointer font-normal">
                                    Selected employees only
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Employee Selection (if selected scope) */}
                    {generationScope === 'selected' && employees && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Select Employees ({selectedEmployees.length} selected)</Label>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleSelectAll(selectedEmployees.length !== employees.length)}
                                >
                                    {selectedEmployees.length === employees.length ? 'Deselect All' : 'Select All'}
                                </Button>
                            </div>
                            <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border p-3">
                                {employees.map((employee) => (
                                    <div key={employee.id} className="flex items-center space-x-2">
                                        <Checkbox
                                            id={`emp-${employee.id}`}
                                            checked={selectedEmployees.includes(employee.id)}
                                            onCheckedChange={(checked) =>
                                                handleEmployeeSelection(employee.id, checked as boolean)
                                            }
                                        />
                                        <Label
                                            htmlFor={`emp-${employee.id}`}
                                            className="flex-1 cursor-pointer text-sm font-normal"
                                        >
                                            {employee.full_name} ({employee.employee_number}) - {employee.department}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Regenerate Option */}
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="regenerate"
                            checked={regenerate}
                            onCheckedChange={(checked) => setRegenerate(checked as boolean)}
                            disabled={isLoading}
                        />
                        <Label htmlFor="regenerate" className="cursor-pointer text-sm font-normal">
                            Force regenerate existing payslips
                        </Label>
                    </div>

                    {/* Info Alert */}
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>DOLE Compliance:</strong> Generated payslips include detailed breakdown of earnings,
                            deductions, and YTD totals as required by DOLE regulations.
                        </AlertDescription>
                    </Alert>

                    {regenerate && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                <strong>Warning:</strong> This will overwrite existing payslips for the selected period.
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isLoading}>
                        Cancel
                    </Button>
                    <Button onClick={handleGenerate} disabled={!periodId || isLoading}>
                        {isLoading ? (
                            <>Generating...</>
                        ) : (
                            <>
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Generate Payslips
                            </>
                        )}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
