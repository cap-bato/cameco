import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, DollarSign, Landmark, Calculator, CreditCard, AlertCircle } from 'lucide-react';
import { SalaryStructureForm, type SalaryStructureData } from '@/components/admin/salary-structure-form';
import { GovernmentRatesForm } from '@/components/admin/government-rates-form';
import { StandardDeductionsForm, type StandardDeductionsData } from '@/components/admin/standard-deductions-form';
import { PaymentScheduleForm, type PaymentScheduleData } from '@/components/admin/payment-schedule-form';
import { useState } from 'react';
import { useToast } from '@/hooks/use-toast';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface PayrollRulesData {
    salary_structure: {
        minimum_wage: number;
        salary_grades_enabled: boolean;
        salary_grades_count: number;
    };
    allowances: {
        housing_allowance: number;
        transportation_allowance: number;
        meal_allowance: number;
        communication_allowance: number;
    };
    bonuses: {
        thirteenth_month_enabled: boolean;
        performance_bonus_enabled: boolean;
    };
    government_rates: {
        // SSS
        sss_employee_rate: number;
        sss_employer_rate: number;
        sss_max_salary: number;
        sss_effective_date: string | null;
        // PhilHealth
        philhealth_rate: number;
        philhealth_employee_share: number;
        philhealth_employer_share: number;
        philhealth_min_salary: number;
        philhealth_max_salary: number;
        philhealth_effective_date: string | null;
        // Pag-IBIG
        pagibig_employee_rate: number;
        pagibig_employer_rate: number;
        pagibig_max_salary: number;
        pagibig_effective_date: string | null;
        // Tax
        tax_brackets: Array<Record<string, unknown>>;
    };
    payment_methods: {
        default_method: string;
        cash_enabled: boolean;
        bank_transfer_enabled: boolean;
        ewallet_enabled: boolean;
        payment_schedule: string;
        cutoff_dates: Record<string, unknown> | null;
    };
}

interface PayrollRulesIndexProps {
    payrollRules: PayrollRulesData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Payroll Rules',
        href: '/admin/payroll-rules',
    },
];

