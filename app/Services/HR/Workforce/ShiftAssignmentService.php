<?php

namespace App\Services\HR\Workforce;

use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShiftAssignmentService
{
    /**
     * Create a single shift assignment
     */
    public function createAssignment(array $data, User $createdBy): ShiftAssignment
    {
        $data['created_by'] = $createdBy->id;
        $data['status'] = $data['status'] ?? 'scheduled'; 

        // Detect conflicts
        $conflicts = $this->detectConflicts(
            Employee::find($data['employee_id']),
            Carbon::parse($data['date']),
            $data['shift_start'],
            $data['shift_end']
        );

        if (!empty($conflicts)) {
            $data['has_conflict'] = true;
            $data['conflict_reason'] = 'Shift overlaps with existing assignments';
        }

        return ShiftAssignment::create($data);
    }

    /**
     * Update a shift assignment
     */
    public function updateAssignment(ShiftAssignment $assignment, array $data): ShiftAssignment
    {
        // Re-check conflicts if times changed
        if (isset($data['shift_start']) || isset($data['shift_end']) || isset($data['date'])) {
            $date = Carbon::parse($data['date'] ?? $assignment->date);
            $start = $data['shift_start'] ?? $assignment->shift_start;
            $end = $data['shift_end'] ?? $assignment->shift_end;

            $conflicts = $this->detectConflicts(
                $assignment->employee,
                $date,
                $start,
                $end,
                $assignment->id
            );

            $data['has_conflict'] = !empty($conflicts);
            $data['conflict_reason'] = $data['has_conflict'] ? 'Shift overlaps with existing assignments' : null;
        }

        $assignment->update($data);
        return $assignment->fresh();
    }

    /**
     * Delete a shift assignment
     */
    public function deleteAssignment(ShiftAssignment $assignment): bool
    {
        return (bool) $assignment->delete();
    }

    /**
     * Bulk create shift assignments
     */
    public function bulkCreateAssignments(array $assignmentsData, User $createdBy): Collection
    {
        $createdById = $createdBy->id;
        $created = [];
        $updated = [];

        foreach ($assignmentsData as $data) {
            $data['created_by'] = $createdById;
            $data['status'] = $data['status'] ?? 'scheduled';

            // Check for conflicts
            $conflicts = $this->detectConflicts(
                Employee::find($data['employee_id']),
                Carbon::parse($data['date']),
                $data['shift_start'],
                $data['shift_end']
            );

            $data['has_conflict'] = !empty($conflicts);
            $data['conflict_reason'] = $data['has_conflict'] ? 'Shift overlaps with existing assignments' : null;

            // Check if assignment already exists for this employee on this date
            $existingAssignment = ShiftAssignment::where('employee_id', $data['employee_id'])
                ->where('date', $data['date'])
                ->where('shift_start', $data['shift_start'])
                ->where('shift_end', $data['shift_end'])
                ->whereNull('deleted_at')
                ->first();

            if ($existingAssignment) {
                // Update existing assignment instead of creating duplicate
                $updated[] = [
                    'id' => $existingAssignment->id,
                    'data' => $data
                ];
            } else {
                $created[] = $data;
            }
        }

        // Insert new assignments
        if (!empty($created)) {
            ShiftAssignment::insert($created);
        }

        // Update existing assignments
        foreach ($updated as $item) {
            ShiftAssignment::where('id', $item['id'])->update($item['data']);
        }

        // Return the assignments created/updated within the last minute
        return ShiftAssignment::where('created_by', $createdById)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->orWhere(function ($q) use ($createdById) {
                $q->where('updated_at', '>=', now()->subMinutes(1))
                  ->where('created_by', $createdById);
            })
            ->get();
    }

    /**
     * Bulk update shift assignments
     */
    public function bulkUpdateAssignments(array $assignmentIds, array $updateData, User $user): int
    {
        return ShiftAssignment::whereIn('id', $assignmentIds)->update($updateData);
    }

    /**
     * Bulk delete shift assignments
     */
    public function bulkDeleteAssignments(array $assignmentIds): int
    {
        return ShiftAssignment::whereIn('id', $assignmentIds)->delete();
    }

    /**
     * Detect conflicts for a potential assignment
     */
    public function detectConflicts(
        Employee $employee,
        Carbon $date,
        string $shiftStart,
        string $shiftEnd,
        ?int $excludeAssignmentId = null
    ): array {
        $query = ShiftAssignment::where('employee_id', $employee->id)
            ->whereDate('date', $date);

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        $existingAssignments = $query->get();
        $conflicts = [];

        foreach ($existingAssignments as $existing) {
            if ($this->shiftsOverlap($shiftStart, $shiftEnd, $existing->shift_start, $existing->shift_end)) {
                $conflicts[] = [
                    'id' => $existing->id,
                    'date' => $existing->date,
                    'shift_start' => $existing->shift_start,
                    'shift_end' => $existing->shift_end,
                    'shift_type' => $existing->shift_type,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Check if two shifts overlap
     */
    private function shiftsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = Carbon::createFromFormat('H:i:s', $start1);
        $e1 = Carbon::createFromFormat('H:i:s', $end1);
        $s2 = Carbon::createFromFormat('H:i:s', $start2);
        $e2 = Carbon::createFromFormat('H:i:s', $end2);

        // Handle night shifts
        if ($e1 < $s1) {
            $e1->addDay();
        }
        if ($e2 < $s2) {
            $e2->addDay();
        }

        return ($s1 < $e2) && ($e1 > $s2);
    }

    /**
     * Resolve a conflicting assignment
     */
    public function resolveConflict(ShiftAssignment $assignment): ShiftAssignment
    {
        $assignment->update([
            'has_conflict' => false,
            'conflict_reason' => null,
        ]);

        return $assignment->fresh();
    }

    /**
     * Get conflicting assignments for an employee on a date
     */
    public function getConflictingAssignments(Employee $employee, Carbon $date): Collection
    {
        return ShiftAssignment::where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('has_conflict', true)
            ->get();
    }

    /**
     * Calculate overtime hours for an assignment
     */
    public function calculateOvertimeHours(ShiftAssignment $assignment): float
    {
        $schedule = $assignment->schedule;

        if (!$schedule) {
            return 0;
        }

        $threshold = $schedule->overtime_threshold ?? 8;
        $duration = $assignment->shift_duration;

        return max(0, $duration - $threshold);
    }

    /**
     * Mark an assignment as overtime
     */
    public function markAsOvertime(ShiftAssignment $assignment, ?float $overtimeHours = null): ShiftAssignment
    {
        $overtimeHours = $overtimeHours ?? $this->calculateOvertimeHours($assignment);

        $assignment->update([
            'is_overtime' => true,
            'overtime_hours' => $overtimeHours,
        ]);

        return $assignment->fresh();
    }

    /**
     * Get overtime assignments for a date range
     */
    public function getOvertimeAssignments(
        Carbon $fromDate,
        Carbon $toDate,
        ?int $employeeId = null
    ): Collection {
        $query = ShiftAssignment::whereBetween('date', [$fromDate, $toDate])
            ->where('is_overtime', true);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->with(['employee', 'schedule', 'department'])->get();
    }

    /**
     * Get coverage report for a date range
     */
    public function getCoverageReport(
        Carbon $fromDate,
        Carbon $toDate,
        ?int $departmentId = null
    ): array {
        $query = ShiftAssignment::whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'scheduled');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $assignments = $query->get();
        $totalDays = $fromDate->diffInDays($toDate) + 1;
        $assignmentCount = $assignments->count();
        $overtimeCount = $assignments->where('is_overtime', true)->count();
        $conflictCount = $assignments->where('has_conflict', true)->count();

        return [
            'total_days' => $totalDays,
            'total_assignments' => $assignmentCount,
            'average_daily' => round($assignmentCount / $totalDays, 2),
            'overtime_assignments' => $overtimeCount,
            'conflicted_assignments' => $conflictCount,
            'coverage_percentage' => round(($assignmentCount / $totalDays) * 10, 2), // Assuming 10 staff per day
            'by_shift_type' => $this->getAssignmentsByShiftType($assignments),
        ];
    }

    /**
     * Identify understaffed days
     */
    public function getUnderstaffedDays(
        Carbon $fromDate,
        Carbon $toDate,
        int $requiredStaff,
        ?int $departmentId = null
    ): array {
        $understaffed = [];

        $currentDate = $fromDate->copy();
        while ($currentDate <= $toDate) {
            $query = ShiftAssignment::whereDate('date', $currentDate)
                ->where('status', 'scheduled');

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            $count = $query->count();

            if ($count < $requiredStaff) {
                $understaffed[] = [
                    'date' => $currentDate->toDateString(),
                    'required' => $requiredStaff,
                    'assigned' => $count,
                    'shortage' => $requiredStaff - $count,
                ];
            }

            $currentDate->addDay();
        }

        return $understaffed;
    }

    /**
     * Get staffing levels for a specific date
     */
    public function getStaffingLevels(Carbon $date, ?int $departmentId = null): array
    {
        $query = ShiftAssignment::whereDate('date', $date)
            ->where('status', 'scheduled');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $assignments = $query->with(['department', 'schedule'])->get();
        $byDepartment = $assignments->groupBy('department_id');

        return [
            'date' => $date->toDateString(),
            'total_assigned' => $assignments->count(),
            'by_department' => $byDepartment->map(function ($group) {
                return [
                    'department_id' => $group->first()->department_id,
                    'department_name' => $group->first()->department?->name,
                    'staff_count' => $group->count(),
                    'by_shift_type' => $group->groupBy('shift_type')->map->count(),
                ];
            })->values()->all(),
            'by_shift_type' => $this->getAssignmentsByShiftType($assignments),
        ];
    }

    /**
     * Helper: Group assignments by shift type
     */
    private function getAssignmentsByShiftType(Collection $assignments): array
    {
        $shiftTypes = ['morning', 'afternoon', 'night', 'split', 'custom'];
        $result = [];

        foreach ($shiftTypes as $type) {
            $count = $assignments->where('shift_type', $type)->count();
            if ($count > 0) {
                $result[$type] = $count;
            }
        }

        return $result;
    }

    /**
     * Get shift assignments with optional filters
     */
    public function getAssignments(
        ?Carbon $date = null,
        ?int $employeeId = null,
        ?int $departmentId = null
    ): Collection {
        $query = ShiftAssignment::query();

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->with(['employee', 'schedule', 'department', 'createdBy'])->get();
    }

    /**
     * Get shift assignment summary
     */
    public function getAssignmentSummary(?Carbon $fromDate = null, ?Carbon $toDate = null): array
    {
        $query = ShiftAssignment::query();

        if ($fromDate && $toDate) {
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        $assignments = $query->get();

        return [
            'total' => $assignments->count(),
            'scheduled' => $assignments->where('status', 'scheduled')->count(),
            'in_progress' => $assignments->where('status', 'in_progress')->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'cancelled' => $assignments->where('status', 'cancelled')->count(),
            'no_show' => $assignments->where('status', 'no_show')->count(),
            'overtime' => $assignments->where('is_overtime', true)->count(),
            'conflicted' => $assignments->where('has_conflict', true)->count(),
            'total_overtime_hours' => $assignments->sum('overtime_hours'),
        ];
    }

    /**
     * Get employee's assignments for a date range
     */
    public function getEmployeeAssignments(Employee $employee, Carbon $fromDate, Carbon $toDate): Collection
    {
        return ShiftAssignment::where('employee_id', $employee->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->with(['schedule', 'department', 'createdBy'])
            ->orderBy('date')
            ->get();
    }

    /**
     * Get today's assignments
     */
    public function getTodayAssignments(?int $departmentId = null): Collection
    {
        $query = ShiftAssignment::whereDate('date', today())
            ->where('status', 'scheduled');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->with(['employee', 'schedule', 'department'])->get();
    }
}
