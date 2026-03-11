<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR Staff when an employee submits an attendance correction request.
 * 
 * Phase 3 Task 3.1: HR notification for attendance corrections
 */
class AttendanceCorrectionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Employee $employee The employee submitting the correction request
     * @param int $correctionRequestId The ID of the correction request
     * @param array $validated The validated request data
     */
    public function __construct(
        private Employee $employee,
        private int $correctionRequestId,
        private array $validated
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $issueType = $this->formatIssueType($this->validated['issue_type']);
        $attendanceDate = $this->validated['attendance_date'];

        return (new MailMessage)
            ->subject("Attendance Correction Request - {$this->employee->profile->full_name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("An employee has submitted an attendance correction request that requires your review.")
            ->line('')
            ->line('**Request Details:**')
            ->line("- **Employee:** {$this->employee->profile->full_name} ({$this->employee->employee_number})")
            ->line("- **Department:** {$this->employee->department->name}")
            ->line("- **Attendance Date:** {$attendanceDate}")
            ->line("- **Issue Type:** {$issueType}")
            ->line("- **Reason:** {$this->validated['reason']}")
            ->when($this->validated['actual_time_in'] ?? null, function ($mail) {
                return $mail->line("- **Claimed Time In:** {$this->validated['actual_time_in']}");
            })
            ->when($this->validated['actual_time_out'] ?? null, function ($mail) {
                return $mail->line("- **Claimed Time Out:** {$this->validated['actual_time_out']}");
            })
            ->line('')
            ->line('Please review and take appropriate action in the HR system.')
            ->action('Review in HR System', url('/hr/attendance/corrections/' . $this->correctionRequestId))
            ->line('Thank you for maintaining accurate attendance records.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'correction_request_id' => $this->correctionRequestId,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->profile->full_name,
            'employee_number' => $this->employee->employee_number,
            'attendance_date' => $this->validated['attendance_date'],
            'issue_type' => $this->validated['issue_type'],
            'reason' => $this->validated['reason'],
            'actual_time_in' => $this->validated['actual_time_in'] ?? null,
            'actual_time_out' => $this->validated['actual_time_out'] ?? null,
            'submitted_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Format the issue type for display.
     *
     * @param string $type
     * @return string
     */
    private function formatIssueType(string $type): string
    {
        return match ($type) {
            'missing_punch' => 'Missing Time Punch',
            'wrong_time' => 'Wrong Time Recorded',
            'other' => 'Other Discrepancy',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
