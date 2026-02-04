<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use App\Models\DailyAttendanceSummary;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\RfidDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records with real data.
     */
    public function index(Request $request): Response
    {
        // Build query for daily attendance summaries with eager loading (Task 7.2.1: Fix N+1)
        $query = DailyAttendanceSummary::with([
            'employee:id,employee_number,profile_id,department_id,status',
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'workSchedule:id,name,time_in,time_out',
            'leaveRequest:id,leave_type'
        ])->orderBy('attendance_date', 'desc');

        // Apply date filter (default to current month)
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        
        $query->whereBetween('attendance_date', [$dateFrom, $dateTo]);

        // Apply other filters
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->filled('status')) {
            // Map status to boolean fields
            switch ($request->status) {
                case 'present':
                    $query->where('is_present', true)->where('is_late', false);
                    break;
                case 'late':
                    $query->where('is_late', true);
                    break;
                case 'absent':
                    $query->where('is_absent', true);
                    break;
                case 'on_leave':
                    $query->whereNotNull('leave_request_id');
                    break;
            }
        }

        // Get records
        $records = $query->limit(100)->get();

        // Transform for frontend
        $attendance = $records->map(function ($record) {
            $employee = $record->employee;
            
            // Determine status
            $status = 'absent';
            if ($record->is_present && !$record->is_late) {
                $status = 'present';
            } elseif ($record->is_late) {
                $status = 'late';
            } elseif ($record->leave_request_id) {
                $status = 'on_leave';
            }
            
            return [
                'id' => $record->id,
                'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                'employee_name' => $employee && $employee->profile ? 
                    "{$employee->profile->first_name} {$employee->profile->last_name}" : 
                    'Unknown Employee',
                'date' => $record->attendance_date->format('Y-m-d'),
                'time_in' => $record->time_in ? $record->time_in->format('H:i:s') : null,
                'time_out' => $record->time_out ? $record->time_out->format('H:i:s') : null,
                'break_start' => $record->break_start ? $record->break_start->format('H:i:s') : null,
                'break_end' => $record->break_end ? $record->break_end->format('H:i:s') : null,
                'total_hours' => round($record->total_hours ?? 0, 2),
                'overtime_hours' => round($record->overtime_hours ?? 0, 2),
                'status' => $status,
                'source' => $record->source ?? 'edge_machine',
                'is_late' => $record->is_late,
                'late_minutes' => $record->late_minutes ?? 0,
                'is_corrected' => $record->correction_applied,
                'notes' => $record->notes,
            ];
        });

        // Calculate summary statistics
        $summary = [
            'total_records' => $records->count(),
            'present_count' => $records->where('is_present', true)->where('is_late', false)->count(),
            'late_count' => $records->where('is_late', true)->count(),
            'absent_count' => $records->where('is_absent', true)->count(),
            'on_leave_count' => $records->whereNotNull('leave_request_id')->count(),
            'present_rate' => $records->count() > 0 ? 
                round(($records->where('is_present', true)->count() / $records->count()) * 100, 1) : 0,
        ];

        // Get employee list for dropdown
        $employees = Employee::with('profile:id,first_name,last_name')
            ->select('id', 'employee_number', 'profile_id')
            ->where('status', 'active')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_id' => $emp->employee_number,
                'name' => $emp->profile ? "{$emp->profile->first_name} {$emp->profile->last_name}" : 'Unknown',
            ]);

        return Inertia::render('HR/Timekeeping/Attendance/Index', [
            'attendance' => $attendance,
            'summary' => $summary,
            'employees' => $employees,
            'filters' => $request->only(['department_id', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Show the form for creating a new attendance record.
     */
    public function create(): Response
    {
        return Inertia::render('HR/Timekeeping/Attendance/Create', [
            'employees' => $this->getMockEmployees(),
            'edge_devices' => $this->getMockEdgeDevices(),
        ]);
    }

    /**
     * Store a newly created attendance record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'time_in' => 'required',
            'time_out' => 'nullable',
            'break_start' => 'nullable',
            'break_end' => 'nullable',
            'source' => 'required|in:edge_machine,manual,imported',
            'device_id' => 'nullable|string',
            'manual_entry_reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record created successfully',
            'data' => array_merge($validated, ['id' => rand(1000, 9999)]),
        ]);
    }

    /**
     * Display the specified attendance record.
     */
    public function show(int $id): Response
    {
        $record = $this->getMockAttendanceRecords()[$id - 1] ?? null;

        if (!$record) {
            abort(404, 'Attendance record not found');
        }

        return Inertia::render('HR/Timekeeping/Attendance/Show', [
            'attendance' => $record,
            'correction_history' => $this->getMockCorrectionHistory($id),
        ]);
    }

    /**
     * Show the form for editing the specified attendance record.
     */
    public function edit(int $id): Response
    {
        $record = $this->getMockAttendanceRecords()[$id - 1] ?? null;

        if (!$record) {
            abort(404, 'Attendance record not found');
        }

        return Inertia::render('HR/Timekeeping/Attendance/Edit', [
            'attendance' => $record,
            'employees' => $this->getMockEmployees(),
            'edge_devices' => $this->getMockEdgeDevices(),
        ]);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'time_in' => 'required',
            'time_out' => 'nullable',
            'break_start' => 'nullable',
            'break_end' => 'nullable',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record updated successfully',
            'data' => array_merge($validated, ['id' => $id]),
        ]);
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Attendance record deleted successfully',
        ]);
    }

    /**
     * Bulk attendance entry for multiple employees.
     */
    public function bulkEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employees' => 'required|array',
            'employees.*.employee_id' => 'required|integer',
            'date' => 'required|date',
            'time_in' => 'required',
            'source' => 'required|in:edge_machine,manual,imported',
            'manual_entry_reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => count($validated['employees']) . ' attendance records created successfully',
            'data' => ['count' => count($validated['employees'])],
        ]);
    }

    /**
     * Get daily attendance summary for a specific date.
     */
    public function daily(string $date): JsonResponse
    {
        $records = array_filter(
            $this->getMockAttendanceRecords(),
            fn($r) => $r['date'] === $date
        );

        $summary = [
            'date' => $date,
            'total_employees' => 150,
            'present' => count(array_filter($records, fn($r) => $r['status'] === 'present')),
            'late' => count(array_filter($records, fn($r) => $r['status'] === 'late')),
            'absent' => count(array_filter($records, fn($r) => $r['status'] === 'absent')),
            'on_leave' => count(array_filter($records, fn($r) => $r['status'] === 'on_leave')),
            'present_rate' => 92.5,
            'records' => array_values($records),
        ];

        return response()->json($summary);
    }

    /**
     * Correct an attendance record (direct correction by HR).
     */
    public function correctAttendance(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'corrected_time_in' => 'nullable',
            'corrected_time_out' => 'nullable',
            'corrected_break_start' => 'nullable',
            'corrected_break_end' => 'nullable',
            'correction_reason' => 'required|string',
            'justification' => 'required|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record corrected successfully',
            'data' => array_merge($validated, [
                'id' => $id,
                'is_corrected' => true,
                'corrected_by' => 'HR Manager',
                'corrected_at' => now()->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    /**
     * Get correction history for an attendance record.
     */
    public function correctionHistory(int $id): JsonResponse
    {
        $history = $this->getMockCorrectionHistory($id);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Generate mock attendance records.
     */
    private function getMockAttendanceRecords(): array
    {
        $records = [];
        $statuses = ['present', 'late', 'absent', 'on_leave', 'undertime'];
        $sources = ['edge_machine', 'manual', 'imported'];
        $devices = ['DEVICE-001', 'DEVICE-002', 'DEVICE-003'];
        $departments = [
            ['id' => 3, 'name' => 'Rolling Mill 3'],
            ['id' => 4, 'name' => 'Wire Mill'],
            ['id' => 5, 'name' => 'Quality Control'],
            ['id' => 6, 'name' => 'Maintenance'],
        ];

        for ($i = 1; $i <= 60; $i++) {
            $source = $sources[array_rand($sources)];
            $status = $statuses[array_rand($statuses)];
            $dept = $departments[array_rand($departments)];
            $isCorrected = $i % 15 === 0; // Every 15th record has correction history
            
            $records[] = [
                'id' => $i,
                'employee_id' => ($i % 20) + 1,
                'employee_name' => 'Employee ' . (($i % 20) + 1),
                'employee_number' => 'EMP' . str_pad(($i % 20) + 1, 3, '0', STR_PAD_LEFT),
                'date' => now()->subDays(rand(0, 7))->format('Y-m-d'),
                'time_in' => '08:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00',
                'time_out' => $status !== 'absent' ? '17:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00' : null,
                'break_start' => $status !== 'absent' ? '12:00:00' : null,
                'break_end' => $status !== 'absent' ? '13:00:00' : null,
                'total_hours' => $status !== 'absent' ? round(8 + (rand(-20, 20) / 10), 2) : 0,
                'regular_hours' => $status !== 'absent' ? 8.0 : 0,
                'overtime_hours' => $status !== 'absent' ? round(rand(0, 2), 1) : 0,
                'break_duration' => $status !== 'absent' ? 60 : 0,
                'source' => $source,
                'device_id' => $source === 'edge_machine' ? $devices[array_rand($devices)] : null,
                'device_location' => $source === 'edge_machine' ? 'Main Entrance' : null,
                'manual_entry_reason' => $source === 'manual' ? ['Lost Card', 'Machine Failure', 'Forgot to Tap'][array_rand(['Lost Card', 'Machine Failure', 'Forgot to Tap'])] : null,
                'status' => $status,
                'is_late' => $status === 'late',
                'late_minutes' => $status === 'late' ? rand(5, 45) : 0,
                'is_corrected' => $isCorrected,
                'original_time_in' => $isCorrected ? '08:45:00' : null,
                'correction_reason' => $isCorrected ? 'Edge Machine Error' : null,
                'corrected_by' => $isCorrected ? 'HR Manager' : null,
                'corrected_at' => $isCorrected ? now()->subDays(1)->format('Y-m-d H:i:s') : null,
                'department_id' => $dept['id'],
                'department_name' => $dept['name'],
                'schedule_name' => 'Standard Day Shift',
                'scheduled_time_in' => '08:00:00',
                'scheduled_time_out' => '17:00:00',
                'notes' => $source === 'manual' ? 'Manual entry due to card issues' : null,
                'created_at' => now()->subDays(rand(0, 7))->format('Y-m-d H:i:s'),
                'updated_at' => now()->subDays(rand(0, 7))->format('Y-m-d H:i:s'),
            ];
        }

        return $records;
    }

    /**
     * Get mock correction history for an attendance record.
     */
    private function getMockCorrectionHistory(int $recordId): array
    {
        if ($recordId % 15 !== 0) {
            return [];
        }

        return [
            [
                'id' => 1,
                'attendance_record_id' => $recordId,
                'field_corrected' => 'time_in',
                'original_value' => '08:45:00',
                'corrected_value' => '08:00:00',
                'correction_reason' => 'Edge Machine Error',
                'justification' => 'Employee reported that edge machine failed to record correct time. Security camera confirmed employee arrived at 8:00 AM.',
                'corrected_by' => 'HR Manager',
                'corrected_by_name' => 'Admin User',
                'corrected_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Get mock employees list.
     */
    private function getMockEmployees(): array
    {
        $employees = [];
        for ($i = 1; $i <= 20; $i++) {
            $employees[] = [
                'id' => $i,
                'name' => 'Employee ' . $i,
                'employee_number' => 'EMP' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'department_id' => ($i % 4) + 3,
                'department_name' => ['Rolling Mill 3', 'Wire Mill', 'Quality Control', 'Maintenance'][$i % 4],
                'position' => 'Production Worker',
            ];
        }
        return $employees;
    }

    /**
     * Get mock edge devices.
     */
    private function getMockEdgeDevices(): array
    {
        return [
            ['id' => 'DEVICE-001', 'name' => 'Main Entrance', 'location' => 'Building A - Main Gate', 'status' => 'online'],
            ['id' => 'DEVICE-002', 'name' => 'Production Floor', 'location' => 'Rolling Mill 3', 'status' => 'online'],
            ['id' => 'DEVICE-003', 'name' => 'Office Entrance', 'location' => 'Administration Building', 'status' => 'offline'],
            ['id' => 'DEVICE-004', 'name' => 'Wire Mill Gate', 'location' => 'Building B - Wire Mill', 'status' => 'online'],
        ];
    }
}
