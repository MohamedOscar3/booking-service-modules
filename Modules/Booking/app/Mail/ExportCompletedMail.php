<?php

namespace Modules\Booking\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\Models\User;

class ExportCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $exportType;

    public ?string $filename;

    public ?string $filePath;

    public ?string $errorMessage;

    public function __construct(User $user, string $exportType, ?string $filename = null, ?string $filePath = null, ?string $errorMessage = null)
    {
        $this->user = $user;
        $this->exportType = $exportType;
        $this->filename = $filename;
        $this->filePath = $filePath;
        $this->errorMessage = $errorMessage;
    }

    public function envelope(): Envelope
    {
        $subject = $this->errorMessage
            ? 'Export Failed - '.$this->getExportDisplayName()
            : 'Export Completed - '.$this->getExportDisplayName();

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'booking::emails.export-completed',
            with: [
                'user' => $this->user,
                'exportType' => $this->exportType,
                'exportDisplayName' => $this->getExportDisplayName(),
                'filename' => $this->filename,
                'errorMessage' => $this->errorMessage,
                'isSuccess' => ! $this->errorMessage,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->filePath || $this->errorMessage) {
            return [];
        }

        return [
            Attachment::fromStorage($this->filePath)
                ->as($this->filename)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    protected function getExportDisplayName(): string
    {
        return match ($this->exportType) {
            'provider-bookings' => 'Provider Bookings Report',
            'service-booking-rates' => 'Service Booking Rates Report',
            'peak-hours' => 'Peak Hours Analysis Report',
            'customer-booking-duration' => 'Customer Booking Duration Report',
            default => 'Admin Report',
        };
    }
}
