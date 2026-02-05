import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Save, Info, Calendar as CalendarIcon, CreditCard, Wallet, Banknote } from 'lucide-react';
import { useState } from 'react';

export interface PaymentScheduleData {
    payment_schedule: 'weekly' | 'bi-monthly' | 'monthly';
    cutoff_1st: number;
    cutoff_2nd: number;
    cutoff_monthly: number;
    default_method: 'cash' | 'bank_transfer' | 'ewallet';
    cash_enabled: boolean;
    bank_transfer_enabled: boolean;
    ewallet_enabled: boolean;
}

interface PaymentScheduleFormProps {
    initialData: PaymentScheduleData;
    onSubmit: (data: PaymentScheduleData) => void;
}

export function PaymentScheduleForm({ initialData, onSubmit }: PaymentScheduleFormProps) {
    const [formData, setFormData] = useState<PaymentScheduleData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof PaymentScheduleData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleInputChange = (field: keyof PaymentScheduleData, value: string | number | boolean) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof PaymentScheduleData, string>> = {};

        // Cutoff date validation based on schedule
        if (formData.payment_schedule === 'bi-monthly') {
            if (formData.cutoff_1st < 1 || formData.cutoff_1st > 31) {
                newErrors.cutoff_1st = 'First cutoff must be between 1 and 31';
            }
            if (formData.cutoff_2nd < 1 || formData.cutoff_2nd > 31) {
                newErrors.cutoff_2nd = 'Second cutoff must be between 1 and 31';
            }
            if (formData.cutoff_1st === formData.cutoff_2nd) {
                newErrors.cutoff_2nd = 'Cutoff dates must be different';
            }
        }

        if (formData.payment_schedule === 'monthly') {
            if (formData.cutoff_monthly < 1 || formData.cutoff_monthly > 31) {
                newErrors.cutoff_monthly = 'Cutoff day must be between 1 and 31';
            }
        }

        // At least one payment method must be enabled
        if (!formData.cash_enabled && !formData.bank_transfer_enabled && !formData.ewallet_enabled) {
            newErrors.cash_enabled = 'At least one payment method must be enabled';
        }

        // Default method must be enabled
        if (formData.default_method === 'cash' && !formData.cash_enabled) {
            newErrors.default_method = 'Default payment method must be enabled';
        }
        if (formData.default_method === 'bank_transfer' && !formData.bank_transfer_enabled) {
            newErrors.default_method = 'Default payment method must be enabled';
        }
        if (formData.default_method === 'ewallet' && !formData.ewallet_enabled) {
            newErrors.default_method = 'Default payment method must be enabled';
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

    const getPaymentDates = () => {
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;

        if (formData.payment_schedule === 'bi-monthly') {
            return [
                `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(formData.cutoff_1st).padStart(2, '0')}`,
                `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(formData.cutoff_2nd).padStart(2, '0')}`,
            ];
        } else if (formData.payment_schedule === 'monthly') {
            return [`${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(formData.cutoff_monthly).padStart(2, '0')}`];
        }
        return [];
    };

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> Payment schedule and cutoff dates determine when payroll is processed and when employees receive their salaries. Changes to payment methods affect how employees are paid. Ensure all configurations comply with company policies and labor laws.
                </AlertDescription>
            </Alert>

            {/* Payment Schedule Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CalendarIcon className="h-5 w-5" />
                        Payroll Frequency & Cutoff Dates
                    </CardTitle>
                    <CardDescription>Configure how often payroll is processed and payment cutoff dates</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Schedule Frequency */}
                    <div className="space-y-2">
                        <Label htmlFor="payment_schedule">
                            Payroll Frequency <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={formData.payment_schedule}
                            onValueChange={(value) =>
                                handleInputChange('payment_schedule', value as 'weekly' | 'bi-monthly' | 'monthly')
                            }
                        >
                            <SelectTrigger id="payment_schedule">
                                <SelectValue placeholder="Select payroll frequency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="weekly">Weekly (Every 7 days)</SelectItem>
                                <SelectItem value="bi-monthly">Bi-Monthly (Twice a month)</SelectItem>
                                <SelectItem value="monthly">Monthly (Once a month)</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {formData.payment_schedule === 'weekly' &&
                                'Employees are paid every week (52 pay periods per year)'}
                            {formData.payment_schedule === 'bi-monthly' &&
                                'Employees are paid twice per month (24 pay periods per year) - Standard in Philippines'}
                            {formData.payment_schedule === 'monthly' &&
                                'Employees are paid once per month (12 pay periods per year)'}
                        </p>
                    </div>

                    {/* Bi-Monthly Cutoff Dates */}
                    {formData.payment_schedule === 'bi-monthly' && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="cutoff_1st">
                                    First Cutoff Date <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="cutoff_1st"
                                    type="number"
                                    min="1"
                                    max="31"
                                    value={formData.cutoff_1st}
                                    onChange={(e) => handleInputChange('cutoff_1st', parseInt(e.target.value) || 1)}
                                    className={errors.cutoff_1st ? 'border-red-500' : ''}
                                />
                                {errors.cutoff_1st && <p className="text-sm text-red-500">{errors.cutoff_1st}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Day of the month for first payroll (e.g., 5th)
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cutoff_2nd">
                                    Second Cutoff Date <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="cutoff_2nd"
                                    type="number"
                                    min="1"
                                    max="31"
                                    value={formData.cutoff_2nd}
                                    onChange={(e) => handleInputChange('cutoff_2nd', parseInt(e.target.value) || 1)}
                                    className={errors.cutoff_2nd ? 'border-red-500' : ''}
                                />
                                {errors.cutoff_2nd && <p className="text-sm text-red-500">{errors.cutoff_2nd}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Day of the month for second payroll (e.g., 20th)
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Monthly Cutoff Date */}
                    {formData.payment_schedule === 'monthly' && (
                        <div className="space-y-2">
                            <Label htmlFor="cutoff_monthly">
                                Monthly Cutoff Date <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="cutoff_monthly"
                                type="number"
                                min="1"
                                max="31"
                                value={formData.cutoff_monthly}
                                onChange={(e) => handleInputChange('cutoff_monthly', parseInt(e.target.value) || 1)}
                                className={errors.cutoff_monthly ? 'border-red-500' : ''}
                            />
                            {errors.cutoff_monthly && <p className="text-sm text-red-500">{errors.cutoff_monthly}</p>}
                            <p className="text-xs text-muted-foreground">
                                Day of the month for payroll (e.g., 30th or 31st for end of month)
                            </p>
                        </div>
                    )}

                    {/* Weekly Note */}
                    {formData.payment_schedule === 'weekly' && (
                        <Alert className="bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertDescription>
                                <strong>Weekly Payroll:</strong> Payroll will be processed every 7 days. The specific day of the week will be configured in the Payroll Processing module. This is less common in the Philippines but may be used for contractual or project-based workers.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Payment Schedule Example */}
                    {formData.payment_schedule !== 'weekly' && getPaymentDates().length > 0 && (
                        <Alert className="bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800">
                            <CalendarIcon className="h-4 w-4 text-green-600 dark:text-green-400" />
                            <AlertDescription>
                                <strong>Example Payment Dates:</strong>
                                <br />
                                {getPaymentDates().map((date, index) => (
                                    <span key={index}>
                                        {new Date(date).toLocaleDateString('en-PH', {
                                            month: 'long',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })}
                                        {index < getPaymentDates().length - 1 && ' and '}
                                    </span>
                                ))}
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {/* Payment Methods Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5" />
                        Payment Methods Configuration
                    </CardTitle>
                    <CardDescription>Configure available payment methods and set default method</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Payment Methods Checkboxes */}
                    <div className="space-y-4">
                        <Label>
                            Enabled Payment Methods <span className="text-red-500">*</span>
                        </Label>

                        {/* Cash Payment */}
                        <div className="flex items-start space-x-3 p-4 border rounded-lg">
                            <Checkbox
                                id="cash_enabled"
                                checked={formData.cash_enabled}
                                onCheckedChange={(checked) => handleInputChange('cash_enabled', checked as boolean)}
                            />
                            <div className="flex-1">
                                <Label htmlFor="cash_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <Banknote className="h-4 w-4" />
                                    Cash Payment
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Physical cash distribution in envelopes. Requires employee signature and accountability report.
                                </p>
                            </div>
                        </div>

                        {/* Bank Transfer */}
                        <div className="flex items-start space-x-3 p-4 border rounded-lg">
                            <Checkbox
                                id="bank_transfer_enabled"
                                checked={formData.bank_transfer_enabled}
                                onCheckedChange={(checked) => handleInputChange('bank_transfer_enabled', checked as boolean)}
                            />
                            <div className="flex-1">
                                <Label
                                    htmlFor="bank_transfer_enabled"
                                    className="flex items-center gap-2 font-semibold cursor-pointer"
                                >
                                    <CreditCard className="h-4 w-4" />
                                    Bank Transfer
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Electronic fund transfer to employee bank accounts. Generates bank file for batch processing.
                                </p>
                            </div>
                        </div>

                        {/* E-Wallet */}
                        <div className="flex items-start space-x-3 p-4 border rounded-lg">
                            <Checkbox
                                id="ewallet_enabled"
                                checked={formData.ewallet_enabled}
                                onCheckedChange={(checked) => handleInputChange('ewallet_enabled', checked as boolean)}
                            />
                            <div className="flex-1">
                                <Label htmlFor="ewallet_enabled" className="flex items-center gap-2 font-semibold cursor-pointer">
                                    <Wallet className="h-4 w-4" />
                                    E-Wallet (GCash, PayMaya, etc.)
                                </Label>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Digital wallet transfers (GCash, PayMaya, Maya). Instant transfer with transaction notifications.
                                </p>
                            </div>
                        </div>

                        {errors.cash_enabled && (
                            <p className="text-sm text-red-500">{errors.cash_enabled}</p>
                        )}
                    </div>

                    {/* Default Payment Method */}
                    <div className="space-y-2">
                        <Label htmlFor="default_method">
                            Default Payment Method <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={formData.default_method}
                            onValueChange={(value) =>
                                handleInputChange('default_method', value as 'cash' | 'bank_transfer' | 'ewallet')
                            }
                        >
                            <SelectTrigger id="default_method" className={errors.default_method ? 'border-red-500' : ''}>
                                <SelectValue placeholder="Select default payment method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash" disabled={!formData.cash_enabled}>
                                    Cash Payment {!formData.cash_enabled && '(Not enabled)'}
                                </SelectItem>
                                <SelectItem value="bank_transfer" disabled={!formData.bank_transfer_enabled}>
                                    Bank Transfer {!formData.bank_transfer_enabled && '(Not enabled)'}
                                </SelectItem>
                                <SelectItem value="ewallet" disabled={!formData.ewallet_enabled}>
                                    E-Wallet {!formData.ewallet_enabled && '(Not enabled)'}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.default_method && <p className="text-sm text-red-500">{errors.default_method}</p>}
                        <p className="text-xs text-muted-foreground">
                            New employees will be assigned this payment method by default (can be changed per employee)
                        </p>
                    </div>

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Note:</strong> Individual employees can have different payment methods. This setting only controls the default for new employees and which methods are available system-wide. Employee-specific payment methods are configured in the HR module.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Current Configuration Summary */}
            <Card className="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 border-indigo-200 dark:border-indigo-800">
                <CardHeader>
                    <CardTitle>Payment Configuration Summary</CardTitle>
                    <CardDescription>Review your current payment schedule and methods</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Payroll Frequency</p>
                            <p className="text-lg font-bold capitalize">{formData.payment_schedule.replace('-', ' ')}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Pay Periods per Year</p>
                            <p className="text-lg font-bold">
                                {formData.payment_schedule === 'weekly' && '52'}
                                {formData.payment_schedule === 'bi-monthly' && '24'}
                                {formData.payment_schedule === 'monthly' && '12'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Cutoff Dates</p>
                            <p className="text-lg font-bold">
                                {formData.payment_schedule === 'bi-monthly' &&
                                    `${formData.cutoff_1st} & ${formData.cutoff_2nd}`}
                                {formData.payment_schedule === 'monthly' && `${formData.cutoff_monthly}`}
                                {formData.payment_schedule === 'weekly' && 'Every 7 days'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Default Payment Method</p>
                            <p className="text-lg font-bold capitalize">{formData.default_method.replace('_', ' ')}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save Payment Schedule'}
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
