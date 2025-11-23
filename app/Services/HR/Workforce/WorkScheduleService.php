<?php

namespace App\Services\HR\Workforce;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkScheduleService
{
    /**
     * Create a new work schedule
     */
    public function createSchedule(array $data, User $createdBy): WorkSchedule
    {
        $data['created_by'] = $createdBy->id;
        $data['status'] = $data['status'] ?? 'draft';

        return WorkSchedule::create($data);
    }

    /**
     * Update an existing work schedule
     */
    public function updateSchedule(WorkSchedule $schedule, array $data): WorkSchedule
    {
        $schedule->update($data);
        return $schedule->fresh();
    }

    /**
     * Delete a work schedule (with validation)
     */
    public function deleteSchedule(WorkSchedule $schedule): bool
    {
        // Prevent deletion if active assignments exist
        if ($schedule->hasActiveAssignments()) {
            throw new \Exception('Cannot delete schedule with active employee assignments');
        }

        return $schedule->delete();
    }

    /**
     * Duplicate a work schedule
     */
    public function duplicateSchedule(WorkSchedule $schedule, string $newName, ?User $createdBy = null): WorkSchedule
    {
        $data = $schedule->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $data['name'] = $newName;
        $data['created_by'] = $createdBy?->id ?? auth()->id();

        return WorkSchedule::create($data);
    }

    /**
     * Activate a work schedule
     */
    public function activateSchedule(WorkSchedule $schedule): WorkSchedule
    {
        return $this->updateSchedule($schedule, ['status' => 'active']);
    }

    /**
     * Expire a work schedule
     */
    public function expireSchedule(WorkSchedule $schedule): WorkSchedule
    {
        return $this->updateSchedule($schedule, ['status' => 'expired']);
    }

    /**
     * Create a template from a schedule
     */
    public function createTemplate(WorkSchedule $schedule): WorkSchedule
    {
        return $this->duplicateSchedule($schedule, $schedule->name . ' (Template)');
    }

    /**
     * Assign schedule to a single employee
     */
    public function assignToEmployee(
        WorkSchedule $schedule,
        Employee $employee,
        Carbon $effectiveDate,
        ?Carbon $endDate = null,
        ?User $createdBy = null
    ): EmployeeSchedule {
        return EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $effectiveDate,
            'end_date' => $endDate,
            'is_active' => true,
            'created_by' => $createdBy?->id ?? auth()->id(),
        ]);
    }

    /**
     * Assign schedule to multiple employees
     */
    public function assignToMultipleEmployees(
        WorkSchedule $schedule,
        array $employeeIds,
        Carbon $effectiveDate,
        ?Carbon $endDate = null,
        ?User $createdBy = null
    ): Collection {
        $createdBy = $createdBy?->id ?? auth()->id();
        $assignments = [];

        foreach ($employeeIds as $employeeId) {
            $assignments[] = [
                'employee_id' => $employeeId,
                'work_schedule_id' => $schedule->id,
                'effective_date' => $effectiveDate,
                'end_date' => $endDate,
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        EmployeeSchedule::insert($assignments);

        return EmployeeSchedule::whereIn('employee_id', $employeeIds)
            ->where('work_schedule_id', $schedule->id)
            ->where('effective_date', $effectiveDate)
            ->get();
    }

    /**
     * Unassign schedule from an employee
     */
    public function unassignFromEmployee(WorkSchedule $schedule, Employee $employee): bool
    {
        return (bool) EmployeeSchedule::where('employee_id', $employee->id)
            ->where('work_schedule_id', $schedule->id)
            ->delete();
    }

    /**
     * Get schedules with optional filters
     */
    public function getSchedules(?string $status = null, ?int $departmentId = null): Collection
    {
        $query = WorkSchedule::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->with(['department', 'createdBy'])->get();
    }

    /**
     * Get active schedules
     */
    public function getActiveSchedules(): Collection
    {
        return WorkSchedule::active()->with(['department', 'createdBy'])->get();
    }

    /**
     * Get template schedules
     */
    public function getTemplates(): Collection
    {
        return WorkSchedule::templates()->with(['department', 'createdBy'])->get();
    }

    /**
     * Get schedule summary statistics
     */
    public function getScheduleSummary(): array
    {
        return [
            'total' => WorkSchedule::count(),
            'active' => WorkSchedule::where('status', 'active')->count(),
            'draft' => WorkSchedule::where('status', 'draft')->count(),
            'expired' => WorkSchedule::where('status', 'expired')->count(),
            'templates' => WorkSchedule::where('is_template', true)->count(),
            'avg_weekly_hours' => $this->calculateAverageWeeklyHours(),
        ];
    }

    /**
     * Get employee's current schedule for a given date
     */
    public function getEmployeeSchedule(Employee $employee, Carbon $date): ?EmployeeSchedule
    {
        return EmployeeSchedule::where('employee_id', $employee->id)
            ->where('effective_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * Auto-activate schedules within validity period
     */
    public function autoActivateSchedules(): int
    {
        return WorkSchedule::where('status', 'draft')
            ->where('effective_date', '<=', today())
            ->update(['status' => 'active']);
    }

    /**
     * Auto-expire schedules past expiration date
     */
    public function autoExpireSchedules(): int
    {
        return WorkSchedule::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', today())
            ->update(['status' => 'expired']);
    }

    /**
     * Calculate average weekly hours across all schedules
     */
    private function calculateAverageWeeklyHours(): float
    {
        $schedules = WorkSchedule::all();

        if ($schedules->isEmpty()) {
            return 0;
        }

        $totalHours = $schedules->sum(function ($schedule) {
            return $this->calculateScheduleWeeklyHours($schedule);
        });

        return $totalHours / $schedules->count();
    }

    /**
     * Calculate total weekly hours for a schedule
     */
    private function calculateScheduleWeeklyHours(WorkSchedule $schedule): float
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $totalHours = 0;

        foreach ($days as $day) {
            $startField = $day . '_start';
            $endField = $day . '_end';

            if ($schedule->{$startField} && $schedule->{$endField}) {
                $start = Carbon::createFromFormat('H:i:s', $schedule->{$startField});
                $end = Carbon::createFromFormat('H:i:s', $schedule->{$endField});

                if ($end < $start) {
                    $end->addDay();
                }

                $totalHours += abs($end->diffInMinutes($start)) / 60;
            }
        }

        return $totalHours;
    }

    /**
     * Get detailed schedule analysis for reporting
     */
    public function getScheduleAnalysis(WorkSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'status' => $schedule->status,
            'effective_date' => $schedule->effective_date,
            'expires_at' => $schedule->expires_at,
            'weekly_hours' => $this->calculateScheduleWeeklyHours($schedule),
            'working_days' => $schedule->work_days,
            'employee_count' => $schedule->employeeSchedules()->active()->count(),
            'active_assignments' => $schedule->shiftAssignments()->where('status', 'scheduled')->count(),
        ];
    }
}
