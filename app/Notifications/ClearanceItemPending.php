<?php

namespace App\Notifications;

use App\Models\ClearanceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ClearanceItemPending Notification
 *
 * Sent to approvers when they have pending clearance items to approve.
 * Recipients: Assigned approvers
 *
 * Phase 4, Task 4.1.2
 */
class ClearanceItemPending extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private ClearanceItem $clearanceItem)
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
        $employeeName = $this->clearanceItem->offboardingCase->employee->profile?->first_name . ' ' .
                       $this->clearanceItem->offboardingCase->employee->profile?->last_name;
        $itemName = $this->clearanceItem->item_name;
        $category = ucfirst(str_replace('_', ' ', $this->clearanceItem->category));
        $priority = strtoupper($this->clearanceItem->priority);
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

        return (new MailMessage)
            ->subject("Action Required: Clearance Approval for {$employeeName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A clearance item requires your approval before the employee's offboarding can proceed.")
            ->line("")
            ->line("**Clearance Item Details**")
            ->line("Item: **{$itemName}**")
            ->line("Category: **{$category}**")
            ->line("Priority: **{$priority}**")
            ->line("Employee: **{$employeeName}**")
            ->line("Employee Number: **{$this->clearanceItem->offboardingCase->employee->employee_number}**")
            ->line("Department: **{$this->clearanceItem->offboardingCase->employee->department?->name}**")
            ->line("Description: **{$this->clearanceItem->description}**")
            ->line("Due Date: **{$dueDate}**")
            ->line("Last Working Day: **{$lastWorkingDay}**")
            ->line("")
            ->action('Review and Approve', url("/hr/offboarding/clearance?case_id={$this->clearanceItem->offboarding_case_id}"))
            ->line('Please review the item and provide your approval or raise any issues.');
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
            'type' => 'clearance_item_pending',
            'clearance_item_id' => $this->clearanceItem->id,
            'case_id' => $this->clearanceItem->offboarding_case_id,
            'item_name' => $this->clearanceItem->item_name,
            'category' => $this->clearanceItem->category,
            'priority' => $this->clearanceItem->priority,
            'employee_name' => $employeeName,
            'due_date' => $this->clearanceItem->due_date,
            'message' => "Clearance approval needed for {$this->clearanceItem->item_name} ({$employeeName})",
            'action_url' => url("/hr/offboarding/clearance?case_id={$this->clearanceItem->offboarding_case_id}"),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
