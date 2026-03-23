import React, { useState, useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { Link } from '@inertiajs/react';

interface EmployeeBalance {
    leave_policy_id: number;
    leave_type_name: string;
    earned: number;
    used: number;
    carried_forward: number;
    remaining: number;
}

interface CreateRequestProps {
    employees: Array<{ id: number; employee_number: string; name: string }>;
    leaveTypes: Array<{ id: number; code?: string; name: string; annual_entitlement?: number }>;
}

export default function CreateRequest({ employees = [], leaveTypes = [] }: CreateRequestProps) {
    const form = useForm({
        employee_id: employees[0]?.id ?? '',
        leave_policy_id: leaveTypes[0]?.id ?? '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        reason: '',
        hr_notes: '',
    });

    const [employeeBalances, setEmployeeBalances] = useState<Record<number, EmployeeBalance>>({});
    const [loadingBalances, setLoadingBalances] = useState(false);

    // Fetch employee balances when employee is selected
    useEffect(() => {
        if (form.data.employee_id) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setLoadingBalances(true);
            fetch(`/hr/leave/employee/${form.data.employee_id}/balances`)
                .then(res => res.json())
                .then(data => {
                    const balanceMap: Record<number, EmployeeBalance> = {};
                    data.balances.forEach((balance: EmployeeBalance) => {
                        balanceMap[balance.leave_policy_id] = balance;
                    });
                    setEmployeeBalances(balanceMap);
                })
                .catch(err => console.error('Error fetching balances:', err))
                .finally(() => setLoadingBalances(false));
        }
    }, [form.data.employee_id]);

    // Calculate days requested
    const calculateDaysRequested = (): number => {
        try {
            // For Half Day AM/PM Leave, always count as 0.5 days
            const selectedLeaveType = leaveTypes.find(t => t.id === form.data.leave_policy_id);
            if (selectedLeaveType?.code === 'HAM' || selectedLeaveType?.code === 'HPM') {
                return 0.5;
            }

            const start = new Date(form.data.start_date);
            const end = new Date(form.data.end_date);
            
            if (isNaN(start.getTime()) || isNaN(end.getTime())) {
                return 0;
            }
            
            const diffTime = Math.abs(end.getTime() - start.getTime());
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays + 1; // Include both start and end dates
        } catch {
            return 0;
        }
    };

    const daysRequested = calculateDaysRequested();

    // Check if request exceeds remaining balance
    const checkBalanceSufficiency = (): { isSufficient: boolean; message?: string } => {
        const balance = employeeBalances[form.data.leave_policy_id];
        if (!balance) {
            return { isSufficient: true }; // No balance data yet
        }

        const remaining = balance.remaining;
        if (remaining <= 0) {
            return { isSufficient: false, message: `${remaining.toFixed(2)} days available (${balance.earned.toFixed(2)} earned, ${balance.used.toFixed(2)} used) - INSUFFICIENT BALANCE` };
        }

        if (daysRequested > remaining) {
            return { isSufficient: false, message: `${remaining.toFixed(2)} days available (${balance.earned.toFixed(2)} earned, ${balance.used.toFixed(2)} used) - INSUFFICIENT BALANCE` };
        }

        return { isSufficient: true };
    };

    const balanceCheck = checkBalanceSufficiency();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Check balance sufficiency first
        if (!balanceCheck.isSufficient) {
            form.setErrors({ leave_policy_id: balanceCheck.message || 'Insufficient balance for this leave type.' });
            return;
        }

        // quick client-side checks to give immediate feedback
        const errs: Record<string, string> = {};
        if (!String(form.data.reason || '').trim()) {
            errs.reason = 'Reason is required.';
        }
        if (!String(form.data.hr_notes || '').trim()) {
            errs.hr_notes = 'HR notes are required.';
        }

        if (Object.keys(errs).length > 0) {
            form.setErrors(errs);
            return;
        }

        router.post('/hr/leave/requests', form.data, {
            onSuccess: () => {
                // redirect handled by server; clear form to be safe
                form.reset('reason', 'hr_notes');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'HR', href: '/hr/dashboard' },
            { title: 'Leave Management', href: '/hr/leave/requests' },
            { title: 'Create', href: '/hr/leave/requests/create' },
        ]}>
            <Head title="Create Leave Request" />

            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Create Leave Request</h1>
                        <p className="text-muted-foreground mt-1">Enter leave request details submitted to HR</p>
                    </div>
                    <Link href="/hr/leave/requests">
                        <Button variant="outline">Back to Requests</Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>New Leave Request</CardTitle>
                        <CardDescription>Complete the form to create a new leave request on behalf of an employee</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={submit} className="space-y-4 max-w-2xl">
                            <div className="space-y-2">
                                <Label htmlFor="employee_id">Employee *</Label>
                                <Select
                                    value={String(form.data.employee_id)}
                                    onValueChange={(val) => form.setData('employee_id', Number(val))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select employee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((emp) => (
                                            <SelectItem key={emp.id} value={String(emp.id)}>
                                                {emp.name} • {emp.employee_number}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.employee_id && <div className="text-sm text-red-500">{form.errors.employee_id}</div>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="leave_policy_id">Leave Type *</Label>
                                    <Select
                                        value={String(form.data.leave_policy_id)}
                                        onValueChange={(val) => form.setData('leave_policy_id', Number(val))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select leave type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {leaveTypes.map((t) => {
                                                const balance = employeeBalances[t.id];
                                                const isEmergency = t.code?.toLowerCase() === 'el' || t.name?.toLowerCase().includes('emergency');
                                                const hasZeroBalance = balance && balance.remaining <= 0;
                                                const isUnavailable = hasZeroBalance && !isEmergency;
                                                
                                                return (
                                                    <SelectItem key={t.id} value={String(t.id)} disabled={isUnavailable}>
                                                        <div className="flex flex-col">
                                                            <span>{t.name}</span>
                                                            {balance ? (
                                                                <small className={`text-xs ${isUnavailable ? 'text-red-500 font-semibold' : 'text-muted-foreground'}`}>
                                                                    {balance.remaining.toFixed(2)} days available ({balance.earned.toFixed(2)} earned, {balance.used.toFixed(2)} used)
                                                                    {isUnavailable && ' - INSUFFICIENT BALANCE'}
                                                                </small>
                                                            ) : (
                                                                <small className="text-xs text-muted-foreground">
                                                                    {typeof t.annual_entitlement !== 'undefined' 
                                                                        ? `${t.annual_entitlement} days entitlement`
                                                                        : 'No balance data'}
                                                                </small>
                                                            )}
                                                        </div>
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    {loadingBalances && <p className="text-xs text-muted-foreground">Loading balance...</p>}
                                    {form.errors.leave_policy_id && <div className="text-sm text-red-500">{form.errors.leave_policy_id}</div>}
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="start_date">Start Date *</Label>
                                        <Input
                                            id="start_date"
                                            name="start_date"
                                            type="date"
                                            value={String(form.data.start_date)}
                                            onChange={(e) => form.setData('start_date', e.target.value)}
                                            required
                                        />
                                        {form.errors.start_date && <div className="text-sm text-red-500">{form.errors.start_date}</div>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="end_date">End Date *</Label>
                                        <Input
                                            id="end_date"
                                            name="end_date"
                                            type="date"
                                            value={String(form.data.end_date)}
                                            onChange={(e) => form.setData('end_date', e.target.value)}
                                            required
                                            min={form.data.start_date ? String(form.data.start_date) : undefined}
                                        />
                                        {form.errors.end_date && <div className="text-sm text-red-500">{form.errors.end_date}</div>}
                                    </div>
                                </div>

                                {daysRequested > 0 && (
                                    <div className={`p-3 rounded-md text-sm ${balanceCheck.isSufficient ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
                                        {balanceCheck.isSufficient ? (
                                            <>
                                                {daysRequested} day{daysRequested !== 1 ? 's' : ''} requested
                                                {employeeBalances[form.data.leave_policy_id] && (
                                                    <>
                                                        {' '}• {employeeBalances[form.data.leave_policy_id].remaining.toFixed(2)} available
                                                    </>
                                                )}
                                            </>
                                        ) : (
                                            balanceCheck.message || 'Insufficient balance'
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="reason">Reason *</Label>
                                <Textarea
                                    id="reason"
                                    value={form.data.reason}
                                    onChange={(e) => form.setData('reason', e.target.value)}
                                    placeholder="Reason or details provided by the employee"
                                    rows={4}
                                    required
                                />
                                {form.errors.reason && <div className="text-sm text-red-500">{form.errors.reason}</div>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="hr_notes">HR Notes (internal) *</Label>
                                <Textarea
                                    id="hr_notes"
                                    value={form.data.hr_notes}
                                    onChange={(e) => form.setData('hr_notes', e.target.value)}
                                    placeholder="Internal notes for HR processing"
                                    rows={3}
                                    required
                                />
                                {form.errors.hr_notes && <div className="text-sm text-red-500">{form.errors.hr_notes}</div>}
                            </div>

                            <div className="flex items-center gap-2">
                                <Button 
                                    type="submit" 
                                    disabled={form.processing || !balanceCheck.isSufficient}
                                >
                                    Submit Leave Request
                                </Button>
                                <Button type="button" variant="outline" onClick={() => router.visit('/hr/leave/requests')}>Cancel</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
