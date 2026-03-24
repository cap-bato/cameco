<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTimelineController extends Controller
{
    public function show(Request $request, int $employeeId): Response
    {
        $date = Carbon::parse($request->get('date', today()->toDateString()));

        $employee = Employee::with(['profile', 'department', 'position'])
            ->findOrFail($employeeId);

        $events = AttendanceEvent::where('employee_id', $employeeId)
            ->whereDate('event_date', $date)
            ->orderBy('event_time', 'asc')
            ->get()
            ->map(fn($e) => [
                'id'         => $e->id,
                'eventType'  => $e->event_type,
                'timestamp'  => $e->event_time->toISOString(),
                'deviceId'   => $e->device_id,
                'sourceType' => $e->source_type ?? 'rfid',
                'isManual'   => $e->is_manual ?? false,
            ]);

        $summary = DailyAttendanceSummary::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();

        return Inertia::render('HR/Timekeeping/EmployeeTimeline', [
            'employee' => [
                'id'         => $employee->id,
                'employeeId' => $employee->employee_number,
                'name'       => $employee->full_name,
                'department' => $employee->department?->name,
                'position'   => $employee->position?->title,
                'photo'      => $employee->profile?->profile_picture_path ?? null,
            ],
            'events'  => $events,
            'summary' => $summary ? [
                'timeIn'        => $summary->time_in,
                'timeOut'       => $summary->time_out,
                'totalHours'    => $summary->total_hours_worked,
                'isPresent'     => $summary->is_present,
                'isLate'        => $summary->is_late,
                'lateMinutes'   => $summary->late_minutes,
                'isOvertime'    => $summary->is_overtime,
                'overtimeHours' => $summary->overtime_hours,
                'isOnLeave'     => $summary->is_on_leave,
            ] : null,
            'date' => $date->toDateString(),
        ]);
    }

}
