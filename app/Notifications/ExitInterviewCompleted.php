<?php

namespace App\Notifications;

use App\Models\ExitInterview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ExitInterviewCompleted Notification
 *
 * Sent to HR when an employee completes their exit interview.
 * Recipients: HR Coordinator
 *
 * Phase 4, Task 4.1.5
 */
class ExitInterviewCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private ExitInterview $exitInterview)
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
        $offboardingCase = $this->exitInterview->offboardingCase;
        $employeeName = $offboardingCase->employee->profile?->first_name . ' ' .
                       $offboardingCase->employee->profile?->last_name;
        $completedAt = $this->exitInterview->completed_at?->format('F d, Y \a\t g:i A') ?? now()->format('F d, Y \a\t g:i A');
        $sentiment = $this->exitInterview->sentiment_classification ?? 'Neutral';
        $averageRating = $this->exitInterview->average_rating ?? 0;

        return (new MailMessage)
            ->subject("Exit Interview Completed: {$employeeName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("An exit interview has been completed.")
            ->line("")
            ->line("**Interview Summary**")
            ->line("Employee: **{$employeeName}**")
            ->line("Employee Number: **{$offboardingCase->employee->employee_number}**")
            ->line("Case Number: **{$offboardingCase->case_number}**")
            ->line("Completed At: **{$completedAt}**")
            ->line("Overall Sentiment: **{$sentiment}**")
            ->line("Average Rating: **{$averageRating}/5**")
            ->line("")
            ->action('Review Interview Details', url("/hr/offboarding/cases/{$offboardingCase->id}"))
            ->line('The complete interview details and feedback are available in the case overview.');
    }

    /**
     * Get the array representation of the notification (for database storage).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $offboardingCase = $this->exitInterview->offboardingCase;
        $employeeName = $offboardingCase->employee->profile?->first_name . ' ' .
                       $offboardingCase->employee->profile?->last_name;

        return [
            'type' => 'exit_interview_completed',
            'exit_interview_id' => $this->exitInterview->id,
            'case_id' => $offboardingCase->id,
            'case_number' => $offboardingCase->case_number,
            'employee_name' => $employeeName,
            'completed_at' => $this->exitInterview->completed_at?->format('Y-m-d H:i:s'),
            'sentiment' => $this->exitInterview->sentiment_classification ?? 'Neutral',
            'average_rating' => $this->exitInterview->average_rating ?? 0,
            'message' => "Exit interview completed for {$employeeName}",
            'action_url' => url("/hr/offboarding/cases/{$offboardingCase->id}"),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
