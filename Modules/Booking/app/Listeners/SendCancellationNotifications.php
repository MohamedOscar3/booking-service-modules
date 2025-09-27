<?php

namespace Modules\Booking\Listeners;

use Modules\Booking\Events\BookingCancelled;
use Modules\Booking\Jobs\SendProviderNotification;

class SendCancellationNotifications
{
    public function handle(BookingCancelled $event): void
    {
        // Only notify provider if cancelled by customer
        if ($event->cancelledBy === 'customer') {
            SendProviderNotification::dispatch($event->booking, 'cancelled');
        }
    }
}
