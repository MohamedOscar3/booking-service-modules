<?php

namespace Modules\Booking\Listeners;

use Modules\Booking\Events\BookingStatusChanged;
use Modules\Booking\Jobs\SendBookingStatusUpdateEmail;

class SendStatusChangeNotifications
{
    public function handle(BookingStatusChanged $event): void
    {
        // Send status update email to customer
        SendBookingStatusUpdateEmail::dispatch(
            $event->booking,
            $event->oldStatus,
            $event->newStatus
        );
    }
}
