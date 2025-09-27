<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TimezoneMiddleware
{
    public function handle(Request $request, Closure $next)
    {

        $timezone = $request->header('timezone');

        if (auth()->guard('sanctum')->check() && $timezone) {
            auth()->user()->update(['timezone' => $timezone]);
        } elseif (! $timezone && auth()->guard('sanctum')->check()) {
            $timezone = auth()->guard('sanctum')->user()->timezone;
        }

        if ($timezone) {
            $request->attributes->set('render_timezone', $timezone);
        }

        return $next($request);
    }
}
