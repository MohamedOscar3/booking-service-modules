<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Api\AdminReportController;
use Modules\Booking\Http\Controllers\Api\AdminReportDownloadController;
use Modules\Booking\Http\Controllers\Api\BookingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Real-time availability endpoints
    Route::get('bookings/availability', [BookingController::class, 'availability'])
        ->name('bookings.availability');
    Route::post('bookings/check-slot', [BookingController::class, 'checkSlot'])
        ->name('bookings.check-slot');

    // Booking status management
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->name('bookings.confirm');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::post('bookings/{booking}/complete', [BookingController::class, 'complete'])
        ->name('bookings.complete');

    // Bookings by status
    Route::get('bookings/status/{status}', [BookingController::class, 'byStatus'])
        ->name('bookings.by-status');

    // Standard CRUD routes
    Route::apiResource('bookings', BookingController::class)->names('bookings');

    // Admin reporting endpoints (admin only)
    Route::prefix('admin/reports')->name('admin.reports.')->group(function () {
        // Report data endpoints
        Route::get('bookings/per-provider', [AdminReportController::class, 'totalBookingsPerProvider'])
            ->name('bookings.per-provider');
        Route::get('services/rates', [AdminReportController::class, 'cancelledVsConfirmedRatePerService'])
            ->name('services.rates');
        Route::get('bookings/peak-hours', [AdminReportController::class, 'peakHoursByDayWeek'])
            ->name('bookings.peak-hours');
        Route::get('customers/duration-analysis', [AdminReportController::class, 'averageBookingDurationPerCustomer'])
            ->name('customers.duration-analysis');

        // Excel export endpoints (queued)
        Route::post('export/bookings/per-provider', [AdminReportController::class, 'queueProviderBookingsExport'])
            ->name('export.bookings.per-provider');
        Route::post('export/services/rates', [AdminReportController::class, 'queueServiceBookingRatesExport'])
            ->name('export.services.rates');
        Route::post('export/bookings/peak-hours', [AdminReportController::class, 'queuePeakHoursExport'])
            ->name('export.bookings.peak-hours');
        Route::post('export/customers/duration-analysis', [AdminReportController::class, 'queueCustomerBookingDurationExport'])
            ->name('export.customers.duration-analysis');

        // Download endpoint for queued reports
        Route::get('download/{filename}', [AdminReportDownloadController::class, 'download'])
            ->name('download')
            ->where('filename', '[a-zA-Z0-9_\-\.]+');
    });
});
