import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Save, Info, DollarSign, Building, TrendingUp } from 'lucide-react';
import { useState } from 'react';

export interface SalaryStructureData {
    minimum_wage: number;
    salary_grades_enabled: boolean;
    salary_grades_count: number;
    housing_allowance: number;
    transportation_allowance: number;
    meal_allowance: number;
    communication_allowance: number;
    thirteenth_month_enabled: boolean;
    performance_bonus_enabled: boolean;
}

interface SalaryStructureFormProps {
    initialData: SalaryStructureData;
    onSubmit: (data: SalaryStructureData) => void;
}

export function SalaryStructureForm({ initialData, onSubmit }: SalaryStructureFormProps) {
    const [formData, setFormData] = useState<SalaryStructureData>(initialData);
    const [errors, setErrors] = useState<Partial<Record<keyof SalaryStructureData, string>>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleInputChange = (field: keyof SalaryStructureData, value: number | boolean) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        // Clear error for this field
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field];
            return newErrors;
        });
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<Record<keyof SalaryStructureData, string>> = {};

        // Minimum wage validation
        if (formData.minimum_wage < 0) {
            newErrors.minimum_wage = 'Minimum wage cannot be negative';
        }
        if (formData.minimum_wage > 10000) {
            newErrors.minimum_wage = 'Minimum wage seems unusually high. Please verify.';
        }

        // Salary grades validation
        if (formData.salary_grades_enabled) {
            if (formData.salary_grades_count < 1 || formData.salary_grades_count > 30) {
                newErrors.salary_grades_count = 'Salary grades count must be between 1 and 30';
            }
        }

        // Allowances validation (cannot be negative)
        if (formData.housing_allowance < 0) {
            newErrors.housing_allowance = 'Allowance cannot be negative';
        }
        if (formData.transportation_allowance < 0) {
            newErrors.transportation_allowance = 'Allowance cannot be negative';
        }
        if (formData.meal_allowance < 0) {
            newErrors.meal_allowance = 'Allowance cannot be negative';
        }
        if (formData.communication_allowance < 0) {
            newErrors.communication_allowance = 'Allowance cannot be negative';
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

    const calculateTotalCompensation = () => {
        const monthlyMinimumWage = formData.minimum_wage * 22; // Assuming 22 working days
        const totalAllowances =
            formData.housing_allowance +
            formData.transportation_allowance +
            formData.meal_allowance +
            formData.communication_allowance;
        
        return monthlyMinimumWage + totalAllowances;
    };

    return (
        <div className="space-y-6">
            {/* Summary Card */}
            <Card className="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20 border-blue-200 dark:border-blue-800">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Info className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Compensation Package Summary
                    </CardTitle>
                    <CardDescription>Estimated monthly base compensation for minimum wage employees</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Base Salary (22 days)</p>
                            <p className="text-2xl font-bold">₱{(formData.minimum_wage * 22).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Total Allowances</p>
                            <p className="text-2xl font-bold">
                                ₱
                                {(
                                    formData.housing_allowance +
                                    formData.transportation_allowance +
                                    formData.meal_allowance +
                                    formData.communication_allowance
                                ).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Total Monthly Package</p>
                            <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                ₱{calculateTotalCompensation().toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Salary Structure Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <DollarSign className="h-5 w-5" />
                        Salary Structure
                    </CardTitle>
                    <CardDescription>Configure minimum wage and salary grade system</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Minimum Wage */}
                    <div className="space-y-2">
                        <Label htmlFor="minimum_wage">
                            Minimum Daily Wage <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                            <Input
                                id="minimum_wage"
                                type="number"
                                min="0"
                                step="0.01"
                                value={formData.minimum_wage}
                                onChange={(e) => handleInputChange('minimum_wage', parseFloat(e.target.value) || 0)}
                                className={`pl-8 ${errors.minimum_wage ? 'border-red-500' : ''}`}
                                placeholder="570.00"
                            />
                        </div>
                        {errors.minimum_wage && <p className="text-sm text-red-500">{errors.minimum_wage}</p>}
                        <p className="text-xs text-muted-foreground">
                            Current Metro Manila minimum wage: ₱610.00 (as of July 2024)
                        </p>
                    </div>

                    {/* Salary Grades */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="space-y-0.5">
                                <Label htmlFor="salary_grades_enabled">Enable Salary Grade System</Label>
                                <p className="text-sm text-muted-foreground">
                                    Use standardized salary grades (e.g., SG 1-30) for position-based compensation
                                </p>
                            </div>
                            <Switch
                                id="salary_grades_enabled"
                                checked={formData.salary_grades_enabled}
                                onCheckedChange={(checked) => handleInputChange('salary_grades_enabled', checked)}
                            />
                        </div>

                        {formData.salary_grades_enabled && (
                            <div className="space-y-2 pl-6 border-l-2 border-blue-200 dark:border-blue-800">
                                <Label htmlFor="salary_grades_count">
                                    Number of Salary Grades <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="salary_grades_count"
                                    type="number"
                                    min="1"
                                    max="30"
                                    value={formData.salary_grades_count}
                                    onChange={(e) =>
                                        handleInputChange('salary_grades_count', parseInt(e.target.value) || 1)
                                    }
                                    className={errors.salary_grades_count ? 'border-red-500' : ''}
                                />
                                {errors.salary_grades_count && (
                                    <p className="text-sm text-red-500">{errors.salary_grades_count}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Typical range: 1-30 (Philippine government uses SG 1-33)
                                </p>
                            </div>
                        )}
                    </div>

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Note:</strong> The salary grade system allows you to define standardized salary ranges for different positions.
                            Each grade has a minimum, midpoint, and maximum salary level. This will be configured in the Positions module.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Allowances Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Building className="h-5 w-5" />
                        Standard Allowances
                    </CardTitle>
                    <CardDescription>Monthly allowance amounts (optional, can be ₱0)</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        {/* Housing Allowance */}
                        <div className="space-y-2">
                            <Label htmlFor="housing_allowance">Housing Allowance</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                <Input
                                    id="housing_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={formData.housing_allowance}
                                    onChange={(e) =>
                                        handleInputChange('housing_allowance', parseFloat(e.target.value) || 0)
                                    }
                                    className={`pl-8 ${errors.housing_allowance ? 'border-red-500' : ''}`}
                                    placeholder="0.00"
                                />
                            </div>
                            {errors.housing_allowance && (
                                <p className="text-sm text-red-500">{errors.housing_allowance}</p>
                            )}
                            <p className="text-xs text-muted-foreground">Monthly housing/accommodation support</p>
                        </div>

                        {/* Transportation Allowance */}
                        <div className="space-y-2">
                            <Label htmlFor="transportation_allowance">Transportation Allowance</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                <Input
                                    id="transportation_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={formData.transportation_allowance}
                                    onChange={(e) =>
                                        handleInputChange('transportation_allowance', parseFloat(e.target.value) || 0)
                                    }
                                    className={`pl-8 ${errors.transportation_allowance ? 'border-red-500' : ''}`}
                                    placeholder="0.00"
                                />
                            </div>
                            {errors.transportation_allowance && (
                                <p className="text-sm text-red-500">{errors.transportation_allowance}</p>
                            )}
                            <p className="text-xs text-muted-foreground">Monthly commuting/travel support</p>
                        </div>

                        {/* Meal Allowance */}
                        <div className="space-y-2">
                            <Label htmlFor="meal_allowance">Meal Allowance</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                <Input
                                    id="meal_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={formData.meal_allowance}
                                    onChange={(e) => handleInputChange('meal_allowance', parseFloat(e.target.value) || 0)}
                                    className={`pl-8 ${errors.meal_allowance ? 'border-red-500' : ''}`}
                                    placeholder="0.00"
                                />
                            </div>
                            {errors.meal_allowance && <p className="text-sm text-red-500">{errors.meal_allowance}</p>}
                            <p className="text-xs text-muted-foreground">Monthly food/subsistence support</p>
                        </div>

                        {/* Communication Allowance */}
                        <div className="space-y-2">
                            <Label htmlFor="communication_allowance">Communication Allowance</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                <Input
                                    id="communication_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={formData.communication_allowance}
                                    onChange={(e) =>
                                        handleInputChange('communication_allowance', parseFloat(e.target.value) || 0)
                                    }
                                    className={`pl-8 ${errors.communication_allowance ? 'border-red-500' : ''}`}
                                    placeholder="0.00"
                                />
                            </div>
                            {errors.communication_allowance && (
                                <p className="text-sm text-red-500">{errors.communication_allowance}</p>
                            )}
                            <p className="text-xs text-muted-foreground">Monthly phone/internet support</p>
                        </div>
                    </div>

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Tax Note:</strong> In the Philippines, de minimis benefits (including allowances) up to ₱90,000 per year are generally tax-exempt.
                            Consult with your tax advisor for specific guidance.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Bonuses Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <TrendingUp className="h-5 w-5" />
                        Bonus Configuration
                    </CardTitle>
                    <CardDescription>Enable statutory and performance-based bonuses</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* 13th Month Pay */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="space-y-0.5">
                            <Label htmlFor="thirteenth_month_enabled" className="font-semibold">
                                13th Month Pay
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                Mandatory under Philippine Presidential Decree No. 851
                            </p>
                            <p className="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                ⚠ Required by law - cannot be disabled
                            </p>
                        </div>
                        <Switch
                            id="thirteenth_month_enabled"
                            checked={formData.thirteenth_month_enabled}
                            onCheckedChange={(checked) => handleInputChange('thirteenth_month_enabled', checked)}
                        />
                    </div>

                    {/* Performance Bonus */}
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="space-y-0.5">
                            <Label htmlFor="performance_bonus_enabled" className="font-semibold">
                                Performance Bonus
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                Optional merit-based bonus tied to performance appraisals
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Bonus amounts will be configured per employee or per position
                            </p>
                        </div>
                        <Switch
                            id="performance_bonus_enabled"
                            checked={formData.performance_bonus_enabled}
                            onCheckedChange={(checked) => handleInputChange('performance_bonus_enabled', checked)}
                        />
                    </div>

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertDescription>
                            <strong>13th Month Pay Calculation:</strong> 1/12 of the total basic salary earned within a calendar year.
                            Must be paid on or before December 24. Employees who worked at least one month are entitled to this benefit.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end gap-4">
                <Button onClick={handleSubmit} disabled={isSubmitting} size="lg">
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? 'Saving...' : 'Save Salary Structure'}
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
