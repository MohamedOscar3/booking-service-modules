<?php

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Confirmation - '.$this->booking->service_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'booking::emails.booking-confirmation',
            with: [
                'booking' => $this->booking,
                'customer' => $this->booking->user,
                'service' => $this->booking->service,
                'provider' => $this->booking->service->provider,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
