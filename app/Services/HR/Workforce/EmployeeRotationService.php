<?php

namespace App\Services\HR\Workforce;

use App\Models\Employee;
use App\Models\EmployeeRotation;
use App\Models\RotationAssignment;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeRotationService
{
    /**
     * Create a new rotation pattern
     */
    public function createRotation(array $data, User $createdBy): EmployeeRotation
    {
        // Validate pattern structure
        $validation = $this->validatePattern($data['pattern_json']);
        if (!$validation['valid']) {
            throw new \Exception('Invalid pattern: ' . $validation['message']);
        }

        $data['created_by'] = $createdBy->id;
        $data['is_active'] = $data['is_active'] ?? true;

        return EmployeeRotation::create($data);
    }

    /**
     * Update a rotation pattern
     */
    public function updateRotation(EmployeeRotation $rotation, array $data): EmployeeRotation
    {
        // Validate pattern if being updated
        if (isset($data['pattern_json'])) {
            $validation = $this->validatePattern($data['pattern_json']);
            if (!$validation['valid']) {
                throw new \Exception('Invalid pattern: ' . $validation['message']);
            }
        }

        $rotation->update($data);
        return $rotation->fresh();
    }

    /**
     * Delete a rotation pattern
     */
    public function deleteRotation(EmployeeRotation $rotation): bool
    {
        if ($rotation->hasActiveAssignments()) {
            throw new \Exception('Cannot delete rotation with active employee assignments');
        }

        return $rotation->delete();
    }

    /**
     * Duplicate a rotation pattern
     */
    public function duplicateRotation(EmployeeRotation $rotation, string $newName, ?User $createdBy = null): EmployeeRotation
    {
        $data = $rotation->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $data['name'] = $newName;
        $data['created_by'] = $createdBy?->id ?? auth()->id();

        return EmployeeRotation::create($data);
    }

    /**
     * Validate pattern structure
     */
    public function validatePattern(array $patternJson): array
    {
        // Check required fields
        if (!isset($patternJson['work_days']) || !isset($patternJson['rest_days']) || !isset($patternJson['pattern'])) {
            return [
                'valid' => false,
                'message' => 'Pattern must contain work_days, rest_days, and pattern array'
            ];
        }

        $workDays = $patternJson['work_days'];
        $restDays = $patternJson['rest_days'];
        $pattern = $patternJson['pattern'];

        // Validate array length
        if (count($pattern) !== $workDays + $restDays) {
            return [
                'valid' => false,
                'message' => "Pattern length must equal work_days + rest_days ({$workDays} + {$restDays})"
            ];
        }

        // Count 1s and 0s
        $workCount = count(array_filter($pattern, fn($v) => $v === 1));
        $restCount = count(array_filter($pattern, fn($v) => $v === 0));

        if ($workCount !== $workDays) {
            return [
                'valid' => false,
                'message' => "Pattern must contain exactly {$workDays} work days (1), found {$workCount}"
            ];
        }

        if ($restCount !== $restDays) {
            return [
                'valid' => false,
                'message' => "Pattern must contain exactly {$restDays} rest days (0), found {$restCount}"
            ];
        }

        return ['valid' => true, 'message' => 'Pattern is valid'];
    }

    /**
     * Generate pattern from predefined type
     */
    public static function generatePatternFromType(string $patternType): array
    {
        return match ($patternType) {
            '4x2' => [
                'work_days' => 4,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 0, 0],
            ],
            '6x1' => [
                'work_days' => 6,
                'rest_days' => 1,
                'pattern' => [1, 1, 1, 1, 1, 1, 0],
            ],
            '5x2' => [
                'work_days' => 5,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 1, 0, 0],
            ],
            default => throw new \Exception("Unknown pattern type: {$patternType}"),
        };
    }

    /**
     * Calculate cycle length from pattern
     */
    public function calculateCycleLength(array $patternJson): int
    {
        return count($patternJson['pattern'] ?? []);
    }

    /**
     * Check if a date is a work day for a rotation
     */
    public function isWorkDay(EmployeeRotation $rotation, Carbon $date, Carbon $startDate): bool
    {
        $pattern = $rotation->pattern_json;
        $daysSinceStart = $startDate->diffInDays($date);
        $cycleLength = $this->calculateCycleLength($pattern);

        $dayInCycle = $daysSinceStart % $cycleLength;

        return $pattern['pattern'][$dayInCycle] === 1;
    }

    /**
     * Assign rotation to a single employee
     */
    public function assignToEmployee(
        EmployeeRotation $rotation,
        Employee $employee,
        Carbon $startDate,
        ?Carbon $endDate = null,
        ?User $createdBy = null
    ): RotationAssignment {
        return RotationAssignment::create([
            'employee_id' => $employee->id,
            'rotation_id' => $rotation->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => true,
            'created_by' => $createdBy?->id ?? auth()->id(),
        ]);
    }

    /**
     * Assign rotation to multiple employees
     */
    public function assignToMultipleEmployees(
        EmployeeRotation $rotation,
        array $employeeIds,
        Carbon $startDate,
        ?Carbon $endDate = null,
        ?User $createdBy = null
    ): Collection {
        $createdBy = $createdBy?->id ?? auth()->id();
        $assignments = [];

        foreach ($employeeIds as $employeeId) {
            $assignments[] = [
                'employee_id' => $employeeId,
                'rotation_id' => $rotation->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RotationAssignment::insert($assignments);

        return RotationAssignment::whereIn('employee_id', $employeeIds)
            ->where('rotation_id', $rotation->id)
            ->where('start_date', $startDate)
            ->get();
    }

    /**
     * Unassign rotation from an employee
     */
    public function unassignFromEmployee(EmployeeRotation $rotation, Employee $employee): bool
    {
        return (bool) RotationAssignment::where('employee_id', $employee->id)
            ->where('rotation_id', $rotation->id)
            ->delete();
    }

    /**
     * Generate shift assignments for a rotation assignment
     * 
     * PHASE 1 FIX: Rotation pattern takes precedence over schedule day-of-week
     * Logic flow:
     *   1. Loop through date range
     *   2. Check if rotation says it's a work day (FIRST - rotation pattern precedence)
     *   3. Then check if schedule has a shift on that day-of-week (SECOND - schedule applies)
     *   4. Create shift only if BOTH conditions pass
     * 
     * This prevents shifts being created on rotation rest days, even if the schedule
     * would normally have a shift on that day-of-week.
     */
    public function generateShiftAssignments(
        RotationAssignment $assignment,
        Carbon $fromDate,
        Carbon $toDate,
        WorkSchedule $schedule,
        ?User $createdBy = null
    ): Collection {
        // Validate parameters
        if (!$assignment || !$assignment->rotation) {
            throw new \Exception('Invalid rotation assignment');
        }

        if (!$schedule) {
            throw new \Exception('Work schedule is required');
        }

        if ($fromDate > $toDate) {
            throw new \Exception('From date must be before or equal to to date');
        }

        $createdBy = $createdBy?->id ?? auth()->id();
        $assignments = [];
        $rotation = $assignment->rotation;
        $currentDate = $fromDate->copy();

        while ($currentDate <= $toDate) {
            // STEP 1: Check rotation pattern FIRST (rotation pattern takes precedence)
            // Skip if rotation says this is a rest day
            if (!$this->isWorkDay($rotation, $currentDate, Carbon::parse($assignment->start_date))) {
                $currentDate->addDay();
                continue; // Skip rest days - no shift created
            }

            // STEP 2: Then check schedule day-of-week (schedule applies to work days)
            // Get day name and check if schedule has shift times for this day
            $day = strtolower($currentDate->format('l'));
            $startField = $day . '_start';
            $endField = $day . '_end';

            // Only create shift if schedule has times defined for this day of week
            if ($schedule->{$startField} && $schedule->{$endField}) {
                $assignments[] = [
                    'employee_id' => $assignment->employee_id,
                    'schedule_id' => $schedule->id,
                    'rotation_assignment_id' => $assignment->id, // NEW: Track rotation source
                    'date' => $currentDate->toDateString(),
                    'shift_start' => $schedule->{$startField},
                    'shift_end' => $schedule->{$endField},
                    'shift_type' => 'regular',
                    'assignment_source' => 'rotation', // NEW: Source tracking
                    'source_details' => json_encode([ // NEW: Additional context
                        'rotation_id' => $rotation->id,
                        'rotation_name' => $rotation->name,
                        'schedule_name' => $schedule->name,
                    ]),
                    'department_id' => $schedule->department_id,
                    'status' => 'scheduled',
                    'has_conflict' => false,
                    'is_overtime' => false,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // If schedule doesn't have shift on that day, skip (don't create shift)

            $currentDate->addDay();
        }

        // Bulk insert all shift assignments
        if (!empty($assignments)) {
            ShiftAssignment::insert($assignments);
        }

        // Return the created shifts for this employee and date range
        return ShiftAssignment::where('employee_id', $assignment->employee_id)
            ->where('date', '>=', $fromDate->toDateString())
            ->where('date', '<=', $toDate->toDateString())
            ->get();
    }

    /**
     * Get rotations with optional filters
     */
    public function getRotations(?string $patternType = null, ?int $departmentId = null): Collection
    {
        $query = EmployeeRotation::query();

        if ($patternType) {
            $query->where('pattern_type', $patternType);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->with(['department', 'createdBy', 'rotationAssignments'])->get();
    }

    /**
     * Get active rotations
     */
    public function getActiveRotations(): Collection
    {
        return EmployeeRotation::active()->with(['department', 'createdBy'])->get();
    }

    /**
     * Get rotation summary statistics
     */
    public function getRotationSummary(): array
    {
        return [
            'total' => EmployeeRotation::count(),
            'active' => EmployeeRotation::where('is_active', true)->count(),
            'by_type' => [
                '4x2' => EmployeeRotation::where('pattern_type', '4x2')->count(),
                '6x1' => EmployeeRotation::where('pattern_type', '6x1')->count(),
                '5x2' => EmployeeRotation::where('pattern_type', '5x2')->count(),
                'custom' => EmployeeRotation::where('pattern_type', 'custom')->count(),
            ],
            'total_employees' => RotationAssignment::where('is_active', true)->distinct('employee_id')->count(),
        ];
    }

    /**
     * Get employee's current rotation for a given date
     */
    public function getEmployeeRotation(Employee $employee, Carbon $date): ?RotationAssignment
    {
        return RotationAssignment::where('employee_id', $employee->id)
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Get detailed rotation analysis for reporting
     */
    public function getRotationAnalysis(EmployeeRotation $rotation): array
    {
        $pattern = $rotation->pattern_json;

        return [
            'id' => $rotation->id,
            'name' => $rotation->name,
            'pattern_type' => $rotation->pattern_type,
            'work_days' => $pattern['work_days'],
            'rest_days' => $pattern['rest_days'],
            'cycle_length' => $this->calculateCycleLength($pattern),
            'employee_count' => $rotation->rotationAssignments()->where('is_active', true)->count(),
            'active_assignments' => $rotation->rotationAssignments()->where('is_active', true)->count(),
        ];
    }

    /**
     * Detect schedule-rotation conflicts for a given assignment.
     *
     * Phase 3: Conflict Detection
     * This method identifies incompatibilities between a rotation pattern and a work schedule.
     *
     * Severity rules:
     *   - "warning": Rotation expects work day but schedule has no shift
     *     (Low priority: employee simply won't work that day)
     *   - "error": Rotation expects rest day but schedule expects work
     *     (High priority: double-booking, must be resolved)
     *
     * @param RotationAssignment $assignment The employee's rotation assignment
     * @param WorkSchedule $schedule The proposed work schedule
     * @param Carbon $fromDate Start date for conflict detection
     * @param Carbon $toDate End date for conflict detection (inclusive)
     * @return array Array of conflicts, each with date, type, severity, and details
     *
     * @example
     *   $conflicts = $service->detectScheduleRotationConflicts($assignment, $schedule, $from, $to);
     *   // Returns:
     *   // [
     *   //     [
     *   //         'date' => '2025-12-01',
     *   //         'type' => 'rotation_work_no_schedule',
     *   //         'severity' => 'warning',
     *   //         'message' => 'Rotation expects work day but schedule has no shift',
     *   //         'rotation_says' => 'work',
     *   //         'schedule_says' => 'rest',
     *   //     ],
     *   //     [
     *   //         'date' => '2025-12-05',
     *   //         'type' => 'rotation_rest_schedule_work',
     *   //         'severity' => 'error',
     *   //         'message' => 'Rotation expects rest day but schedule expects work (conflict)',
     *   //         'rotation_says' => 'rest',
     *   //         'schedule_says' => 'work',
     *   //     ],
     *   // ]
     */
    public function detectScheduleRotationConflicts(
        RotationAssignment $assignment,
        WorkSchedule $schedule,
        Carbon $fromDate,
        Carbon $toDate
    ): array {
        $conflicts = [];
        $rotation = $assignment->rotation;
        $currentDate = $fromDate->clone();

        while ($currentDate->lte($toDate)) {
            // Get the day of week (lowercase for schedule field names)
            $dayOfWeek = strtolower($currentDate->format('l'));

            // Check if rotation considers this a work day
            $isRotationWorkDay = $this->isWorkDay($rotation, $currentDate, $assignment->start_date);

            // Check if schedule has shift times for this day of week
            $scheduleStartField = $dayOfWeek . '_start';
            $scheduleEndField = $dayOfWeek . '_end';
            $isScheduleWorkDay = !empty($schedule->{$scheduleStartField}) && !empty($schedule->{$scheduleEndField});

            // Detect conflicts
            if ($isRotationWorkDay && !$isScheduleWorkDay) {
                // Rotation says work, but schedule says rest
                $conflicts[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'type' => 'rotation_work_no_schedule',
                    'severity' => 'warning',
                    'message' => 'Rotation expects work day but schedule has no shift',
                    'rotation_says' => 'work',
                    'schedule_says' => 'rest',
                ];
            } elseif (!$isRotationWorkDay && $isScheduleWorkDay) {
                // Rotation says rest, but schedule says work
                $conflicts[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'type' => 'rotation_rest_schedule_work',
                    'severity' => 'error',
                    'message' => 'Rotation expects rest day but schedule expects work (conflict)',
                    'rotation_says' => 'rest',
                    'schedule_says' => 'work',
                ];
            }

            $currentDate->addDay();
        }

        return $conflicts;
    }
}
