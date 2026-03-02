import { Head, usePage, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, Eye, Edit, ChevronLeft, ChevronRight } from 'lucide-react';
import { OvertimeFormModal } from '@/components/timekeeping/overtime-form-modal';
import { OvertimeDetailModal } from '@/components/timekeeping/overtime-detail-modal';
import { OvertimeRecord, EmployeeBasic } from '@/types/timekeeping-pages';
import { PermissionGate, usePermission } from '@/components/permission-gate';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: any;
    meta: any;
}

interface OvertimeRequestsIndexProps {
    overtime: PaginatedResponse<OvertimeRecord>;
    employees: EmployeeBasic[];
    summary: {
        total_records: number;
        planned: number;
        in_progress: number;
        completed: number;
        total_ot_hours: number;
    };
}

export default function OvertimeIndex() {
    const { overtime = { data: [] }, employees = [], summary } = usePage().props as unknown as OvertimeRequestsIndexProps;
    const { hasPermission } = usePermission();

    // Modal states
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [selectedRecord, setSelectedRecord] = useState<OvertimeRecord | null>(null);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'Overtime', href: '/hr/timekeeping/overtime' },
    ];

    const handleViewRecord = (record: OvertimeRecord) => {
        setSelectedRecord(record);
        setIsDetailModalOpen(true);
    };

    const handleEditRecord = (record: OvertimeRecord) => {
        setSelectedRecord(record);
        setIsFormModalOpen(true);
    };

    const handleApproveRecord = (record: OvertimeRecord) => {
        console.log('Approve overtime record:', record);
        // API call would go here to update status to 'in_progress'
    };

    const handleSaveForm = (data: any) => {
        console.log('Save overtime record:', data);
        setIsFormModalOpen(false);
        setSelectedRecord(null);
        // API call would go here
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Overtime Requests" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Overtime Requests</h1>
                        <p className="text-gray-600">Track and manage overtime records</p>
                    </div>

                    <PermissionGate permission="hr.timekeeping.overtime.create">
                        <div>
                            <Button onClick={() => setIsFormModalOpen(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Create Overtime Record
                            </Button>
                        </div>
                    </PermissionGate>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Records</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_records}</div>
                            <p className="text-xs text-gray-500">all time</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Planned</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{summary.planned}</div>
                            <p className="text-xs text-gray-500">upcoming</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">In Progress</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{summary.in_progress}</div>
                            <p className="text-xs text-gray-500">ongoing</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Completed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{summary.completed}</div>
                            <p className="text-xs text-gray-500">finished</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total OT Hours</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">{summary.total_ot_hours}</div>
                            <p className="text-xs text-gray-500">hours</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Overtime Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Overtime Records</CardTitle>
                        <CardDescription>Overview of all overtime requests and approvals</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left py-3 px-4 font-semibold">Employee</th>
                                        <th className="text-left py-3 px-4 font-semibold">Date</th>
                                        <th className="text-left py-3 px-4 font-semibold">Planned Hours</th>
                                        <th className="text-left py-3 px-4 font-semibold">Actual Hours</th>
                                        <th className="text-left py-3 px-4 font-semibold">Reason</th>
                                        <th className="text-left py-3 px-4 font-semibold">Status</th>
                                        <th className="text-right py-3 px-4 font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {overtime.data.map((record) => (
                                        <tr key={record.id} className="border-b hover:bg-muted/50">
                                            <td className="py-3 px-4">{record.employee_name}</td>
                                            <td className="py-3 px-4">{record.overtime_date}</td>
                                            <td className="py-3 px-4">{record.planned_hours}h</td>
                                            <td className="py-3 px-4">{record.actual_hours ? `${record.actual_hours}h` : '-'}</td>
                                            <td className="py-3 px-4 text-xs">{record.reason.substring(0, 30)}...</td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                    record.status === 'completed' ? 'bg-green-100 text-green-700' :
                                                    record.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                                                    record.status === 'planned' ? 'bg-yellow-100 text-yellow-700' :
                                                    'bg-red-100 text-red-700'
                                                }`}>
                                                    {record.status}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <Button variant="outline" size="sm" onClick={() => handleViewRecord(record)}>View</Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination Controls */}
                        <div className="flex items-center justify-between mt-6 pt-6 border-t">
                            <div className="text-sm text-gray-600">
                                Showing {overtime.data.length > 0 ? ((overtime.current_page - 1) * overtime.per_page) + 1 : 0} to{' '}
                                {Math.min(overtime.current_page * overtime.per_page, overtime.total)} of {overtime.total} records
                            </div>

                            <div className="flex items-center gap-2">
                                <Link href={overtime.links?.prev} preserve={['page']}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!overtime.links?.prev}
                                        className="gap-1"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>
                                </Link>

                                <div className="flex items-center gap-1">
                                    {Array.from({ length: overtime.last_page }, (_, i) => i + 1).map((page) => (
                                        <Link
                                            key={page}
                                            href={`/hr/timekeeping/overtime?page=${page}`}
                                            preserve={['filters']}
                                        >
                                            <Button
                                                variant={page === overtime.current_page ? "default" : "outline"}
                                                size="sm"
                                                className="h-8 w-8 p-0"
                                            >
                                                {page}
                                            </Button>
                                        </Link>
                                    ))}
                                </div>

                                <Link href={overtime.links?.next} preserve={['page']}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!overtime.links?.next}
                                        className="gap-1"
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Modals */}
                <OvertimeFormModal
                    isOpen={isFormModalOpen && !selectedRecord}
                    onClose={() => setIsFormModalOpen(false)}
                    onSave={handleSaveForm}
                    employees={employees}
                    record={null}
                />

                {selectedRecord && (
                    <>
                        <OvertimeDetailModal
                            isOpen={isDetailModalOpen}
                            onClose={() => {
                                setIsDetailModalOpen(false);
                                setSelectedRecord(null);
                            }}
                            record={selectedRecord}
                            onEdit={hasPermission('hr.timekeeping.overtime.update') ? () => handleEditRecord(selectedRecord) : undefined}
                            onApprove={hasPermission('hr.timekeeping.overtime.approve') ? () => handleApproveRecord(selectedRecord) : undefined}
                        />

                        <OvertimeFormModal
                            isOpen={isFormModalOpen && selectedRecord?.status === 'planned'}
                            onClose={() => {
                                setIsFormModalOpen(false);
                                setSelectedRecord(null);
                            }}
                            onSave={handleSaveForm}
                            employees={employees}
                            record={selectedRecord}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
