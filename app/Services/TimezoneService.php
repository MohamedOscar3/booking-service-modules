<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * TimezoneService
 *
 * @description Global service for timezone encoding and decoding
 */
class TimezoneService
{
    /**
     * Convert datetime from user timezone to UTC for storage
     *
     * @param string $datetime The datetime to convert (date + time)
     * @param string $userTimezone The user's timezone
     * @return Carbon The datetime in UTC
     */
    public function convertToTimezone(string $datetime, ?string $userTimezone = null): Carbon
    {
        if ($userTimezone === null && auth()->guard('sanctum')->check()) {
            $userTimezone = auth()->guard('sanctum')->user()->timezone;
        } elseif ($userTimezone === null) {
            $userTimezone = 'UTC';
        }

        return Carbon::parse($datetime, $userTimezone)->utc();
    }

    /**
     * Convert datetime from UTC to user timezone for display
     *
     * @param string $datetime The UTC datetime to convert
     * @param string $userTimezone The user's timezone
     * @return Carbon The datetime in user timezone
     */
    public function convertFromTimezone(string|Carbon $datetime, ?string $userTimezone = null): Carbon
    {
        if ($userTimezone === null && auth()->guard('sanctum')->check()) {
            $userTimezone = auth()->guard('sanctum')->user()->timezone;
        } elseif ($userTimezone === null) {
            $userTimezone = 'UTC';
        }

        if ($datetime instanceof Carbon) {
            return $datetime->copy()->setTimezone($userTimezone);
        }

        return Carbon::parse($datetime, 'UTC')->setTimezone($userTimezone);
    }
}
