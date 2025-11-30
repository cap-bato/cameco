import React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { Link } from '@inertiajs/react';

interface CreateRequestProps {
    employees: Array<{ id: number; employee_number: string; name: string }>;
    leaveTypes: Array<{ id: number; code?: string; name: string; annual_entitlement?: number }>;
}

export default function CreateRequest({ employees = [], leaveTypes = [] }: CreateRequestProps) {
    const form = useForm({
        employee_id: employees.length > 0 ? employees[0].id : '',
        leave_policy_id: leaveTypes.length > 0 ? leaveTypes[0].id : '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        reason: '',
        hr_notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

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
                                                {leaveTypes.map((t) => (
                                                    <SelectItem key={t.id} value={String(t.id)}>
                                                        <div className="flex flex-col">
                                                            <span>{t.name}</span>
                                                            {typeof t.annual_entitlement !== 'undefined' && (
                                                                <small className="text-xs text-muted-foreground">{Number(t.annual_entitlement) === 1 ? `${t.annual_entitlement} day` : `${t.annual_entitlement} days`}</small>
                                                            )}
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                    </Select>
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
                                <Button type="submit" disabled={form.processing}>Submit Leave Request</Button>
                                <Button type="button" variant="outline" onClick={() => router.visit('/hr/leave/requests')}>Cancel</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
