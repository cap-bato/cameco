import { Head, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus } from 'lucide-react';
import { PermissionGate } from '@/components/permission-gate';
import { AttendanceRecordsTable } from '@/components/timekeeping/attendance-records-table';
import { AttendanceEntryModal, type AttendanceFormData } from '@/components/timekeeping/attendance-entry-modal';
import { AttendanceDetailModal } from '@/components/timekeeping/attendance-detail-modal';
import { AttendanceCorrectionModal, type CorrectionFormData } from '@/components/timekeeping/attendance-correction-modal';
import { AttendanceFilters, type AttendanceFiltersState } from '@/components/timekeeping/attendance-filters';
import { AttendanceRecord, EmployeeBasic } from '@/types/timekeeping-pages';

interface AttendanceIndexProps {
    attendance: AttendanceRecord[];
    employees: EmployeeBasic[];
    summary: {
        total_records: number;
        present_count: number;
        present_rate: number;
        late_count: number;
        absent_count: number;
    };
}

export default function AttendanceIndex() {
    const { attendance = [], employees = [], summary = { total_records: 0, present_count: 0, present_rate: 0, late_count: 0, absent_count: 0 } } = usePage().props as unknown as AttendanceIndexProps;

    // Modal states
    const [isEntryModalOpen, setIsEntryModalOpen] = useState(false);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [isCorrectionModalOpen, setIsCorrectionModalOpen] = useState(false);
    const [selectedRecord, setSelectedRecord] = useState<AttendanceRecord | null>(null);

    // Filter states
    const [filters, setFilters] = useState<AttendanceFiltersState>({
        search: '',
        status: undefined,
        source: undefined,
    });

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'Attendance', href: '/hr/timekeeping/attendance' },
    ];

    // Filter attendance records based on active filters
    const filteredRecords = useMemo(() => {
        return attendance.filter(record => {
            if (filters.search && !record.employee_name.toLowerCase().includes(filters.search.toLowerCase())) {
                return false;
            }
            if (filters.status && record.status !== filters.status) {
                return false;
            }
            if (filters.source && record.source !== filters.source) {
                return false;
            }
            return true;
        });
    }, [attendance, filters]);

    const handleViewRecord = (record: AttendanceRecord) => {
        setSelectedRecord(record);
        setIsDetailModalOpen(true);
    };

    const handleEditRecord = (record: AttendanceRecord) => {
        setSelectedRecord(record);
        setIsDetailModalOpen(false);
        setIsEntryModalOpen(true);
    };

    const handleCorrectRecord = (record: AttendanceRecord) => {
        setSelectedRecord(record);
        setIsCorrectionModalOpen(true);
    };

    const handleSaveEntry = (data: AttendanceFormData) => {
        console.log('Save attendance entry:', data);
        setIsEntryModalOpen(false);
        // API call would go here
    };

    const handleSaveCorrection = (data: CorrectionFormData) => {
        console.log('Save correction:', data);
        setIsCorrectionModalOpen(false);
        // API call would go here
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance Records" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex flex-col">
                        <h1 className="text-3xl font-bold">Attendance Records</h1>
                        <p className="text-gray-600">Track and manage attendance records</p>
                        
                    </div>
                    <PermissionGate permission="hr.timekeeping.attendance.create">
                        <div>
                            <Button onClick={() => setIsEntryModalOpen(true)} className="gap-2">
                                <Plus className="h-4 w-4" />
                                Add Manual Entry
                            </Button>
                        </div>
                    </PermissionGate>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Records</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_records}</div>
                            <p className="text-xs text-gray-500">today</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Present</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{summary.present_count}</div>
                            <p className="text-xs text-gray-500">{summary.present_rate}%</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Late</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{summary.late_count}</div>
                            <p className="text-xs text-gray-500">arrivals</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Absent</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{summary.absent_count}</div>
                            <p className="text-xs text-gray-500">employees</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <AttendanceFilters
                    filters={filters}
                    onFiltersChange={setFilters}
                />

                {/* Attendance Records Table */}
                <AttendanceRecordsTable
                    records={filteredRecords}
                    onViewRecord={handleViewRecord}
                />

                {/* Modals */}
                <AttendanceEntryModal
                    isOpen={isEntryModalOpen}
                    onClose={() => {
                        setIsEntryModalOpen(false);
                        setSelectedRecord(null);
                    }}
                    onSave={handleSaveEntry}
                    employees={employees}
                    record={selectedRecord}
                />

                {selectedRecord && (
                    <>
                        <AttendanceDetailModal
                            isOpen={isDetailModalOpen}
                            onClose={() => {
                                setIsDetailModalOpen(false);
                                setSelectedRecord(null);
                            }}
                            record={selectedRecord}
                            onEdit={() => handleEditRecord(selectedRecord)}
                            onCorrect={() => handleCorrectRecord(selectedRecord)}
                        />

                        <AttendanceCorrectionModal
                            isOpen={isCorrectionModalOpen}
                            onClose={() => {
                                setIsCorrectionModalOpen(false);
                                setSelectedRecord(null);
                            }}
                            record={selectedRecord}
                            onSave={handleSaveCorrection}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
