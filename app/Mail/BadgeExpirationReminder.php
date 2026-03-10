<?php

namespace App\Mail;

use App\Models\RfidCardMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BadgeExpirationReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The RFID badge mapping instance.
     *
     * @var RfidCardMapping
     */
    public $badge;

    /**
     * Days until badge expiration.
     *
     * @var int
     */
    public $daysUntilExpiry;

    /**
     * Create a new message instance.
     *
     * @param RfidCardMapping $badge
     * @param int $daysUntilExpiry
     */
    public function __construct(RfidCardMapping $badge, int $daysUntilExpiry)
    {
        $this->badge = $badge;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        $urgency = $this->daysUntilExpiry <= 7 ? '⚠️ URGENT: ' : '';
        $subject = sprintf(
            '%sRFID Badge Expiration Notice - %d Day%s Remaining',
            $urgency,
            $this->daysUntilExpiry,
            $this->daysUntilExpiry !== 1 ? 's' : ''
        );

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.badge-expiration-reminder',
            with: [
                'employeeName' => $this->badge->employee->full_name,
                'cardUid' => $this->badge->card_uid,
                'expirationDate' => $this->badge->expires_at->format('F d, Y'),
                'daysRemaining' => $this->daysUntilExpiry,
                'isUrgent' => $this->daysUntilExpiry <= 7,
                'badgeId' => $this->badge->id,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments(): array
    {
        return [];
    }
}
