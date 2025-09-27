<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Mail\BookingConfirmationMail;
use Modules\Booking\Models\Booking;

class SendBookingConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function handle(): void
    {
        // Load necessary relationships
        $this->booking->load(['user', 'service', 'provider']);

        // Send confirmation email to customer
        Mail::to($this->booking->user->email)
            ->send(new BookingConfirmationMail($this->booking));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'booking:'.$this->booking->id,
            'email:confirmation',
            'user:'.$this->booking->user_id,
        ];
    }
}
