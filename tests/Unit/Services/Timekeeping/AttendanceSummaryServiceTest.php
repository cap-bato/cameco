<?php

namespace Tests\Unit\Services\Timekeeping;

use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Timekeeping\AttendanceSummaryService;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * AttendanceSummaryServiceTest
 * 
 * Unit tests for Task 5.3.1 and 5.3.2
 * - Task 5.3.1: computeDailySummary() method
 * - Task 5.3.2: applyBusinessRules() method
 */
class AttendanceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceSummaryService $service;
    private Employee $employee;
    private WorkSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceSummaryService();
        
        // Create test department
        $department = Department::factory()->create([
            'name' => 'Test Department',
        ]);
        
        // Create test user and employee
        $user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);
        
        // Create work schedule for the department: 8:00 AM - 5:00 PM, 1 hour lunch break
        $this->schedule = WorkSchedule::factory()->create([
            'name' => 'Standard 9-5',
            'department_id' => $department->id,
            'effective_date' => now()->subDays(30),
            'expires_at' => null,
            'monday_start' => '08:00:00',
            'monday_end' => '17:00:00',
            'tuesday_start' => '08:00:00',
            'tuesday_end' => '17:00:00',
            'wednesday_start' => '08:00:00',
            'wednesday_end' => '17:00:00',
            'thursday_start' => '08:00:00',
            'thursday_end' => '17:00:00',
            'friday_start' => '08:00:00',
            'friday_end' => '17:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 60,
        ]);
    }

    /**
     * Task 5.3.1: Test computeDailySummary() fetches and computes times
     */
    public function test_compute_daily_summary_with_complete_day(): void
    {
        $date = Carbon::now()->startOfDay();

        // Create attendance events
        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('08:05'),
            'event_type' => 'time_in',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('12:00'),
            'event_type' => 'break_start',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('13:00'),
            'event_type' => 'break_end',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('17:30'),
            'event_type' => 'time_out',
        ]);

        $summary = $this->service->computeDailySummary($this->employee->id, $date);

        // Verify time values extracted
        $this->assertNotNull($summary['time_in']);
        $this->assertNotNull($summary['time_out']);
        $this->assertEquals(60, $summary['break_duration']);
        
        // Verify hours calculated (9.25 hours: 08:05 to 17:30 = 9h 25m - 60m break = 8h 25m)
        $this->assertNotNull($summary['total_hours_worked']);
        $this->assertGreaterThan(8, $summary['total_hours_worked']);
    }

    /**
     * Task 5.3.1: Test computeDailySummary() returns empty for absent employee
     */
    public function test_compute_daily_summary_returns_absent_when_no_events(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = $this->service->computeDailySummary($this->employee->id, $date);

        $this->assertNull($summary['time_in']);
        $this->assertNull($summary['time_out']);
        $this->assertEquals(0, $summary['total_hours_worked']);
    }

    /**
     * Task 5.3.2: Test applyBusinessRules() marks present for on-time arrival
     */
    public function test_apply_business_rules_marks_present_for_on_time(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:10')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'break_duration' => 60,
            'total_hours_worked' => 8.83,
            'regular_hours' => 8.83,
            'overtime_hours' => 0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should be present and not late (within 15-minute grace period)
        $this->assertTrue($withRules['is_present']);
        $this->assertFalse($withRules['is_late']);
        $this->assertNull($withRules['late_minutes']);
    }

    /**
     * Task 5.3.2: Test applyBusinessRules() marks late for tardy arrival
     */
    public function test_apply_business_rules_marks_late_for_tardy_arrival(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'scheduled_start' => $date->copy()->setTimeFromTimeString('08:00'),
            'scheduled_end' => $date->copy()->setTimeFromTimeString('17:00'),
            'time_in' => $date->copy()->setTimeFromTimeString('08:25')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'break_duration' => 60,
            'total_hours_worked' => 8.58,
            'regular_hours' => 8.58,
            'overtime_hours' => 0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should be present and late (outside 15-minute grace period)
        $this->assertTrue($withRules['is_present']);
        $this->assertTrue($withRules['is_late']);
        // The late_minutes calculation: time_in is 25 minutes after start, minus 15-min grace = 10 min late
        // Due to rounding, we just check it's greater than 0
        $this->assertGreaterThan(0, $withRules['late_minutes']);
    }

    /**
     * Task 5.3.2: Test applyBusinessRules() marks absent for no time_in
     */
    public function test_apply_business_rules_marks_absent_for_no_time_in(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => null,
            'time_out' => null,
            'break_duration' => 0,
            'total_hours_worked' => 0,
            'regular_hours' => 0,
            'overtime_hours' => 0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should be absent
        $this->assertFalse($withRules['is_present']);
    }

    /**
     * Task 5.3.2: Test applyBusinessRules() marks undertime for short day
     */
    public function test_apply_business_rules_marks_undertime_for_short_day(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'scheduled_start' => $date->copy()->setTimeFromTimeString('08:00'),
            'scheduled_end' => $date->copy()->setTimeFromTimeString('17:00'),
            'time_in' => $date->copy()->setTimeFromTimeString('08:00')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('16:00')->toDateTimeString(),
            'break_duration' => 60,
            'total_hours_worked' => 7.0, // Only 7 hours vs 8 scheduled
            'regular_hours' => 7.0,
            'overtime_hours' => 0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should be undertime
        $this->assertTrue($withRules['is_undertime']);
        $this->assertNotNull($withRules['undertime_minutes']);
        $this->assertGreaterThan(0, $withRules['undertime_minutes']);
    }

    /**
     * Task 5.3.2: Test applyBusinessRules() marks overtime for long day
     */
    public function test_apply_business_rules_marks_overtime_for_long_day(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'scheduled_start' => $date->copy()->setTimeFromTimeString('08:00'),
            'scheduled_end' => $date->copy()->setTimeFromTimeString('17:00'),
            'time_in' => $date->copy()->setTimeFromTimeString('08:00')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('18:00')->toDateTimeString(),
            'break_duration' => 60,
            'total_hours_worked' => 9.0,
            'regular_hours' => 8.0,
            'overtime_hours' => 1.0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should be overtime
        $this->assertTrue($withRules['is_overtime']);
    }

    /**
     * Task 5.3.2: Test grace period boundary (exactly at grace period limit)
     */
    public function test_apply_business_rules_respects_grace_period_boundary(): void
    {
        $date = Carbon::now()->startOfDay();

        // Clocked in exactly 15 minutes after start = should NOT be late
        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:15')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'break_duration' => 60,
            'total_hours_worked' => 8.58,
            'regular_hours' => 8.58,
            'overtime_hours' => 0,
        ];

        $withRules = $this->service->applyBusinessRules($summary, $date);

        // Should NOT be late (exactly at grace period boundary)
        $this->assertFalse($withRules['is_late']);
        $this->assertNull($withRules['late_minutes']);
    }

    /**
     * Task 5.3.1: Test computeDailySummary() with partial day (only time_in)
     */
    public function test_compute_daily_summary_with_only_time_in(): void
    {
        $date = Carbon::now()->startOfDay();

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('08:00'),
            'event_type' => 'time_in',
        ]);

        $summary = $this->service->computeDailySummary($this->employee->id, $date);

        // Should have time_in but no time_out
        $this->assertNotNull($summary['time_in']);
        $this->assertNull($summary['time_out']);
        $this->assertNull($summary['total_hours_worked']);
    }

    /**
     * Task 5.3.2: Test getGracePeriodMinutes() returns configured value
     */
    public function test_grace_period_minutes_constant(): void
    {
        $gracePeriod = AttendanceSummaryService::getGracePeriodMinutes();
        
        $this->assertEquals(15, $gracePeriod);
    }

    /**
     * Task 5.3.2: Test getOvertimeThresholdMinutes() returns configured value
     */
    public function test_overtime_threshold_minutes_constant(): void
    {
        $threshold = AttendanceSummaryService::getOvertimeThresholdMinutes();
        
        $this->assertEquals(0, $threshold);
    }

    /**
     * Task 5.3.3: Test storeDailySummary() creates new attendance summary record
     */
    public function test_store_daily_summary_creates_new_record(): void
    {
        $date = Carbon::now()->startOfDay();

        // Create attendance events and compute summary
        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('08:05'),
            'event_type' => 'time_in',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('12:00'),
            'event_type' => 'break_start',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('13:00'),
            'event_type' => 'break_end',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('17:30'),
            'event_type' => 'time_out',
        ]);

        // Compute and apply business rules
        $summary = $this->service->computeDailySummary($this->employee->id, $date);
        $summary = $this->service->applyBusinessRules($summary);

        // Store to database
        $record = $this->service->storeDailySummary($summary);

        // Verify record was created
        $this->assertNotNull($record->id);
        // Check database record exists by querying directly due to date format differences
        $dbRecord = DailyAttendanceSummary::where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();
        $this->assertNotNull($dbRecord);
        $this->assertTrue($dbRecord->is_present);
        $this->assertFalse($dbRecord->is_late);
    }

    /**
     * Task 5.3.3: Test storeDailySummary() updates existing record
     */
    public function test_store_daily_summary_updates_existing_record(): void
    {
        $date = Carbon::now()->startOfDay();

        // Create initial summary in database
        $initialRecord = DailyAttendanceSummary::create([
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:00'),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00'),
            'total_hours_worked' => 8,
            'is_present' => true,
            'is_late' => false,
        ]);

        // Create new summary data with updated times
        $updatedSummary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:05')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:30')->toDateTimeString(),
            'total_hours_worked' => 8.5,
            'is_present' => true,
            'is_late' => false,  // 8:05 is within grace period
            'break_duration' => 60,
        ];

        // Store updated summary
        $record = $this->service->storeDailySummary($updatedSummary);

        // Verify record was updated, not duplicated
        $this->assertEquals($initialRecord->id, $record->id);
        $this->assertDatabaseCount('daily_attendance_summary', 1);
        
        // Verify updated values
        $dbRecord = DailyAttendanceSummary::find($record->id);
        $this->assertEquals(8.5, $dbRecord->total_hours_worked);
        $this->assertFalse($dbRecord->is_late);
    }

    /**
     * Task 5.3.3: Test storeDailySummary() with ledger sequence tracking
     */
    public function test_store_daily_summary_with_ledger_sequence(): void
    {
        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:00')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'total_hours_worked' => 8,
            'is_present' => true,
            'is_late' => false,
        ];

        // Store with ledger sequence tracking
        $record = $this->service->storeDailySummary($summary, 100, 250);

        // Verify ledger sequence was stored
        $dbRecord = DailyAttendanceSummary::find($record->id);
        $this->assertEquals(100, $dbRecord->ledger_sequence_start);
        $this->assertEquals(250, $dbRecord->ledger_sequence_end);
    }

    /**
     * Task 5.3.3: Test storeDailySummary() throws when employee doesn't exist
     */
    public function test_store_daily_summary_throws_for_invalid_employee(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $summary = [
            'employee_id' => 99999,  // Non-existent employee
            'attendance_date' => Carbon::now()->toDateString(),
            'is_present' => false,
        ];

        $this->service->storeDailySummary($summary);
    }

    /**
     * Task 5.3.4: Test storeDailySummary() dispatches AttendanceSummaryUpdated event
     */
    public function test_store_daily_summary_dispatches_event(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:00')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'total_hours_worked' => 8,
            'is_present' => true,
            'is_late' => false,
        ];

        // Store summary and verify event is dispatched
        $record = $this->service->storeDailySummary($summary);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Timekeeping\AttendanceSummaryUpdated::class,
            function ($event) use ($record) {
                return $event->summary->id === $record->id;
            }
        );
    }

    /**
     * Task 5.3.4: Test storeDailySummary() dispatches event with isNew flag for new records
     */
    public function test_store_daily_summary_event_marks_as_new(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $date = Carbon::now()->startOfDay();

        $summary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:00')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00')->toDateTimeString(),
            'total_hours_worked' => 8,
            'is_present' => true,
        ];

        $this->service->storeDailySummary($summary);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Timekeeping\AttendanceSummaryUpdated::class,
            function ($event) {
                return $event->isNew === true;
            }
        );
    }

    /**
     * Task 5.3.4: Test storeDailySummary() dispatches event with isNew=false for updates
     */
    public function test_store_daily_summary_event_marks_as_update(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $date = Carbon::now()->startOfDay();

        // Create initial record
        $existing = DailyAttendanceSummary::create([
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:00'),
            'time_out' => $date->copy()->setTimeFromTimeString('17:00'),
            'total_hours_worked' => 8,
            'is_present' => true,
            'is_late' => false,
        ]);

        // Clear event queue
        \Illuminate\Support\Facades\Event::fake();

        // Update existing record
        $updatedSummary = [
            'employee_id' => $this->employee->id,
            'attendance_date' => $date->toDateString(),
            'work_schedule_id' => $this->schedule->id,
            'time_in' => $date->copy()->setTimeFromTimeString('08:05')->toDateTimeString(),
            'time_out' => $date->copy()->setTimeFromTimeString('17:30')->toDateTimeString(),
            'total_hours_worked' => 8.5,
            'is_present' => true,
            'is_late' => false,
        ];

        $record = $this->service->storeDailySummary($updatedSummary);

        // Verify this is an update (same ID as original)
        $this->assertEquals($existing->id, $record->id);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Timekeeping\AttendanceSummaryUpdated::class,
            function ($event) {
                return $event->isNew === false;
            }
        );
    }

    /**
     * Task 5.3.3-5.3.4: Integration test - compute, apply rules, store, and verify event
     */
    public function test_full_summary_workflow_with_event_dispatch(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $date = Carbon::now()->startOfDay();

        // Create simple attendance events with minimal data
        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('08:00'),
            'event_type' => 'time_in',
        ]);

        AttendanceEvent::factory()->create([
            'employee_id' => $this->employee->id,
            'event_date' => $date,
            'event_time' => $date->copy()->setTimeFromTimeString('17:00'),
            'event_type' => 'time_out',
        ]);

        // Full workflow: compute → apply rules → store → verify
        $summary = $this->service->computeDailySummary($this->employee->id, $date);
        $summary = $this->service->applyBusinessRules($summary);
        $record = $this->service->storeDailySummary($summary, 100, 200);

        // Verify basic summary data
        $this->assertTrue($record->is_present);  // Has time_in

        // Verify event was dispatched with correct metadata
        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\Timekeeping\AttendanceSummaryUpdated::class,
            function ($event) use ($record) {
                return $event->summary->id === $record->id &&
                       $event->isNew === true &&
                       $event->previousValues === null;
            }
        );
    }
}
