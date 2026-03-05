<?php

namespace App\Notifications;

use App\Models\CompanyAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AssetReturnOverdue Notification
 *
 * Sent to employee when company assets are overdue for return.
 * Recipients: Departing employee
 *
 * Phase 4, Task 4.1.6
 */
class AssetReturnOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private CompanyAsset $asset, private int $daysOverdue)
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
        $assetName = $this->asset->asset_name;
        $serialNumber = $this->asset->serial_number ?? 'N/A';
        $returnDate = $this->asset->return_date ?? 'Not specified';
        if ($returnDate && $returnDate !== 'Not specified') {
            try {
                $date = new \DateTime($returnDate);
                $returnDate = $date->format('F d, Y');
            } catch (\Exception $e) {
                // Keep as is if parsing fails
            }
        }
        $assetType = $this->asset->asset_type;

        return (new MailMessage)
            ->error()
            ->subject("URGENT: Overdue Asset Return")
            ->greeting("Hello {$notifiable->name},")
            ->line("**IMPORTANT:** The following company asset was due for return {$this->daysOverdue} days ago.")
            ->line("")
            ->line("**Asset Details**")
            ->line("Asset: **{$assetName}**")
            ->line("Type: **{$assetType}**")
            ->line("Serial Number: **{$serialNumber}**")
            ->line("Due Date: **{$returnDate}**")
            ->line("Status: **OVERDUE**")
            ->line("")
            ->line("Please arrange to return this asset to the HR department immediately.")
            ->line("**Note:** Unreturned assets may result in salary deductions as per your employment agreement.")
            ->action('View My Offboarding', url("/employee/offboarding/mycase"))
            ->line('If you have already returned this asset, please contact HR with proof of return.');
    }

    /**
     * Get the array representation of the notification (for database storage).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asset_return_overdue',
            'asset_id' => $this->asset->id,
            'case_id' => $this->asset->offboarding_case_id,
            'asset_name' => $this->asset->asset_name,
            'asset_type' => $this->asset->asset_type,
            'serial_number' => $this->asset->serial_number,
            'return_date' => $this->asset->return_date,
            'days_overdue' => $this->daysOverdue,
            'message' => "Asset return overdue: {$this->asset->asset_name} ({$this->daysOverdue} days)",
            'action_url' => url("/employee/offboarding/mycase"),
            'severity' => 'high',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
