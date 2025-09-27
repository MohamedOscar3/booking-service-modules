<?php

namespace Modules\Booking\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        \Modules\Booking\Events\BookingCreated::class => [
            \Modules\Booking\Listeners\SendBookingNotifications::class,
        ],
        \Modules\Booking\Events\BookingStatusChanged::class => [
            \Modules\Booking\Listeners\SendStatusChangeNotifications::class,
        ],
        \Modules\Booking\Events\BookingCancelled::class => [
            \Modules\Booking\Listeners\SendCancellationNotifications::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
