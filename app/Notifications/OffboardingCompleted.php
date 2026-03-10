<?php

namespace App\Notifications;

use App\Models\OffboardingCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * OffboardingCompleted Notification
 *
 * Sent to all stakeholders when offboarding process is completed.
 * Recipients: Employee, Manager, HR Coordinator, Department Head
 *
 * Phase 4, Task 4.1.7
 */
class OffboardingCompleted extends Notification implements ShouldQueue
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
        $caseNumber = $this->offboardingCase->case_number;
        $completedAt = $this->offboardingCase->completed_at?->format('F d, Y \a\t g:i A') ?? now()->format('F d, Y \a\t g:i A');

        // Customize message based on recipient type
        if ($notifiable->id === $this->offboardingCase->employee_id) {
            $greeting = "Hello {$employeeName},";
            $subject = "Your Offboarding Process is Complete";
            $message = "Your offboarding process has been completed successfully. All clearances have been approved and final documentation has been processed.";
        } else {
            $greeting = "Hello {$notifiable->name},";
            $subject = "Offboarding Completed for {$employeeName}";
            $message = "The offboarding process for {$employeeName} has been completed successfully.";
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->line("")
            ->line("**Final Details**")
            ->line("Case Number: **{$caseNumber}**")
            ->line("Employee: **{$employeeName}**")
            ->line("Employee Number: **{$this->offboardingCase->employee->employee_number}**")
            ->line("Department: **{$this->offboardingCase->employee->department?->name}**")
            ->line("Completed At: **{$completedAt}**")
            ->line("Rehire Eligible: **" . ($this->offboardingCase->rehire_eligible ? 'Yes' : 'No') . "**")
            ->line("")
            ->action('View Final Summary', url("/hr/offboarding/cases/{$this->offboardingCase->id}"))
            ->line('Thank you and best wishes for your future endeavors.');
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
            'type' => 'offboarding_completed',
            'case_id' => $this->offboardingCase->id,
            'case_number' => $this->offboardingCase->case_number,
            'employee_name' => $employeeName,
            'employee_id' => $this->offboardingCase->employee_id,
            'completed_at' => $this->offboardingCase->completed_at?->format('Y-m-d H:i:s'),
            'rehire_eligible' => $this->offboardingCase->rehire_eligible ?? false,
            'message' => "Offboarding completed for {$employeeName}",
            'action_url' => url("/hr/offboarding/cases/{$this->offboardingCase->id}"),
            'timestamp' => now()->toDateTimeString(),
            'severity' => 'info',
        ];
    }
}
