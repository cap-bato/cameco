<?php

namespace Tests\Unit\Controllers\HR\Timekeeping;

use Tests\TestCase;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

class AttendanceCorrectionApproveTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $employee;
    protected User $manager;
    protected DailyAttendanceSummary $summary;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the permission
        Permission::create(['name' => 'hr.timekeeping.corrections.approve', 'guard_name' => 'web']);

        // Create test employee and manager user
        $this->employee = Employee::factory()->create();
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('hr.timekeeping.corrections.approve');

        // Create a work schedule (required by DailyAttendanceSummary foreign key)
        $workSchedule = WorkSchedule::factory()->create();

        // Create daily attendance summary with wrong time (late by 90 minutes)
        $this->summary = DailyAttendanceSummary::create([
            'employee_id' => $this->employee->id,
            'attendance_date' => '2026-03-06',
            'work_schedule_id' => $workSchedule->id,
            'time_in' => '2026-03-06 09:30:00',
            'time_out' => '2026-03-06 17:30:00',
            'total_hours_worked' => 8.0,
            'regular_hours' => 8.0,
            'overtime_hours' => 0.0,
            'break_duration' => 60,
            'is_present' => true,
            'is_late' => true,
            'is_undertime' => false,
            'is_overtime' => false,
            'late_minutes' => 90,
            'undertime_minutes' => 0,
            'is_on_leave' => false,
            'ledger_verified' => true,
            'calculated_at' => now(),
            'is_finalized' => true,
            'correction_applied' => false,
        ]);
    }

    /**
     * Test: Correction updates daily summary property fields correctly
     */
    public function test_updateDailyAttendanceSummary_corrects_time_in()
    {
        // Create a mock attendance event
        $event = AttendanceEvent::create([
            'employee_id' => $this->employee->id,
            'event_date' => Carbon::parse('2026-03-06'),
            'event_time' => Carbon::parse('2026-03-06 09:30:00'),
            'event_type' => 'time_out',
            'ledger_sequence_id' => 9999,
            'is_deduplicated' => true,
            'ledger_hash_verified' => true,
            'source' => 'manual',
            'created_by' => $this->manager->id,
        ]);

        // Create correction with correct time_in
        $correction = AttendanceCorrection::create([
            'attendance_event_id' => $event->id,
            'requested_by_user_id' => $this->employee->id,
            'original_time_in' => '2026-03-06 09:30:00',
            'original_time_out' => '2026-03-06 17:30:00',
            'corrected_time_in' => '08:05',
            'corrected_time_out' => null,
            'correction_reason' => 'wrong_entry',
            'justification' => 'Employee clocked in at 08:05, not 09:30',
            'hours_difference' => 1.42,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Test the updateDailyAttendanceSummary method directly
        $controller = app(\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class);
        $updateMethod = new \ReflectionMethod($controller, 'updateDailyAttendanceSummary');
        $updateMethod->setAccessible(true);
        $updateMethod->invoke($controller, $correction);

        // Refresh and verify summary was updated
        $this->summary->refresh();

        // Verify time_in was corrected (08:05)
        $this->assertStringContainsString('08:05', $this->summary->time_in->format('H:i:s'));

        // Verify late_minutes was recalculated (8:05 is within 15-minute grace period, so 0 late minutes)
        $this->assertEquals(0, $this->summary->late_minutes);

        // Verify is_finalized was NOT reset
        $this->assertTrue($this->summary->is_finalized);

        // Verify correction_applied flag was set
        $this->assertTrue($this->summary->correction_applied);
    }

    /**
     * Test: Partial correction only updates provided fields
     */
    public function test_updateDailyAttendanceSummary_partial_correction()
    {
        $event = AttendanceEvent::create([
            'employee_id' => $this->employee->id,
            'event_date' => Carbon::parse('2026-03-06'),
            'event_time' => Carbon::parse('2026-03-06 09:30:00'),
            'event_type' => 'time_out',
            'ledger_sequence_id' => 8888,
            'is_deduplicated' => true,
            'ledger_hash_verified' => true,
            'source' => 'manual',
            'created_by' => $this->manager->id,
        ]);

        // Partial correction: only time_in
        $correction = AttendanceCorrection::create([
            'attendance_event_id' => $event->id,
            'requested_by_user_id' => $this->employee->id,
            'original_time_in' => '2026-03-06 09:30:00',
            'original_time_out' => '2026-03-06 17:30:00',
            'corrected_time_in' => '08:00',
            'corrected_time_out' => null,
            'correction_reason' => 'wrong_entry',
            'justification' => 'Only time_in was wrong',
            'hours_difference' => 1.5,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $controller = app(\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class);
        $updateMethod = new \ReflectionMethod($controller, 'updateDailyAttendanceSummary');
        $updateMethod->setAccessible(true);
        $updateMethod->invoke($controller, $correction);

        $this->summary->refresh();

        // Verify time_in was corrected (08:00)
        $this->assertStringContainsString('08:00', $this->summary->time_in->format('H:i:s'));

        // Verify time_out was NOT changed
        $this->assertStringContainsString('17:30', $this->summary->time_out->format('H:i:s'));

        // Late minutes should now be 0 (on time at 08:00)
        $this->assertEquals(0, $this->summary->late_minutes);
    }

    /**
     * Test: Correction preserves is_finalized state
     */
    public function test_updateDailyAttendanceSummary_preserves_finalized_state()
    {
        $this->assertTrue($this->summary->is_finalized);

        $event = AttendanceEvent::create([
            'employee_id' => $this->employee->id,
            'event_date' => Carbon::parse('2026-03-06'),
            'event_time' => Carbon::parse('2026-03-06 10:00:00'),
            'event_type' => 'time_out',
            'ledger_sequence_id' => 7777,
            'is_deduplicated' => true,
            'ledger_hash_verified' => true,
            'source' => 'edge_machine',
            'created_by' => $this->manager->id,
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_event_id' => $event->id,
            'requested_by_user_id' => $this->employee->id,
            'original_time_in' => '2026-03-06 09:30:00',
            'original_time_out' => '2026-03-06 17:30:00',
            'corrected_time_in' => '08:10',
            'corrected_time_out' => null,
            'correction_reason' => 'wrong_entry',
            'justification' => 'Timing correction',
            'hours_difference' => 1.33,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $controller = app(\App\Http\Controllers\HR\Timekeeping\AttendanceCorrectionController::class);
        $updateMethod = new \ReflectionMethod($controller, 'updateDailyAttendanceSummary');
        $updateMethod->setAccessible(true);
        $updateMethod->invoke($controller, $correction);

        $this->summary->refresh();

        // is_finalized MUST remain true
        $this->assertTrue($this->summary->is_finalized);
    }
}
