<?php

namespace App\Notifications;

use App\Models\ClearanceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ClearanceItemApproved Notification
 *
 * Sent to HR Coordinator when a clearance item has been approved.
 * Recipients: HR Coordinator
 *
 * Phase 4, Task 4.1.3
 */
class ClearanceItemApproved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private ClearanceItem $clearanceItem, private string $approverName)
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
        $approvedAt = $this->clearanceItem->approved_at?->format('F d, Y \a\t g:i A') ?? now()->format('F d, Y \a\t g:i A');

        return (new MailMessage)
            ->subject("Clearance Approved: {$itemName} for {$employeeName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A clearance item has been approved.")
            ->line("")
            ->line("**Approval Details**")
            ->line("Item: **{$itemName}**")
            ->line("Category: **{$category}**")
            ->line("Employee: **{$employeeName}**")
            ->line("Approved By: **{$this->approverName}**")
            ->line("Approved At: **{$approvedAt}**")
            ->line("")
            ->action('View Case', url("/hr/offboarding/cases/{$this->clearanceItem->offboarding_case_id}"))
            ->line('This clearance item has been completed successfully.');
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
            'type' => 'clearance_item_approved',
            'clearance_item_id' => $this->clearanceItem->id,
            'case_id' => $this->clearanceItem->offboarding_case_id,
            'item_name' => $this->clearanceItem->item_name,
            'employee_name' => $employeeName,
            'approver_name' => $this->approverName,
            'approved_at' => $this->clearanceItem->approved_at?->format('Y-m-d H:i:s'),
            'message' => "Clearance approved for {$this->clearanceItem->item_name} ({$employeeName})",
            'action_url' => url("/hr/offboarding/cases/{$this->clearanceItem->offboarding_case_id}"),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
