import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Save, Info, Landmark, Shield, Home, FileText, Calendar } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useToast } from '@/hooks/use-toast';

export interface GovernmentRatesData {
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
}

interface GovernmentRatesFormProps {
    initialData: GovernmentRatesData;
}

export function GovernmentRatesForm({ initialData }: GovernmentRatesFormProps) {
    const { toast } = useToast();
    const [activeAgency, setActiveAgency] = useState('sss');
    
    // SSS State
    const [sssData, setSssData] = useState({
        employee_rate: initialData.sss_employee_rate,
        employer_rate: initialData.sss_employer_rate,
        max_salary: initialData.sss_max_salary,
        effective_date: initialData.sss_effective_date || new Date().toISOString().split('T')[0],
    });
    const [sssErrors, setSssErrors] = useState<Record<string, string>>({});
    const [sssSubmitting, setSssSubmitting] = useState(false);

    // PhilHealth State
    const [philhealthData, setPhilhealthData] = useState({
        rate: initialData.philhealth_rate,
        employee_share: initialData.philhealth_employee_share,
        employer_share: initialData.philhealth_employer_share,
        min_salary: initialData.philhealth_min_salary,
        max_salary: initialData.philhealth_max_salary,
        effective_date: initialData.philhealth_effective_date || new Date().toISOString().split('T')[0],
    });
    const [philhealthErrors, setPhilhealthErrors] = useState<Record<string, string>>({});
    const [philhealthSubmitting, setPhilhealthSubmitting] = useState(false);

    // Pag-IBIG State
    const [pagibigData, setPagibigData] = useState({
        employee_rate: initialData.pagibig_employee_rate,
        employer_rate: initialData.pagibig_employer_rate,
        max_salary: initialData.pagibig_max_salary,
        effective_date: initialData.pagibig_effective_date || new Date().toISOString().split('T')[0],
    });
    const [pagibigErrors, setPagibigErrors] = useState<Record<string, string>>({});
    const [pagibigSubmitting, setPagibigSubmitting] = useState(false);

    const validateSssData = (): boolean => {
        const errors: Record<string, string> = {};

        if (sssData.employee_rate < 0 || sssData.employee_rate > 100) {
            errors.employee_rate = 'Employee rate must be between 0% and 100%';
        }
        if (sssData.employer_rate < 0 || sssData.employer_rate > 100) {
            errors.employer_rate = 'Employer rate must be between 0% and 100%';
        }
        if (sssData.max_salary < 0) {
            errors.max_salary = 'Maximum salary cannot be negative';
        }
        if (!sssData.effective_date) {
            errors.effective_date = 'Effective date is required';
        }

        setSssErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const validatePhilhealthData = (): boolean => {
        const errors: Record<string, string> = {};

        if (philhealthData.rate < 0 || philhealthData.rate > 100) {
            errors.rate = 'Total rate must be between 0% and 100%';
        }
        if (philhealthData.employee_share < 0 || philhealthData.employee_share > 100) {
            errors.employee_share = 'Employee share must be between 0% and 100%';
        }
        if (philhealthData.employer_share < 0 || philhealthData.employer_share > 100) {
            errors.employer_share = 'Employer share must be between 0% and 100%';
        }
        if (philhealthData.min_salary < 0) {
            errors.min_salary = 'Minimum salary cannot be negative';
        }
        if (philhealthData.max_salary < philhealthData.min_salary) {
            errors.max_salary = 'Maximum salary must be greater than minimum salary';
        }
        if (!philhealthData.effective_date) {
            errors.effective_date = 'Effective date is required';
        }

        setPhilhealthErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const validatePagibigData = (): boolean => {
        const errors: Record<string, string> = {};

        if (pagibigData.employee_rate < 0 || pagibigData.employee_rate > 100) {
            errors.employee_rate = 'Employee rate must be between 0% and 100%';
        }
        if (pagibigData.employer_rate < 0 || pagibigData.employer_rate > 100) {
            errors.employer_rate = 'Employer rate must be between 0% and 100%';
        }
        if (pagibigData.max_salary < 0) {
            errors.max_salary = 'Maximum salary cannot be negative';
        }
        if (!pagibigData.effective_date) {
            errors.effective_date = 'Effective date is required';
        }

        setPagibigErrors(errors);
        return Object.keys(errors).length === 0;
    };

    const handleSssSubmit = () => {
        if (!validateSssData()) return;

        setSssSubmitting(true);
        router.put(
            '/admin/payroll-rules/government-rates',
            {
                agency: 'sss',
                sss_employee_rate: sssData.employee_rate,
                sss_employer_rate: sssData.employer_rate,
                sss_max_salary: sssData.max_salary,
                effective_date: sssData.effective_date,
            } as unknown as Record<string, string | number>,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'SSS rates updated successfully.',
                    });
                    setSssSubmitting(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Error',
                        description: 'Failed to update SSS rates. Please check your inputs.',
                        variant: 'destructive',
                    });
                    console.error('SSS update errors:', errors);
                    setSssSubmitting(false);
                },
            }
        );
    };

    const handlePhilhealthSubmit = () => {
        if (!validatePhilhealthData()) return;

        setPhilhealthSubmitting(true);
        router.put(
            '/admin/payroll-rules/government-rates',
            {
                agency: 'philhealth',
                philhealth_rate: philhealthData.rate,
                philhealth_employee_share: philhealthData.employee_share,
                philhealth_employer_share: philhealthData.employer_share,
                philhealth_min_salary: philhealthData.min_salary,
                philhealth_max_salary: philhealthData.max_salary,
                effective_date: philhealthData.effective_date,
            } as unknown as Record<string, string | number>,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'PhilHealth rates updated successfully.',
                    });
                    setPhilhealthSubmitting(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Error',
                        description: 'Failed to update PhilHealth rates. Please check your inputs.',
                        variant: 'destructive',
                    });
                    console.error('PhilHealth update errors:', errors);
                    setPhilhealthSubmitting(false);
                },
            }
        );
    };

    const handlePagibigSubmit = () => {
        if (!validatePagibigData()) return;

        setPagibigSubmitting(true);
        router.put(
            '/admin/payroll-rules/government-rates',
            {
                agency: 'pagibig',
                pagibig_employee_rate: pagibigData.employee_rate,
                pagibig_employer_rate: pagibigData.employer_rate,
                pagibig_max_salary: pagibigData.max_salary,
                effective_date: pagibigData.effective_date,
            } as unknown as Record<string, string | number>,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast({
                        title: 'Success',
                        description: 'Pag-IBIG rates updated successfully.',
                    });
                    setPagibigSubmitting(false);
                },
                onError: (errors) => {
                    toast({
                        title: 'Error',
                        description: 'Failed to update Pag-IBIG rates. Please check your inputs.',
                        variant: 'destructive',
                    });
                    console.error('Pag-IBIG update errors:', errors);
                    setPagibigSubmitting(false);
                },
            }
        );
    };

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Info className="h-4 w-4" />
                <AlertDescription>
                    <strong>Important:</strong> Government contribution rates are mandated by Philippine law. Always verify the latest rates from official government sources (SSS, PhilHealth, Pag-IBIG websites) before updating. Changes take effect from the specified effective date and apply to all future payroll calculations.
                </AlertDescription>
            </Alert>

            {/* Agency Tabs */}
            <Tabs value={activeAgency} onValueChange={setActiveAgency} className="space-y-4">
                <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger value="sss" className="gap-2">
                        <Shield className="h-4 w-4" />
                        SSS
                    </TabsTrigger>
                    <TabsTrigger value="philhealth" className="gap-2">
                        <Landmark className="h-4 w-4" />
                        PhilHealth
                    </TabsTrigger>
                    <TabsTrigger value="pagibig" className="gap-2">
                        <Home className="h-4 w-4" />
                        Pag-IBIG
                    </TabsTrigger>
                </TabsList>

                {/* SSS Tab */}
                <TabsContent value="sss">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Social Security System (SSS) Contribution Rates
                            </CardTitle>
                            <CardDescription>
                                Configure SSS contribution rates based on R.A. 11199 (Social Security Act of 2018)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Current Rates Summary */}
                            <div className="p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employee Share</p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{sssData.employee_rate}%</p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employer Share</p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{sssData.employer_rate}%</p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Total Contribution</p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                            {sssData.employee_rate + sssData.employer_rate}%
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Rate Configuration */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="sss_employee_rate">
                                        Employee Contribution Rate (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="sss_employee_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={sssData.employee_rate}
                                        onChange={(e) => {
                                            setSssData({ ...sssData, employee_rate: parseFloat(e.target.value) || 0 });
                                            setSssErrors({ ...sssErrors, employee_rate: '' });
                                        }}
                                        className={sssErrors.employee_rate ? 'border-red-500' : ''}
                                    />
                                    {sssErrors.employee_rate && (
                                        <p className="text-sm text-red-500">{sssErrors.employee_rate}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Current mandated rate: 4.5%</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sss_employer_rate">
                                        Employer Contribution Rate (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="sss_employer_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={sssData.employer_rate}
                                        onChange={(e) => {
                                            setSssData({ ...sssData, employer_rate: parseFloat(e.target.value) || 0 });
                                            setSssErrors({ ...sssErrors, employer_rate: '' });
                                        }}
                                        className={sssErrors.employer_rate ? 'border-red-500' : ''}
                                    />
                                    {sssErrors.employer_rate && (
                                        <p className="text-sm text-red-500">{sssErrors.employer_rate}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Current mandated rate: 9.5%</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sss_max_salary">
                                        Maximum Salary Base (₱) <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                        <Input
                                            id="sss_max_salary"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={sssData.max_salary}
                                            onChange={(e) => {
                                                setSssData({ ...sssData, max_salary: parseFloat(e.target.value) || 0 });
                                                setSssErrors({ ...sssErrors, max_salary: '' });
                                            }}
                                            className={`pl-8 ${sssErrors.max_salary ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {sssErrors.max_salary && <p className="text-sm text-red-500">{sssErrors.max_salary}</p>}
                                    <p className="text-xs text-muted-foreground">Current maximum: ₱30,000/month</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sss_effective_date">
                                        Effective Date <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="sss_effective_date"
                                            type="date"
                                            value={sssData.effective_date}
                                            onChange={(e) => {
                                                setSssData({ ...sssData, effective_date: e.target.value });
                                                setSssErrors({ ...sssErrors, effective_date: '' });
                                            }}
                                            className={`pl-10 ${sssErrors.effective_date ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {sssErrors.effective_date && (
                                        <p className="text-sm text-red-500">{sssErrors.effective_date}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Date when these rates take effect for payroll calculations
                                    </p>
                                </div>
                            </div>

                            {/* Sample Calculation */}
                            <Alert>
                                <FileText className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Sample Calculation:</strong> For an employee earning ₱20,000/month:
                                    <br />
                                    Employee contribution: ₱20,000 × {sssData.employee_rate}% = ₱
                                    {(20000 * sssData.employee_rate / 100).toFixed(2)}
                                    <br />
                                    Employer contribution: ₱20,000 × {sssData.employer_rate}% = ₱
                                    {(20000 * sssData.employer_rate / 100).toFixed(2)}
                                    <br />
                                    Total monthly contribution: ₱
                                    {(20000 * (sssData.employee_rate + sssData.employer_rate) / 100).toFixed(2)}
                                </AlertDescription>
                            </Alert>

                            {/* Save Button */}
                            <div className="flex justify-end">
                                <Button onClick={handleSssSubmit} disabled={sssSubmitting} size="lg">
                                    <Save className="h-4 w-4 mr-2" />
                                    {sssSubmitting ? 'Saving...' : 'Save SSS Rates'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* PhilHealth Tab */}
                <TabsContent value="philhealth">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Landmark className="h-5 w-5" />
                                Philippine Health Insurance Corporation (PhilHealth) Premium Rates
                            </CardTitle>
                            <CardDescription>
                                Configure PhilHealth premium rates based on R.A. 11223 (Universal Health Care Act)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Current Rates Summary */}
                            <div className="p-4 bg-green-50 dark:bg-green-950/20 rounded-lg border border-green-200 dark:border-green-800">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Total Premium Rate</p>
                                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {philhealthData.rate}%
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employee Share</p>
                                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {philhealthData.employee_share}%
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employer Share</p>
                                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {philhealthData.employer_share}%
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Rate Configuration */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_rate">
                                        Total Premium Rate (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="philhealth_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={philhealthData.rate}
                                        onChange={(e) => {
                                            setPhilhealthData({ ...philhealthData, rate: parseFloat(e.target.value) || 0 });
                                            setPhilhealthErrors({ ...philhealthErrors, rate: '' });
                                        }}
                                        className={philhealthErrors.rate ? 'border-red-500' : ''}
                                    />
                                    {philhealthErrors.rate && <p className="text-sm text-red-500">{philhealthErrors.rate}</p>}
                                    <p className="text-xs text-muted-foreground">Current mandated rate: 5.0%</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_effective_date">
                                        Effective Date <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="philhealth_effective_date"
                                            type="date"
                                            value={philhealthData.effective_date}
                                            onChange={(e) => {
                                                setPhilhealthData({ ...philhealthData, effective_date: e.target.value });
                                                setPhilhealthErrors({ ...philhealthErrors, effective_date: '' });
                                            }}
                                            className={`pl-10 ${philhealthErrors.effective_date ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {philhealthErrors.effective_date && (
                                        <p className="text-sm text-red-500">{philhealthErrors.effective_date}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_employee_share">
                                        Employee Share (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="philhealth_employee_share"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={philhealthData.employee_share}
                                        onChange={(e) => {
                                            setPhilhealthData({
                                                ...philhealthData,
                                                employee_share: parseFloat(e.target.value) || 0,
                                            });
                                            setPhilhealthErrors({ ...philhealthErrors, employee_share: '' });
                                        }}
                                        className={philhealthErrors.employee_share ? 'border-red-500' : ''}
                                    />
                                    {philhealthErrors.employee_share && (
                                        <p className="text-sm text-red-500">{philhealthErrors.employee_share}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Standard: 2.5% (50% of total)</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_employer_share">
                                        Employer Share (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="philhealth_employer_share"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={philhealthData.employer_share}
                                        onChange={(e) => {
                                            setPhilhealthData({
                                                ...philhealthData,
                                                employer_share: parseFloat(e.target.value) || 0,
                                            });
                                            setPhilhealthErrors({ ...philhealthErrors, employer_share: '' });
                                        }}
                                        className={philhealthErrors.employer_share ? 'border-red-500' : ''}
                                    />
                                    {philhealthErrors.employer_share && (
                                        <p className="text-sm text-red-500">{philhealthErrors.employer_share}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Standard: 2.5% (50% of total)</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_min_salary">Minimum Salary Base (₱)</Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                        <Input
                                            id="philhealth_min_salary"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={philhealthData.min_salary}
                                            onChange={(e) => {
                                                setPhilhealthData({
                                                    ...philhealthData,
                                                    min_salary: parseFloat(e.target.value) || 0,
                                                });
                                                setPhilhealthErrors({ ...philhealthErrors, min_salary: '' });
                                            }}
                                            className={`pl-8 ${philhealthErrors.min_salary ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {philhealthErrors.min_salary && (
                                        <p className="text-sm text-red-500">{philhealthErrors.min_salary}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Current minimum: ₱10,000/month</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="philhealth_max_salary">
                                        Maximum Salary Base (₱) <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                        <Input
                                            id="philhealth_max_salary"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={philhealthData.max_salary}
                                            onChange={(e) => {
                                                setPhilhealthData({
                                                    ...philhealthData,
                                                    max_salary: parseFloat(e.target.value) || 0,
                                                });
                                                setPhilhealthErrors({ ...philhealthErrors, max_salary: '' });
                                            }}
                                            className={`pl-8 ${philhealthErrors.max_salary ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {philhealthErrors.max_salary && (
                                        <p className="text-sm text-red-500">{philhealthErrors.max_salary}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Current maximum: ₱100,000/month</p>
                                </div>
                            </div>

                            {/* Sample Calculation */}
                            <Alert>
                                <FileText className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Sample Calculation:</strong> For an employee earning ₱25,000/month:
                                    <br />
                                    Employee premium: ₱25,000 × {philhealthData.employee_share}% = ₱
                                    {(25000 * philhealthData.employee_share / 100).toFixed(2)}
                                    <br />
                                    Employer premium: ₱25,000 × {philhealthData.employer_share}% = ₱
                                    {(25000 * philhealthData.employer_share / 100).toFixed(2)}
                                    <br />
                                    Total monthly premium: ₱{(25000 * philhealthData.rate / 100).toFixed(2)}
                                </AlertDescription>
                            </Alert>

                            {/* Save Button */}
                            <div className="flex justify-end">
                                <Button onClick={handlePhilhealthSubmit} disabled={philhealthSubmitting} size="lg">
                                    <Save className="h-4 w-4 mr-2" />
                                    {philhealthSubmitting ? 'Saving...' : 'Save PhilHealth Rates'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Pag-IBIG Tab */}
                <TabsContent value="pagibig">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Home className="h-5 w-5" />
                                Home Development Mutual Fund (Pag-IBIG) Contribution Rates
                            </CardTitle>
                            <CardDescription>Configure Pag-IBIG contribution rates based on R.A. 9679 (HDMF Law)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Current Rates Summary */}
                            <div className="p-4 bg-orange-50 dark:bg-orange-950/20 rounded-lg border border-orange-200 dark:border-orange-800">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employee Rate</p>
                                        <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                            {pagibigData.employee_rate}%
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Employer Rate</p>
                                        <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                            {pagibigData.employer_rate}%
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">Total Contribution</p>
                                        <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                            {pagibigData.employee_rate + pagibigData.employer_rate}%
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Rate Configuration */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="pagibig_employee_rate">
                                        Employee Contribution Rate (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="pagibig_employee_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={pagibigData.employee_rate}
                                        onChange={(e) => {
                                            setPagibigData({ ...pagibigData, employee_rate: parseFloat(e.target.value) || 0 });
                                            setPagibigErrors({ ...pagibigErrors, employee_rate: '' });
                                        }}
                                        className={pagibigErrors.employee_rate ? 'border-red-500' : ''}
                                    />
                                    {pagibigErrors.employee_rate && (
                                        <p className="text-sm text-red-500">{pagibigErrors.employee_rate}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Mandated rate: 2% (or 1% for employees earning ≤ ₱1,500/month)
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pagibig_employer_rate">
                                        Employer Contribution Rate (%) <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="pagibig_employer_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={pagibigData.employer_rate}
                                        onChange={(e) => {
                                            setPagibigData({ ...pagibigData, employer_rate: parseFloat(e.target.value) || 0 });
                                            setPagibigErrors({ ...pagibigErrors, employer_rate: '' });
                                        }}
                                        className={pagibigErrors.employer_rate ? 'border-red-500' : ''}
                                    />
                                    {pagibigErrors.employer_rate && (
                                        <p className="text-sm text-red-500">{pagibigErrors.employer_rate}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Mandated rate: 2%</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pagibig_max_salary">
                                        Maximum Salary Base (₱) <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">₱</span>
                                        <Input
                                            id="pagibig_max_salary"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            value={pagibigData.max_salary}
                                            onChange={(e) => {
                                                setPagibigData({ ...pagibigData, max_salary: parseFloat(e.target.value) || 0 });
                                                setPagibigErrors({ ...pagibigErrors, max_salary: '' });
                                            }}
                                            className={`pl-8 ${pagibigErrors.max_salary ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {pagibigErrors.max_salary && (
                                        <p className="text-sm text-red-500">{pagibigErrors.max_salary}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">Current maximum: ₱5,000/month</p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pagibig_effective_date">
                                        Effective Date <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="pagibig_effective_date"
                                            type="date"
                                            value={pagibigData.effective_date}
                                            onChange={(e) => {
                                                setPagibigData({ ...pagibigData, effective_date: e.target.value });
                                                setPagibigErrors({ ...pagibigErrors, effective_date: '' });
                                            }}
                                            className={`pl-10 ${pagibigErrors.effective_date ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {pagibigErrors.effective_date && (
                                        <p className="text-sm text-red-500">{pagibigErrors.effective_date}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Date when these rates take effect for payroll calculations
                                    </p>
                                </div>
                            </div>

                            {/* Sample Calculation */}
                            <Alert>
                                <FileText className="h-4 w-4" />
                                <AlertDescription>
                                    <strong>Sample Calculation:</strong> For an employee earning ₱18,000/month:
                                    <br />
                                    Salary base (capped at ₱{pagibigData.max_salary.toLocaleString()}): ₱
                                    {Math.min(18000, pagibigData.max_salary).toLocaleString()}
                                    <br />
                                    Employee contribution: ₱{Math.min(18000, pagibigData.max_salary).toLocaleString()} ×{' '}
                                    {pagibigData.employee_rate}% = ₱
                                    {(Math.min(18000, pagibigData.max_salary) * pagibigData.employee_rate / 100).toFixed(2)}
                                    <br />
                                    Employer contribution: ₱{Math.min(18000, pagibigData.max_salary).toLocaleString()} ×{' '}
                                    {pagibigData.employer_rate}% = ₱
                                    {(Math.min(18000, pagibigData.max_salary) * pagibigData.employer_rate / 100).toFixed(2)}
                                    <br />
                                    Total monthly contribution: ₱
                                    {(Math.min(18000, pagibigData.max_salary) * (pagibigData.employee_rate + pagibigData.employer_rate) / 100).toFixed(2)}
                                </AlertDescription>
                            </Alert>

                            {/* Save Button */}
                            <div className="flex justify-end">
                                <Button onClick={handlePagibigSubmit} disabled={pagibigSubmitting} size="lg">
                                    <Save className="h-4 w-4 mr-2" />
                                    {pagibigSubmitting ? 'Saving...' : 'Save Pag-IBIG Rates'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}
