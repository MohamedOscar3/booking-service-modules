<?php

namespace App\Providers;

use App\Services\ApiResponseService;
use App\Services\TimezoneService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(ApiResponseService::class, function () {
            return new ApiResponseService;
        });

        $this->app->singleton(TimezoneService::class, function () {
            return new TimezoneService;
        });
    }
}
