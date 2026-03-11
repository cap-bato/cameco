<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentRequestProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private DocumentRequest $documentRequest, private string $status, private ?string $filePath = null)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $documentType = $this->formatDocumentType($this->documentRequest->document_type);

        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject("Document Request Approved - {$documentType}")
                ->greeting("Hello {$notifiable->name},")
                ->line("Your document request for **{$documentType}** has been approved.")
                ->line('You can now download the document from your employee portal.')
                ->action('View My Documents', url('/employee/documents'))
                ->line('Thank you for using our system!');
        }

        return (new MailMessage)
            ->subject("Document Request Update - {$documentType}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your document request for **{$documentType}** could not be processed.")
            ->line("**Reason:** {$this->documentRequest->rejection_reason}")
            ->line('Please contact HR if you have questions or need to submit a new request.')
            ->action('Contact HR', url('/employee/support'));
    }

    public function toArray($notifiable): array
    {
        return [
            'document_request_id' => $this->documentRequest->id,
            'document_type' => $this->documentRequest->document_type,
            'status' => $this->status,
            'file_path' => $this->filePath,
            'processed_by' => auth()->user()?->name,
            'processed_at' => now()->toDateTimeString(),
        ];
    }

    private function formatDocumentType(string $type): string
    {
        return match ($type) {
            'certificate_of_employment' => 'Certificate of Employment',
            'payslip' => 'Payslip',
            'bir_form_2316' => 'BIR Form 2316',
            'government_compliance' => 'Government Compliance Document',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}