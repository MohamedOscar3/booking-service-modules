<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Controllers\Api\ServiceController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('services', ServiceController::class)->names('service');
});
