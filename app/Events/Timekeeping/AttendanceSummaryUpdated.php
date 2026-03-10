<?php

namespace App\Events\Timekeeping;

use App\Models\DailyAttendanceSummary;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AttendanceSummaryUpdated Event
 * 
 * Task 5.3.4: Dispatched when a daily attendance summary is computed and stored.
 * Triggers downstream processing and notifications based on summary status.
 * 
 * Integration points:
 * - Payroll module: Recompute payroll affected by summary changes
 * - Notification module: Send alerts for late arrivals, absences, violations
 * - Appraisal module: Update performance metrics (attendance quality)
 * - Audit logging: Record summary creation/update for compliance
 * 
 * Event Flow:
 * 1. AttendanceSummaryService::storeDailySummary() saves summary to database
 * 2. DailyAttendanceSummary model fires event (via model observer)
 * 3. Event listeners in Payroll, Notification, Appraisal modules react
 */
class AttendanceSummaryUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DailyAttendanceSummary $summary;
    public bool $isNew = false;
    public ?array $previousValues = null;

    /**
     * Create a new event instance.
     * 
     * @param DailyAttendanceSummary $summary The attendance summary that was updated
     * @param bool $isNew True if this is a new record, false if updated
     * @param array|null $previousValues Previous values for change tracking (for updates)
     */
    public function __construct(
        DailyAttendanceSummary $summary,
        bool $isNew = false,
        ?array $previousValues = null
    ) {
        $this->summary = $summary;
        $this->isNew = $isNew;
        $this->previousValues = $previousValues;
    }
}
