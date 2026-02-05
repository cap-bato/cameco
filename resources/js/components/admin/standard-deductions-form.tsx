import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Save, Info, AlertTriangle, Clock, Ban } from 'lucide-react';
import { useState } from 'react';

export interface StandardDeductionsData {
    late_deduction_type: 'per_minute' | 'per_bracket' | 'fixed';
    late_deduction_amount: number;
    undertime_deduction_type: 'proportional' | 'fixed' | 'none';
    undertime_deduction_amount: number;
    absence_deduction_per_day: number;
    lwop_deduction_rate: number; // Leave Without Pay deduction rate (0-1)
}

interface StandardDeductionsFormProps {
    initialData: StandardDeductionsData;
    onSubmit: (data: StandardDeductionsData) => void;
}

export function StandardDeductionsForm({ initialData, onSubmit }: StandardDeductionsFormProps) {
    const [formData, setFormData] = useState<StandardDeductionsData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof StandardDeductionsData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleInputChange = (field: keyof StandardDeductionsData, value: string | number) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof StandardDeductionsData, string>> = {};

        // Late deduction validation
        if (formData.late_deduction_amount < 0) {
            newErrors.late_deduction_amount = 'Late deduction amount cannot be negative';
        }

        // Undertime deduction validation
        if (formData.undertime_deduction_type !== 'none') {
            if (formData.undertime_deduction_amount < 0) {
                newErrors.undertime_deduction_amount = 'Undertime deduction amount cannot be negative';
            }
        }

        // Absence deduction validation
        if (formData.absence_deduction_per_day < 0 || formData.absence_deduction_per_day > 1) {
            newErrors.absence_deduction_per_day = 'Absence deduction rate must be between 0 and 1 (0-100%)';
        }

        // LWOP deduction validation
        if (formData.lwop_deduction_rate < 0 || formData.lwop_deduction_rate > 1) {
            newErrors.lwop_deduction_rate = 'LWOP deduction rate must be between 0 and 1 (0-100%)';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = () => {
        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);
        onSubmit(formData);

        // Reset submitting state after a delay (Inertia will handle the actual redirect)
        setTimeout(() => {
            setIsSubmitting(false);
        }, 2000);
    };

    const calculateLateDeduction = () => {
        const minutesLate = 30; // Example scenario

        switch (formData.late_deduction_type) {
            case 'per_minute':
                return (minutesLate * formData.late_deduction_amount).toFixed(2);
            case 'per_bracket': {
                // Example: 1-15 mins = 1 bracket, 16-30 mins = 2 brackets
                const brackets = Math.ceil(minutesLate / 15);
                return (brackets * formData.late_deduction_amount).toFixed(2);
            }
            case 'fixed':
                return formData.late_deduction_amount.toFixed(2);
            default:
                return '0.00';
        }
    };

    const calculateUndertimeDeduction = () => {
        const minutesUndertime = 60; // Example: 1 hour undertime
        const dailyRate = 570; // Example daily rate
        const hourlyRate = dailyRate / 8;

        switch (formData.undertime_deduction_type) {
            case 'proportional':
                return ((minutesUndertime / 60) * hourlyRate).toFixed(2);
            case 'fixed':
                return formData.undertime_deduction_amount.toFixed(2);
            case 'none':
                return '0.00';
            default:
                return '0.00';
        }
    };

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> Deduction rules apply to all employees. Configure these settings carefully as they directly affect employee salaries. Late and undertime deductions must comply with Philippine labor laws and company policies.
                </AlertDescription>
            </Alert>

            {/* Late Deduction Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Late Deduction Rules
                    </CardTitle>
                    <CardDescription>Configure deductions for employees arriving late to work</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="late_deduction_type">
                                Deduction Type <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={formData.late_deduction_type}
                                onValueChange={(value) =>
                                    handleInputChange('late_deduction_type', value as 'per_minute' | 'per_bracket' | 'fixed')
                                }
                            >
                                <SelectTrigger id="late_deduction_type">
                                    <SelectValue placeholder="Select deduction type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="per_minute">Per Minute</SelectItem>
                                    <SelectItem value="per_bracket">Per Bracket (15-min increments)</SelectItem>
                                    <SelectItem value="fixed">Fixed Amount</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {formData.late_deduction_type === 'per_minute' &&
                                    'Deduct a fixed amount for each minute late'}
                                {formData.late_deduction_type === 'per_bracket' &&
                                    'Deduct in 15-minute increments (1-15 mins = 1 bracket, 16-30 mins = 2 brackets)'}
                                {formData.late_deduction_type === 'fixed' && 'Deduct a fixed amount regardless of duration'}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="late_deduction_amount">
                                Deduction Amount (₱) <span className="text-red-500">*</span>
                            </Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                <Input
                                    id="late_deduction_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={formData.late_deduction_amount}
                                    onChange={(e) =>
                                        handleInputChange('late_deduction_amount', parseFloat(e.target.value) || 0)
                                    }
                                    className={`pl-8 ${errors.late_deduction_amount ? 'border-red-500' : ''}`}
                                />
                            </div>
                            {errors.late_deduction_amount && (
                                <p className="text-sm text-red-500">{errors.late_deduction_amount}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {formData.late_deduction_type === 'per_minute' && 'Amount deducted per minute late'}
                                {formData.late_deduction_type === 'per_bracket' && 'Amount deducted per 15-minute bracket'}
                                {formData.late_deduction_type === 'fixed' && 'Fixed deduction amount'}
                            </p>
                        </div>
                    </div>

                    {/* Late Deduction Example */}
                    <Alert className="bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                        <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        <AlertDescription>
                            <strong>Example Calculation (30 minutes late):</strong>
                            <br />
                            {formData.late_deduction_type === 'per_minute' && (
                                <>
                                    30 minutes × ₱{formData.late_deduction_amount.toFixed(2)}/minute = ₱
                                    {calculateLateDeduction()}
                                </>
                            )}
                            {formData.late_deduction_type === 'per_bracket' && (
                                <>
                                    2 brackets (16-30 mins) × ₱{formData.late_deduction_amount.toFixed(2)}/bracket = ₱
                                    {calculateLateDeduction()}
                                </>
                            )}
                            {formData.late_deduction_type === 'fixed' && (
                                <>Fixed deduction: ₱{calculateLateDeduction()}</>
                            )}
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Undertime Deduction Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Undertime Deduction Rules
                    </CardTitle>
                    <CardDescription>Configure deductions for employees leaving work early</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="undertime_deduction_type">
                                Deduction Type <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={formData.undertime_deduction_type}
                                onValueChange={(value) =>
                                    handleInputChange(
                                        'undertime_deduction_type',
                                        value as 'proportional' | 'fixed' | 'none'
                                    )
                                }
                            >
                                <SelectTrigger id="undertime_deduction_type">
                                    <SelectValue placeholder="Select deduction type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="proportional">Proportional (Based on hourly rate)</SelectItem>
                                    <SelectItem value="fixed">Fixed Amount</SelectItem>
                                    <SelectItem value="none">No Deduction</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {formData.undertime_deduction_type === 'proportional' &&
                                    'Deduct based on hours/minutes missed (daily rate ÷ 8 hours)'}
                                {formData.undertime_deduction_type === 'fixed' && 'Deduct a fixed amount for any undertime'}
                                {formData.undertime_deduction_type === 'none' &&
                                    'No undertime deductions (flexible schedule)'}
                            </p>
                        </div>

                        {formData.undertime_deduction_type === 'fixed' && (
                            <div className="space-y-2">
                                <Label htmlFor="undertime_deduction_amount">
                                    Deduction Amount (₱) <span className="text-red-500">*</span>
                                </Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                        ₱
                                    </span>
                                    <Input
                                        id="undertime_deduction_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={formData.undertime_deduction_amount}
                                        onChange={(e) =>
                                            handleInputChange('undertime_deduction_amount', parseFloat(e.target.value) || 0)
                                        }
                                        className={`pl-8 ${errors.undertime_deduction_amount ? 'border-red-500' : ''}`}
                                    />
                                </div>
                                {errors.undertime_deduction_amount && (
                                    <p className="text-sm text-red-500">{errors.undertime_deduction_amount}</p>
                                )}
                                <p className="text-xs text-muted-foreground">Fixed deduction amount for any undertime</p>
                            </div>
                        )}

                        {formData.undertime_deduction_type === 'proportional' && (
                            <div className="space-y-2">
                                <Alert className="bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                                    <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    <AlertDescription>
                                        <strong>Proportional Calculation:</strong>
                                        <br />
                                        Hourly Rate = Daily Rate ÷ 8 hours
                                        <br />
                                        Deduction = (Undertime Minutes ÷ 60) × Hourly Rate
                                    </AlertDescription>
                                </Alert>
                            </div>
                        )}
                    </div>

                    {/* Undertime Deduction Example */}
                    {formData.undertime_deduction_type !== 'none' && (
                        <Alert className="bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                            <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            <AlertDescription>
                                <strong>Example Calculation (1 hour undertime, ₱570 daily rate):</strong>
                                <br />
                                {formData.undertime_deduction_type === 'proportional' && (
                                    <>
                                        Hourly rate: ₱570 ÷ 8 = ₱71.25/hour
                                        <br />
                                        Deduction: 1 hour × ₱71.25 = ₱{calculateUndertimeDeduction()}
                                    </>
                                )}
                                {formData.undertime_deduction_type === 'fixed' && (
                                    <>Fixed deduction: ₱{calculateUndertimeDeduction()}</>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {/* Absence Deduction Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Ban className="h-5 w-5" />
                        Absence Deduction Rules
                    </CardTitle>
                    <CardDescription>
                        Configure deductions for unexcused absences and leave without pay (LWOP)
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="absence_deduction_per_day">
                                Absence Deduction Rate <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="absence_deduction_per_day"
                                type="number"
                                min="0"
                                max="1"
                                step="0.01"
                                value={formData.absence_deduction_per_day}
                                onChange={(e) =>
                                    handleInputChange('absence_deduction_per_day', parseFloat(e.target.value) || 0)
                                }
                                className={errors.absence_deduction_per_day ? 'border-red-500' : ''}
                            />
                            {errors.absence_deduction_per_day && (
                                <p className="text-sm text-red-500">{errors.absence_deduction_per_day}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Percentage of daily rate deducted for unexcused absences (0.0 = 0%, 1.0 = 100%)
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="lwop_deduction_rate">
                                Leave Without Pay (LWOP) Rate <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="lwop_deduction_rate"
                                type="number"
                                min="0"
                                max="1"
                                step="0.01"
                                value={formData.lwop_deduction_rate}
                                onChange={(e) => handleInputChange('lwop_deduction_rate', parseFloat(e.target.value) || 0)}
                                className={errors.lwop_deduction_rate ? 'border-red-500' : ''}
                            />
                            {errors.lwop_deduction_rate && (
                                <p className="text-sm text-red-500">{errors.lwop_deduction_rate}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Percentage of daily rate deducted for approved unpaid leave (typically 1.0 = 100%)
                            </p>
                        </div>
                    </div>

                    {/* Absence Deduction Example */}
                    <Alert className="bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                        <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        <AlertDescription>
                            <strong>Example Calculation (₱570 daily rate):</strong>
                            <br />
                            <strong>Unexcused Absence:</strong> ₱570 × {(formData.absence_deduction_per_day * 100).toFixed(0)}
                            % = ₱{(570 * formData.absence_deduction_per_day).toFixed(2)} deduction
                            <br />
                            <strong>LWOP (Approved Unpaid Leave):</strong> ₱570 × {(formData.lwop_deduction_rate * 100).toFixed(0)}
                            % = ₱{(570 * formData.lwop_deduction_rate).toFixed(2)} deduction
                        </AlertDescription>
                    </Alert>

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Note on Absences:</strong> Unexcused absences are absences without approved leave. LWOP (Leave Without Pay) applies when an employee has exhausted all leave credits but has approval to take unpaid time off. Both typically result in 100% daily rate deduction (1.0 rate).
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Additional Deductions Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Other Deductions</CardTitle>
                    <CardDescription>Additional payroll deductions configured elsewhere</CardDescription>
                </CardHeader>
                <CardContent>
                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Other Deduction Types:</strong>
                            <br />• <strong>Government Contributions:</strong> SSS, PhilHealth, Pag-IBIG (configured in Government Rates tab)
                            <br />• <strong>Withholding Tax:</strong> BIR tax deductions (configured in Government Rates tab)
                            <br />• <strong>Salary Loans:</strong> Managed per employee in Payroll Officer module
                            <br />• <strong>Cash Advances:</strong> Tracked and deducted in Payroll Officer module
                            <br />• <strong>Other Deductions:</strong> Uniform, insurance, union dues (configured per employee)
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save Deduction Rules'}
                </Button>
            </div>

            {/* Validation Error Summary */}
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertDescription>
                        <strong>Please fix the following errors:</strong>
                        <ul className="list-disc list-inside mt-2">
                            {Object.values(errors).map((error, index) => (
                                <li key={index}>{error}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
