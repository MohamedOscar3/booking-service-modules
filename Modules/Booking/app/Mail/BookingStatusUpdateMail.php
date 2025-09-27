<?php

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;

class BookingStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public BookingStatusEnum $oldStatus,
        public BookingStatusEnum $newStatus
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->newStatus->label();

        return new Envelope(
            subject: "Booking {$statusLabel} - {$this->booking->service_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'booking::emails.status-update',
            with: [
                'booking' => $this->booking,
                'customer' => $this->booking->user,
                'service' => $this->booking->service,
                'provider' => $this->booking->service->provider,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
