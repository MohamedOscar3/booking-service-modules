<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Middleware
 * @tag Timezone
 * @description Add timezone to response
 */
class TimezoneResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $renderTimezone = $request->attributes->get('render_timezone') ?? session('timezone');

        if ($response instanceof JsonResponse && $renderTimezone) {
            $content = $response->getData(true);
            if (isset($content['data']) && is_array($content['data'])) {
                $content['data'] = $this->convertDatesToTimezone($content['data'], $renderTimezone);
                $response->setData($content);
            }
        }

        return $response;
    }

    /**
     * Recursively convert date fields to the specified timezone
     */
    protected function convertDatesToTimezone(array $data, string $timezone): array
    {
        foreach ($data as $key => $value) {
            // Check for common date/time field names
            $dateFields = ['created_at', 'updated_at', 'deleted_at', 'date', 'datetime', 'time',
                'start_date', 'end_date', 'start_time', 'end_time'];

            if (is_array($value)) {
                $data[$key] = $this->convertDatesToTimezone($value, $timezone);

            } elseif (is_string($value) && $this->isDateField($key, $dateFields) && $this->isValidDate($value)) {
                try {
                    // Parse the date, adjust for timezone, and return in standard Laravel format
                    $date = Carbon::parse($value);

                    $data[$key] = $date->setTimezone($timezone)->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // If parsing fails, leave the original value
                }
            }
        }

        return $data;
    }

    /**
     * Check if the field name is a date field
     */
    protected function isDateField(string $fieldName, array $dateFields): bool
    {
        // Check if field name is in the list of date fields
        if (in_array($fieldName, $dateFields)) {
            return true;
        }

        // Check if field name ends with _at or _date
        if (preg_match('/_at$|_date$|_time$/', $fieldName)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a string is a valid date
     */
    protected function isValidDate(string $dateString): bool
    {
        try {
            // Try to parse the date string
            $date = Carbon::parse($dateString);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
