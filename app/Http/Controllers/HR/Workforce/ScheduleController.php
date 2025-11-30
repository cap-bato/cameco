<?php

namespace App\Http\Controllers\HR\Workforce;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Workforce\StoreWorkScheduleRequest;
use App\Http\Requests\HR\Workforce\UpdateWorkScheduleRequest;
use App\Models\WorkSchedule;
use App\Services\HR\Workforce\WorkScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    protected WorkScheduleService $workScheduleService;

    public function __construct(WorkScheduleService $workScheduleService)
    {
        $this->workScheduleService = $workScheduleService;
    }

    /**
     * Display a listing of work schedules.
     */
    public function index(Request $request): Response
    {
        $schedules = $this->workScheduleService->getSchedules();
        $summary = [
            'total_schedules' => $schedules->count(),
            'active_schedules' => $schedules->where('status', 'active')->count(),
            'expired_schedules' => $schedules->where('status', 'expired')->count(),
            'draft_schedules' => $schedules->where('status', 'draft')->count(),
            'employees_assigned' => 0,
            'templates_available' => $schedules->where('is_template', true)->count(),
        ];

        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();

        $filters = [
            'search' => $request->input('search', ''),
            'department_id' => $request->input('department_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'is_template' => $request->input('is_template'),
        ];

        return Inertia::render('HR/Workforce/Schedules/Index', [
            'schedules' => $schedules,
            'summary' => $summary,
            'departments' => $departments,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new schedule.
     */
    public function create(): Response
    {
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $employees = \App\Models\Employee::all(['id', 'employee_number', 'first_name', 'last_name', 'department_id'])->toArray();

        return Inertia::render('HR/Workforce/Schedules/Create', [
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    /**
     * Store a newly created schedule in storage.
     */
    public function store(StoreWorkScheduleRequest $request)
    {
        $this->workScheduleService->createSchedule(
            $request->validated(),
            auth()->user()
        );

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule created successfully.');
    }

    /**
     * Display the specified schedule.
     */
    public function show(string $id): Response
    {
        $schedule = WorkSchedule::with(['department', 'createdBy', 'employeeSchedules'])->findOrFail($id);

        return Inertia::render('HR/Workforce/Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show the form for editing the specified schedule.
     */
    public function edit(string $id): Response
    {
        $schedule = WorkSchedule::findOrFail($id);
        $departments = \App\Models\Department::all(['id', 'name', 'code'])->toArray();
        $employees = \App\Models\Employee::all(['id', 'employee_number', 'first_name', 'last_name', 'department_id'])->toArray();

        return Inertia::render('HR/Workforce/Schedules/Edit', [
            'schedule' => $schedule,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    /**
     * Update the specified schedule in storage.
     */
    public function update(UpdateWorkScheduleRequest $request, string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $this->workScheduleService->updateSchedule($schedule, $request->validated());

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove the specified schedule from storage.
     */
    public function destroy(string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $this->workScheduleService->deleteSchedule($schedule);

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule deleted successfully.');
    }

    /**
     * Assign employees to a schedule.
     */
    public function assignEmployees(Request $request, string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $employeeIds = $request->input('employee_ids', []);
        $effectiveDate = $request->input('effective_date', now());

        $this->workScheduleService->assignToMultipleEmployees($schedule, $employeeIds, $effectiveDate);

        return redirect()->route('hr.workforce.schedules.show', $id)
            ->with('success', count($employeeIds) . ' employee(s) assigned to schedule successfully.');
    }

    /**
     * Remove employees from a schedule.
     */
    public function unassignEmployees(Request $request, string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $employeeIds = $request->input('employee_ids', []);

        foreach ($employeeIds as $employeeId) {
            $employee = \App\Models\Employee::find($employeeId);
            if ($employee) {
                $this->workScheduleService->unassignFromEmployee($schedule, $employee);
            }
        }

        return redirect()->route('hr.workforce.schedules.show', $id)
            ->with('success', count($employeeIds) . ' employee(s) removed from schedule successfully.');
    }

    /**
     * Duplicate an existing schedule.
     */
    public function duplicate(string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $newName = request('name', $schedule->name . ' (Copy)');
        $newSchedule = $this->workScheduleService->duplicateSchedule($schedule, $newName);

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', 'Schedule duplicated successfully.');
    }

    /**
     * Activate a schedule.
     */
    public function activate(string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $this->workScheduleService->activateSchedule($schedule);

        return back()->with('success', 'Schedule activated successfully.');
    }

    /**
     * Expire a schedule.
     */
    public function expire(string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $this->workScheduleService->expireSchedule($schedule);

        return back()->with('success', 'Schedule expired successfully.');
    }

    /**
     * Bulk update schedule status.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $scheduleIds = $request->input('schedule_ids', []);
        $status = $request->input('status');

        foreach ($scheduleIds as $scheduleId) {
            $schedule = WorkSchedule::find($scheduleId);
            if ($schedule) {
                $schedule->update(['status' => $status]);
            }
        }

        return redirect()->route('hr.workforce.schedules.index')
            ->with('success', count($scheduleIds) . " schedule(s) status updated to '{$status}' successfully.");
    }

    /**
     * Export schedules to CSV.
     */
    public function exportCsv(Request $request)
    {
        $schedules = $this->workScheduleService->getSchedules();
        
        $filename = 'schedules_' . date('Y-m-d_H-i-s') . '.csv';
        $path = storage_path("app/exports/{$filename}");

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        fputcsv($file, ['ID', 'Name', 'Description', 'Status', 'Effective Date', 'Expires At', 'Department']);

        foreach ($schedules as $schedule) {
            fputcsv($file, [
                $schedule->id,
                $schedule->name,
                $schedule->description,
                $schedule->status,
                $schedule->effective_date,
                $schedule->expires_at,
                $schedule->department?->name,
            ]);
        }

        fclose($file);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    /**
     * Get schedule statistics and analytics.
     */
    public function getStatistics()
    {
        $schedules = $this->workScheduleService->getSchedules();

        $statistics = [
            'total_schedules' => $schedules->count(),
            'active_schedules' => $schedules->where('status', 'active')->count(),
            'expired_schedules' => $schedules->where('status', 'expired')->count(),
            'draft_schedules' => $schedules->where('status', 'draft')->count(),
            'templates' => $schedules->where('is_template', true)->count(),
        ];

        return response()->json($statistics);
    }

    /**
     * Get available employees for assignment to a schedule.
     */
    public function getAvailableEmployees(Request $request)
    {
        $departmentId = $request->input('department_id');
        $query = \App\Models\Employee::all(['id', 'employee_number', 'first_name', 'last_name', 'department_id']);

        if ($departmentId) {
            $query = $query->where('department_id', $departmentId);
        }

        return response()->json($query->values());
    }

    /**
     * Get employees assigned to a specific schedule.
     */
    public function getAssignedEmployees(string $id)
    {
        $schedule = WorkSchedule::findOrFail($id);
        $assignedEmployees = $schedule->employeeSchedules()
            ->with(['employee', 'workSchedule'])
            ->get();

        return response()->json($assignedEmployees);
    }

    /**
     * Lightweight JSON list of schedules for modal dropdowns.
     */
    public function list(Request $request): JsonResponse
    {
        $query = WorkSchedule::query()
            ->select(['id', 'name', 'status', 'department_id', 'is_template'])
            ->orderBy('name');

        if ($request->boolean('active_only', true)) {
            $query->where('status', 'active');
        }

        if (!$request->boolean('include_templates', false)) {
            $query->where('is_template', false);
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        return response()->json($query->get());
    }
}
