<?php

namespace App\Notifications;

use App\Models\ClearanceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ClearanceOverdue Notification
 *
 * Sent to approvers and HR when clearance items become overdue.
 * Recipients: Assigned approver, HR Coordinator
 *
 * Phase 4, Task 4.1.8
 */
class ClearanceOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private ClearanceItem $clearanceItem,
        private int $daysOverdue
    ) {
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
        $employeeName = $this->clearanceItem->offboardingCase->employee->profile?->first_name . ' ' .
                       $this->clearanceItem->offboardingCase->employee->profile?->last_name;
        $itemName = $this->clearanceItem->item_name;
        $category = ucfirst(str_replace('_', ' ', $this->clearanceItem->category));
        $dueDate = 'Not specified';
        if ($this->clearanceItem->due_date) {
            try {
                $date = new \DateTime($this->clearanceItem->due_date);
                $dueDate = $date->format('F d, Y');
            } catch (\Exception $e) {
                $dueDate = $this->clearanceItem->due_date;
            }
        }
        $lastWorkingDay = $this->clearanceItem->offboardingCase->last_working_day->format('F d, Y');
        $priority = strtoupper($this->clearanceItem->priority);

        return (new MailMessage)
            ->error()
            ->subject("URGENT: Overdue Clearance Item - {$itemName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("**ATTENTION:** A clearance item is now {$this->daysOverdue} days overdue and requires immediate attention.")
            ->line("")
            ->line("**Overdue Item Details**")
            ->line("Item: **{$itemName}**")
            ->line("Category: **{$category}**")
            ->line("Priority: **{$priority}**")
            ->line("Employee: **{$employeeName}**")
            ->line("Employee Number: **{$this->clearanceItem->offboardingCase->employee->employee_number}**")
            ->line("Description: **{$this->clearanceItem->description}**")
            ->line("Due Date: **{$dueDate}**")
            ->line("Last Working Day: **{$lastWorkingDay}**")
            ->line("Days Overdue: **{$this->daysOverdue}**")
            ->line("")
            ->action('Review and Approve Immediately', url("/hr/offboarding/clearance?case_id={$this->clearanceItem->offboarding_case_id}"))
            ->line('This clearance item is blocking the employee\'s offboarding process. Please complete your review and approval urgently.');
    }

    /**
     * Get the array representation of the notification (for database storage).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employeeName = $this->clearanceItem->offboardingCase->employee->profile?->first_name . ' ' .
                       $this->clearanceItem->offboardingCase->employee->profile?->last_name;

        return [
            'type' => 'clearance_overdue',
            'clearance_item_id' => $this->clearanceItem->id,
            'case_id' => $this->clearanceItem->offboarding_case_id,
            'item_name' => $this->clearanceItem->item_name,
            'category' => $this->clearanceItem->category,
            'priority' => $this->clearanceItem->priority,
            'employee_name' => $employeeName,
            'due_date' => $this->clearanceItem->due_date,
            'days_overdue' => $this->daysOverdue,
            'message' => "OVERDUE: {$this->clearanceItem->item_name} for {$employeeName} ({$this->daysOverdue} days)",
            'action_url' => url("/hr/offboarding/clearance?case_id={$this->clearanceItem->offboarding_case_id}"),
            'severity' => 'critical',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
