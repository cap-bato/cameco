<?php

namespace App\Notifications;

use App\Models\OffboardingCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ExitInterviewPending Notification
 *
 * Sent to employee when they need to complete their exit interview.
 * Recipients: Departing employee
 *
 * Phase 4, Task 4.1.4
 */
class ExitInterviewPending extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private OffboardingCase $offboardingCase)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $this->offboardingCase->employee->profile?->first_name . ' ' .
                       $this->offboardingCase->employee->profile?->last_name;
        $lastWorkingDay = 'Not specified';
        if ($this->offboardingCase->last_working_day) {
            try {
                $date = new \DateTime($this->offboardingCase->last_working_day);
                $lastWorkingDay = $date->format('F d, Y');
            } catch (\Exception $e) {
                $lastWorkingDay = $this->offboardingCase->last_working_day;
            }
        }

        return (new MailMessage)
            ->subject("Exit Interview Required")
            ->greeting("Hello {$employeeName},")
            ->line("As part of your offboarding process, we would like to gather your feedback through an exit interview.")
            ->line("Your honest feedback is valuable to us and will help us improve our workplace environment.")
            ->line("")
            ->line("**Interview Details**")
            ->line("Your Last Working Day: **{$lastWorkingDay}**")
            ->line("Case Number: **{$this->offboardingCase->case_number}**")
            ->line("")
            ->action('Complete Exit Interview', url("/employee/offboarding/exit-interview/{$this->offboardingCase->id}"))
            ->line("The interview typically takes 10-15 minutes to complete.")
            ->line("Your responses are confidential and used only for feedback and improvement purposes.");
    }

    /**
     * Get the array representation of the notification (for database storage).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employeeName = $this->offboardingCase->employee->profile?->first_name . ' ' .
                       $this->offboardingCase->employee->profile?->last_name;

        return [
            'type' => 'exit_interview_pending',
            'case_id' => $this->offboardingCase->id,
            'case_number' => $this->offboardingCase->case_number,
            'employee_name' => $employeeName,
            'last_working_day' => $this->offboardingCase->last_working_day,
            'message' => "Please complete your exit interview",
            'action_url' => url("/employee/offboarding/exit-interview/{$this->offboardingCase->id}"),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
