<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Mail\BookingStatusUpdateMail;
use Modules\Booking\Models\Booking;

class SendBookingStatusUpdateEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public BookingStatusEnum $oldStatus,
        public BookingStatusEnum $newStatus
    ) {}

    public function handle(): void
    {
        // Load necessary relationships
        $this->booking->load(['user', 'service', 'provider']);

        // Send status update email to customer
        Mail::to($this->booking->user->email)
            ->send(new BookingStatusUpdateMail($this->booking, $this->oldStatus, $this->newStatus));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'booking:'.$this->booking->id,
            'email:status-update',
            'user:'.$this->booking->user_id,
            'status:'.$this->newStatus->value,
        ];
    }
}
