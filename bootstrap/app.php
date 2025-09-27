<?php

use App\Services\ApiResponseService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api([
            \App\Http\Middleware\TimezoneResponseMiddleware::class,
            \App\Http\Middleware\TimezoneMiddleware::class,
        ]
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register the custom exception handler
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return app(ApiResponseService::class)->handleException($e);
            }

            return null;
        });
    })->create();
