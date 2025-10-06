<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Ensure module service providers and routes are loaded
        $moduleProviders = [
            \Modules\Auth\Providers\AuthServiceProvider::class,
            \Modules\Auth\Providers\RouteServiceProvider::class,
            \Modules\AvailabilityManagement\Providers\AvailabilityManagementServiceProvider::class,
            \Modules\AvailabilityManagement\Providers\RouteServiceProvider::class,
            \Modules\Booking\Providers\BookingServiceProvider::class,
            \Modules\Booking\Providers\RouteServiceProvider::class,
            \Modules\Category\Providers\CategoryServiceProvider::class,
            \Modules\Category\Providers\RouteServiceProvider::class,
            \Modules\Service\Providers\ServiceServiceProvider::class,
            \Modules\Service\Providers\RouteServiceProvider::class,
        ];

        foreach ($moduleProviders as $provider) {
            if (!$app->providerIsLoaded($provider)) {
                $app->register($provider);
            }
        }

        return $app;
    }
}
