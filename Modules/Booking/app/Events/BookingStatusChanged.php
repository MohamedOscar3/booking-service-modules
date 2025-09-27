<?php

namespace Modules\Booking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Models\Booking;

class BookingStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $booking,
        public BookingStatusEnum $oldStatus,
        public BookingStatusEnum $newStatus
    ) {}
}
