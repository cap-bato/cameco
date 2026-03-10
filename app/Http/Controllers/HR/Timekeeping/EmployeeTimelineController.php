<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTimelineController extends Controller
{
    /**
     * Display employee timeline for a specific date.
     * 
     * @param Request $request
     * @param int $employeeId
     * @return Response
     */
    public function show(Request $request, int $employeeId): Response
    {
        $date = $request->get('date', now()->toDateString());
        
        $employee = $this->getEmployeeInfo($employeeId);
        $events = $this->generateMockTimelineEvents($employeeId, $date);
        $schedule = $this->getEmployeeSchedule($employeeId, $date);
        $summary = $this->generateSummary($events, $schedule);
        
        return Inertia::render('HR/Timekeeping/EmployeeTimeline', [
            'employee' => $employee,
            'events' => $events,
            'schedule' => $schedule,
            'summary' => $summary,
            'date' => $date,
        ]);
    }

    /**
     * Get employee information.
     * 
     * @param int $employeeId
     * @return array
     */
    private function getEmployeeInfo(int $employeeId): array
    {
        // Mock employee data
        $employees = [
            1 => [
                'id' => 1,
                'employee_id' => 'EMP-2024-001',
                'name' => 'Juan Dela Cruz',
                'department' => 'Manufacturing',
                'position' => 'Production Worker',
                'photo' => null,
            ],
            2 => [
                'id' => 2,
                'employee_id' => 'EMP-2024-002',
                'name' => 'Maria Santos',
                'department' => 'Quality Control',
                'position' => 'QC Inspector',
                'photo' => null,
            ],
        ];
        
        return $employees[$employeeId] ?? [
            'id' => $employeeId,
            'employee_id' => "EMP-2024-{$employeeId}",
            'name' => 'Unknown Employee',
            'department' => 'Unknown',
            'position' => 'Unknown',
            'photo' => null,
        ];
    }

    /**
     * Generate mock timeline events.
     * 
     * @param int $employeeId
     * @param string $date
     * @return array
     */
    private function generateMockTimelineEvents(int $employeeId, string $date): array
    {
        $baseDate = \Carbon\Carbon::parse($date);
        
        return [
            [
                'id' => 1,
                'sequenceId' => 12345,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'time_in',
                'timestamp' => $baseDate->copy()->setTime(8, 5, 0)->toISOString(),
                'deviceLocation' => 'Gate 1 - Main Entrance',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(8, 0, 0)->toISOString(),
                'variance' => 5, // 5 minutes late
                'violationType' => 'late_arrival',
            ],
            [
                'id' => 2,
                'sequenceId' => 12346,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'break_start',
                'timestamp' => $baseDate->copy()->setTime(12, 0, 0)->toISOString(),
                'deviceLocation' => 'Cafeteria',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(12, 0, 0)->toISOString(),
                'variance' => 0,
            ],
            [
                'id' => 3,
                'sequenceId' => 12347,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'break_end',
                'timestamp' => $baseDate->copy()->setTime(12, 30, 0)->toISOString(),
                'deviceLocation' => 'Cafeteria',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(12, 30, 0)->toISOString(),
                'variance' => 0,
            ],
            [
                'id' => 4,
                'sequenceId' => 12348,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'break_start',
                'timestamp' => $baseDate->copy()->setTime(15, 0, 0)->toISOString(),
                'deviceLocation' => 'Cafeteria',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(15, 0, 0)->toISOString(),
                'variance' => 0,
            ],
            [
                'id' => 5,
                'sequenceId' => 12349,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'break_end',
                'timestamp' => $baseDate->copy()->setTime(15, 15, 0)->toISOString(),
                'deviceLocation' => 'Cafeteria',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(15, 15, 0)->toISOString(),
                'variance' => 0,
            ],
            [
                'id' => 6,
                'sequenceId' => 12350,
                'employeeId' => "EMP-2024-{$employeeId}",
                'employeeName' => $this->getEmployeeInfo($employeeId)['name'],
                'eventType' => 'time_out',
                'timestamp' => $baseDate->copy()->setTime(16, 45, 0)->toISOString(),
                'deviceLocation' => 'Gate 2 - Loading Dock',
                'verified' => true,
                'scheduledTime' => $baseDate->copy()->setTime(17, 0, 0)->toISOString(),
                'variance' => -15, // 15 minutes early (early departure)
                'violationType' => 'early_departure',
            ],
        ];
    }

    /**
     * Get employee schedule for the date.
     * 
     * @param int $employeeId
     * @param string $date
     * @return array
     */
    private function getEmployeeSchedule(int $employeeId, string $date): array
    {
        $baseDate = \Carbon\Carbon::parse($date);
        
        return [
            'id' => 1,
            'name' => 'Regular Day Shift',
            'type' => 'fixed',
            'timeIn' => $baseDate->copy()->setTime(8, 0, 0)->toISOString(),
            'timeOut' => $baseDate->copy()->setTime(17, 0, 0)->toISOString(),
            'breakStart' => $baseDate->copy()->setTime(12, 0, 0)->toISOString(),
            'breakEnd' => $baseDate->copy()->setTime(12, 30, 0)->toISOString(),
            'afternoonBreakStart' => $baseDate->copy()->setTime(15, 0, 0)->toISOString(),
            'afternoonBreakEnd' => $baseDate->copy()->setTime(15, 15, 0)->toISOString(),
            'totalHours' => 8.0,
        ];
    }

    /**
     * Generate summary statistics.
     * 
     * @param array $events
     * @param array $schedule
     * @return array
     */
    private function generateSummary(array $events, array $schedule): array
    {
        $timeIn = collect($events)->firstWhere('eventType', 'time_in');
        $timeOut = collect($events)->firstWhere('eventType', 'time_out');
        
        $violations = collect($events)->filter(function ($event) {
            return isset($event['violationType']);
        })->values()->toArray();
        
        $hoursWorked = 8.0; // Mock calculation
        $expectedHours = $schedule['totalHours'] ?? 8.0;
        
        return [
            'status' => count($violations) > 0 ? 'with_violations' : 'compliant',
            'hoursWorked' => $hoursWorked,
            'expectedHours' => $expectedHours,
            'violations' => $violations,
            'violationCount' => count($violations),
            'timeInVariance' => $timeIn['variance'] ?? 0,
            'timeOutVariance' => $timeOut['variance'] ?? 0,
        ];
    }
}
