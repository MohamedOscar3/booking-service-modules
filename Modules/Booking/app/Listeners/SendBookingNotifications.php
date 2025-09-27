<?php

namespace Modules\Booking\Listeners;

use Modules\Booking\Events\BookingCreated;
use Modules\Booking\Jobs\SendBookingConfirmationEmail;
use Modules\Booking\Jobs\SendProviderNotification;

class SendBookingNotifications
{
    public function handle(BookingCreated $event): void
    {
        // Send confirmation email to customer
        SendBookingConfirmationEmail::dispatch($event->booking);

        // Send notification to provider
        SendProviderNotification::dispatch($event->booking, 'new');
    }
}