export default function PayrollRulesIndex({ payrollRules }: PayrollRulesIndexProps) {
    const { toast } = useToast();
    const [activeTab, setActiveTab] = useState('salary');

    const handleSalaryStructureSubmit = (data: SalaryStructureData) => {
        router.put('/admin/payroll-rules/salary-structure', data as unknown as Record<string, string | number | boolean>, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Salary structure updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update salary structure. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Salary structure update errors:', errors);
            },
        });
    };

    const handleDeductionsSubmit = (data: StandardDeductionsData) => {
        router.put('/admin/payroll-rules/deductions', data as unknown as Record<string, string | number>, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Standard deductions updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update standard deductions. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Deductions update errors:', errors);
            },
        });
    };

    const handlePaymentScheduleSubmit = (data: PaymentScheduleData) => {
        router.put('/admin/payroll-rules/payment-methods', data as unknown as Record<string, string | number | boolean>, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Payment schedule and methods updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update payment schedule. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Payment schedule update errors:', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Rules" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit('/admin/dashboard')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Dashboard
                        </Button>
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">Payroll Rules Configuration</h1>
                    <p className="text-muted-foreground">
                        Configure salary structures, government contribution rates, deductions, and payment methods
                    </p>
                </div>

                {/* Info Alert */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <strong>Important:</strong> Changes to government rates (SSS, PhilHealth, Pag-IBIG, Tax) take effect from the specified date.
                        Always verify current Philippine government rates before updating. Salary structure changes affect all future payroll calculations.
                    </AlertDescription>
                </Alert>

                {/* Overview Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payroll Configuration Overview</CardTitle>
                        <CardDescription>Manage salary components, government contributions, and payment settings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="flex items-start gap-3">
                                <div className="p-2 bg-blue-100 dark:bg-blue-950 rounded-lg">
                                    <DollarSign className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">Minimum Wage</p>
                                    <p className="text-2xl font-bold">₱{payrollRules.salary_structure.minimum_wage.toFixed(2)}</p>
                                    <p className="text-xs text-muted-foreground">Per day</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="p-2 bg-green-100 dark:bg-green-950 rounded-lg">
                                    <Landmark className="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">SSS Rate</p>
                                    <p className="text-2xl font-bold">{payrollRules.government_rates.sss_employee_rate + payrollRules.government_rates.sss_employer_rate}%</p>
                                    <p className="text-xs text-muted-foreground">Total contribution</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="p-2 bg-purple-100 dark:bg-purple-950 rounded-lg">
                                    <Calculator className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">PhilHealth Rate</p>
                                    <p className="text-2xl font-bold">{payrollRules.government_rates.philhealth_rate}%</p>
                                    <p className="text-xs text-muted-foreground">Monthly salary</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="p-2 bg-orange-100 dark:bg-orange-950 rounded-lg">
                                    <CreditCard className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium">Payment Method</p>
                                    <p className="text-2xl font-bold capitalize">{payrollRules.payment_methods.default_method}</p>
                                    <p className="text-xs text-muted-foreground">Default method</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabbed Configuration */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="salary" className="gap-2">
                            <DollarSign className="h-4 w-4" />
                            Salary Structure
                        </TabsTrigger>
                        <TabsTrigger value="government" className="gap-2">
                            <Landmark className="h-4 w-4" />
                            Government Rates
                        </TabsTrigger>
                        <TabsTrigger value="deductions" className="gap-2">
                            <Calculator className="h-4 w-4" />
                            Deductions
                        </TabsTrigger>
                        <TabsTrigger value="payment" className="gap-2">
                            <CreditCard className="h-4 w-4" />
                            Payment Schedule
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Salary Structure */}
                    <TabsContent value="salary">
                        <SalaryStructureForm
                            initialData={{
                                minimum_wage: payrollRules.salary_structure.minimum_wage,
                                salary_grades_enabled: payrollRules.salary_structure.salary_grades_enabled,
                                salary_grades_count: payrollRules.salary_structure.salary_grades_count,
                                housing_allowance: payrollRules.allowances.housing_allowance,
                                transportation_allowance: payrollRules.allowances.transportation_allowance,
                                meal_allowance: payrollRules.allowances.meal_allowance,
                                communication_allowance: payrollRules.allowances.communication_allowance,
                                thirteenth_month_enabled: payrollRules.bonuses.thirteenth_month_enabled,
                                performance_bonus_enabled: payrollRules.bonuses.performance_bonus_enabled,
                            }}
                            onSubmit={handleSalaryStructureSubmit}
                        />
                    </TabsContent>

                    {/* Tab 2: Government Rates */}
                    <TabsContent value="government">
                        <GovernmentRatesForm
                            initialData={{
                                sss_employee_rate: payrollRules.government_rates.sss_employee_rate,
                                sss_employer_rate: payrollRules.government_rates.sss_employer_rate,
                                sss_max_salary: payrollRules.government_rates.sss_max_salary,
                                sss_effective_date: payrollRules.government_rates.sss_effective_date,
                                philhealth_rate: payrollRules.government_rates.philhealth_rate,
                                philhealth_employee_share: payrollRules.government_rates.philhealth_employee_share,
                                philhealth_employer_share: payrollRules.government_rates.philhealth_employer_share,
                                philhealth_min_salary: payrollRules.government_rates.philhealth_min_salary,
                                philhealth_max_salary: payrollRules.government_rates.philhealth_max_salary,
                                philhealth_effective_date: payrollRules.government_rates.philhealth_effective_date,
                                pagibig_employee_rate: payrollRules.government_rates.pagibig_employee_rate,
                                pagibig_employer_rate: payrollRules.government_rates.pagibig_employer_rate,
                                pagibig_max_salary: payrollRules.government_rates.pagibig_max_salary,
                                pagibig_effective_date: payrollRules.government_rates.pagibig_effective_date,
                            }}
                        />
                    </TabsContent>



                    {/* Tab 3: Deductions */}
                    <TabsContent value="deductions">
                        <StandardDeductionsForm
                            initialData={{
                                late_deduction_type: 'per_minute',
                                late_deduction_amount: 5.0,
                                undertime_deduction_type: 'proportional',
                                undertime_deduction_amount: 0,
                                absence_deduction_per_day: 1.0,
                                lwop_deduction_rate: 1.0,
                            }}
                            onSubmit={handleDeductionsSubmit}
                        />
                    </TabsContent>

                    {/* Tab 4: Payment Schedule */}
                    <TabsContent value="payment">
                        <PaymentScheduleForm
                            initialData={{
                                payment_schedule: payrollRules.payment_methods.payment_schedule as 'weekly' | 'bi-monthly' | 'monthly',
                                cutoff_1st: 5,
                                cutoff_2nd: 20,
                                cutoff_monthly: 30,
                                default_method: payrollRules.payment_methods.default_method as 'cash' | 'bank_transfer' | 'ewallet',
                                cash_enabled: payrollRules.payment_methods.cash_enabled,
                                bank_transfer_enabled: payrollRules.payment_methods.bank_transfer_enabled,
                                ewallet_enabled: payrollRules.payment_methods.ewallet_enabled,
                            }}
                            onSubmit={handlePaymentScheduleSubmit}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
