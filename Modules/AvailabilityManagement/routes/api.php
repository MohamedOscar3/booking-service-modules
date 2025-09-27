<?php

use Illuminate\Support\Facades\Route;
use Modules\AvailabilityManagement\Http\Controllers\Api\AvailabilityManagementController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Main CRUD routes
    Route::apiResource('availability-management', AvailabilityManagementController::class)->names('availability-management');

    // Additional routes
    Route::get('availability-management/provider/{providerId}', [AvailabilityManagementController::class, 'getByProvider']);
    Route::get('availability-management/available/{date}', [AvailabilityManagementController::class, 'getAvailableForDate']);
    Route::get('availability-management/provider/{providerId}/recurring', [AvailabilityManagementController::class, 'getRecurring']);
});
