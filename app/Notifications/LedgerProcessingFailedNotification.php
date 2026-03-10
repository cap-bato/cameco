<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * LedgerProcessingFailedNotification
 * 
 * Notification sent to HR Managers when RFID ledger processing fails
 * or when critical integrity issues are detected.
 * 
 * Phase 6, Task 6.1.3: Failure notifications
 * 
 * @package App\Notifications
 */
class LedgerProcessingFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var string Error message
     */
    private string $errorMessage;

    /**
     * @var string Severity level (error, warning, critical)
     */
    private string $severity;

    /**
     * @var array Additional context data
     */
    private array $context;

    /**
     * Create a new notification instance.
     *
     * @param string $errorMessage
     * @param string $severity
     * @param array $context
     */
    public function __construct(string $errorMessage, string $severity = 'error', array $context = [])
    {
        $this->errorMessage = $errorMessage;
        $this->severity = $severity;
        $this->context = $context;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Send via database notification and email for critical issues
        return $this->severity === 'critical' 
            ? ['database', 'mail'] 
            : ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $severityLabels = [
            'critical' => '🔴 CRITICAL',
            'error' => '🟠 ERROR',
            'warning' => '🟡 WARNING'
        ];

        $label = $severityLabels[$this->severity] ?? '⚪ INFO';

        return (new MailMessage)
            ->error()
            ->subject("{$label}: RFID Ledger Processing Failure")
            ->greeting("Hello {$notifiable->name},")
            ->line("An issue has been detected with the RFID attendance ledger processing:")
            ->line("**Error:** {$this->errorMessage}")
            ->line("**Severity:** {$label}")
            ->line("**Timestamp:** " . ($this->context['timestamp'] ?? now()))
            ->line("**Job:** " . ($this->context['job'] ?? 'Unknown'))
            ->line("**Retry Attempts:** " . ($this->context['attempts'] ?? 'N/A') . " / " . ($this->context['max_tries'] ?? 'N/A'))
            ->action('View Ledger Health Dashboard', url('/hr/timekeeping/ledger/health'))
            ->line('Please check the ledger health dashboard for more details and take corrective action if needed.')
            ->line('If this is a critical issue, payroll processing may be blocked until resolved.');
    }

    /**
     * Get the array representation of the notification (for database storage).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ledger_processing_failed',
            'severity' => $this->severity,
            'message' => $this->errorMessage,
            'context' => $this->context,
            'action_url' => url('/hr/timekeeping/ledger/health'),
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * Get the notification's database type (for filtering).
     *
     * @return string
     */
    public function databaseType(object $notifiable): string
    {
        return 'timekeeping.ledger.failure';
    }
}
