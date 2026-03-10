import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, UserMinus } from 'lucide-react';

interface EmployeeOption {
    id: number;
    employee_number: string;
    name: string;
    department: string | null;
}

interface CreateCaseProps {
    employees: EmployeeOption[];
    separationTypes: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Offboarding', href: '/hr/offboarding/dashboard' },
    { title: 'Cases', href: '/hr/offboarding/cases' },
    { title: 'Create Case', href: '/hr/offboarding/cases/create' },
];

export default function CreateCase({ employees, separationTypes }: CreateCaseProps) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        separation_type: '',
        last_working_day: '',
        separation_reason: '',
        notice_period_days: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hr/offboarding/cases');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Offboarding Case" />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/hr/offboarding/cases">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Cases
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <UserMinus className="h-6 w-6 text-red-500" />
                            Create Offboarding Case
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Initiate a new offboarding process for an employee.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Main Form */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Employee Selection */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Employee Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Label htmlFor="employee_id">
                                            Employee <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.employee_id}
                                            onValueChange={(val) => setData('employee_id', val)}
                                        >
                                            <SelectTrigger id="employee_id" className="mt-1">
                                                <SelectValue placeholder="Select an employee..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {employees.map((emp) => (
                                                    <SelectItem key={emp.id} value={String(emp.id)}>
                                                        <span className="font-medium">{emp.name}</span>
                                                        <span className="text-gray-500 ml-2 text-xs">
                                                            {emp.employee_number}
                                                            {emp.department ? ` · ${emp.department}` : ''}
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.employee_id && (
                                            <p className="mt-1 text-xs text-red-500">{errors.employee_id}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Separation Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Separation Details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Label htmlFor="separation_type">
                                            Separation Type <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.separation_type}
                                            onValueChange={(val) => setData('separation_type', val)}
                                        >
                                            <SelectTrigger id="separation_type" className="mt-1">
                                                <SelectValue placeholder="Select separation type..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(separationTypes).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.separation_type && (
                                            <p className="mt-1 text-xs text-red-500">{errors.separation_type}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="last_working_day">
                                            Last Working Day <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="last_working_day"
                                            type="date"
                                            className="mt-1"
                                            value={data.last_working_day}
                                            onChange={(e) => setData('last_working_day', e.target.value)}
                                            min={new Date().toISOString().split('T')[0]}
                                        />
                                        {errors.last_working_day && (
                                            <p className="mt-1 text-xs text-red-500">{errors.last_working_day}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="notice_period_days">Notice Period (Days)</Label>
                                        <Input
                                            id="notice_period_days"
                                            type="number"
                                            className="mt-1"
                                            placeholder="e.g. 30"
                                            min={0}
                                            value={data.notice_period_days}
                                            onChange={(e) => setData('notice_period_days', e.target.value)}
                                        />
                                        {errors.notice_period_days && (
                                            <p className="mt-1 text-xs text-red-500">{errors.notice_period_days}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="separation_reason">
                                            Reason for Separation <span className="text-red-500">*</span>
                                        </Label>
                                        <Textarea
                                            id="separation_reason"
                                            className="mt-1"
                                            rows={4}
                                            placeholder="Provide a detailed reason for this separation..."
                                            value={data.separation_reason}
                                            onChange={(e) => setData('separation_reason', e.target.value)}
                                            maxLength={500}
                                        />
                                        <p className="mt-1 text-xs text-gray-400 text-right">
                                            {data.separation_reason.length}/500
                                        </p>
                                        {errors.separation_reason && (
                                            <p className="mt-1 text-xs text-red-500">{errors.separation_reason}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>What happens next?</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ol className="space-y-3 text-sm text-gray-600">
                                        <li className="flex gap-2">
                                            <span className="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">1</span>
                                            <span>Offboarding case is created with a unique case number.</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">2</span>
                                            <span>Default clearance checklist items are auto-generated.</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">3</span>
                                            <span>Employee status is updated to "Offboarding".</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="flex-shrink-0 w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">4</span>
                                            <span>Relevant departments are notified to begin clearance.</span>
                                        </li>
                                    </ol>
                                </CardContent>
                            </Card>

                            <Card className="border-amber-200 bg-amber-50">
                                <CardContent className="pt-6">
                                    <p className="text-sm text-amber-800">
                                        <strong>Note:</strong> Once submitted, the employee's status will be changed to <em>Offboarding</em>. This action triggers notifications to HR coordinators and relevant department heads.
                                    </p>
                                </CardContent>
                            </Card>

                            {/* Submit */}
                            <div className="flex flex-col gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing ? 'Creating Case...' : 'Create Offboarding Case'}
                                </Button>
                                <Link href="/hr/offboarding/cases">
                                    <Button variant="outline" className="w-full" type="button">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
