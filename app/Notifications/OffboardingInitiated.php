<?php

namespace App\Notifications;

use App\Models\OffboardingCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * OffboardingInitiated Notification
 *
 * Sent when an offboarding case is initiated for an employee.
 * Recipients: Employee, Manager, HR Coordinator, Department Heads
 *
 * Phase 4, Task 4.1.1
 */
class OffboardingInitiated extends Notification implements ShouldQueue
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
        $lastWorkingDay = 'Not specified';
        if ($this->offboardingCase->last_working_day) {
            try {
                $date = new \DateTime($this->offboardingCase->last_working_day);
                $lastWorkingDay = $date->format('F d, Y');
            } catch (\Exception $e) {
                $lastWorkingDay = $this->offboardingCase->last_working_day;
            }
        }
        $separationType = ucfirst(str_replace('_', ' ', $this->offboardingCase->separation_type));

        // Determine subject and message based on recipient role
        $subject = "Offboarding Initiated for {$employeeName}";
        $greeting = "Hello {$notifiable->name},";

        if ($notifiable->id === $this->offboardingCase->employee_id) {
            $subject = "Your Offboarding Process Has Been Initiated";
            $greeting = "Hello {$employeeName},";
            $message = "Your employment termination has been processed. This notification confirms the start of your offboarding process.";
        } else {
            $message = "An offboarding case has been initiated for {$employeeName}.";
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($message)
            ->line("")
            ->line("**Offboarding Case Details**")
            ->line("Case Number: **{$caseNumber}**")
            ->line("Employee: **{$employeeName}**")
            ->line("Employee Number: **{$this->offboardingCase->employee->employee_number}**")
            ->line("Department: **{$this->offboardingCase->employee->department?->name}**")
            ->line("Separation Type: **{$separationType}**")
            ->line("Last Working Day: **{$lastWorkingDay}**")
            ->line("Separation Reason: **{$this->offboardingCase->separation_reason}**")
            ->line("")
            ->action('View Case Details', url("/hr/offboarding/cases/{$this->offboardingCase->id}"))
            ->line("For questions or assistance, please contact your HR Coordinator.");
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
            'type' => 'offboarding_initiated',
            'case_id' => $this->offboardingCase->id,
            'case_number' => $this->offboardingCase->case_number,
            'employee_name' => $employeeName,
            'employee_id' => $this->offboardingCase->employee_id,
            'last_working_day' => $this->offboardingCase->last_working_day,
            'separation_type' => $this->offboardingCase->separation_type,
            'message' => "Offboarding initiated for {$employeeName} (Case #{$this->offboardingCase->case_number})",
            'action_url' => url("/hr/offboarding/cases/{$this->offboardingCase->id}"),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
