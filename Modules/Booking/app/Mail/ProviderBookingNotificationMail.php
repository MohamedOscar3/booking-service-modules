<?php

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;

class ProviderBookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $notificationType
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'new' => 'New Booking Request - '.$this->booking->service_name,
            'cancelled' => 'Booking Cancelled - '.$this->booking->service_name,
            'updated' => 'Booking Updated - '.$this->booking->service_name,
            default => 'Booking Notification - '.$this->booking->service_name,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'booking::emails.provider-notification',
            with: [
                'booking' => $this->booking,
                'customer' => $this->booking->user,
                'service' => $this->booking->service,
                'notificationType' => $this->notificationType,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
