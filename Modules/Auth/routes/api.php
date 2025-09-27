<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\LoginController;
use Modules\Auth\Http\Controllers\Api\RegisterController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, '__invoke'])->middleware('throttle:10,1');
    Route::post('/login', [LoginController::class, '__invoke'])->middleware('throttle:10,1');

});
