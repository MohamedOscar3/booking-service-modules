<?php

namespace Modules\Booking\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    /**
     * Get total bookings per provider with statistics
     */
    public function getProviderBookingStats(Request $request): Collection
    {

        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as providers', 'services.provider_id', '=', 'providers.id')
            ->select([
                'providers.id as provider_id',
                'providers.name as provider_name',
                'providers.email as provider_email',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw("SUM(CASE WHEN bookings.status IN ('confirmed', 'completed') THEN bookings.price ELSE 0 END) as total_revenue"),
                DB::raw("SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings"),
                DB::raw("AVG(CASE WHEN bookings.status IN ('confirmed', 'completed') THEN bookings.price END) as average_booking_value"),
                DB::raw('COUNT(DISTINCT services.id) as services_offered'),
            ])
            ->whereNull('bookings.deleted_at');

        $this->applyProviderFilters($query, $request);

        return $query->groupBy(['providers.id', 'providers.name', 'providers.email'])
            ->orderBy('total_bookings', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'provider_id' => $item->provider_id,
                    'provider_name' => $item->provider_name,
                    'provider_email' => $item->provider_email,
                    'total_bookings' => (int) $item->total_bookings,
                    'total_revenue' => (float) $item->total_revenue,
                    'pending_bookings' => (int) $item->pending_bookings,
                    'confirmed_bookings' => (int) $item->confirmed_bookings,
                    'completed_bookings' => (int) $item->completed_bookings,
                    'cancelled_bookings' => (int) $item->cancelled_bookings,
                    'average_booking_value' => number_format((float) $item->average_booking_value, 2, '.', ''),
                    'services_offered' => (int) $item->services_offered,
                ];
            });
    }

    /**
     * Get service booking rates (confirmed vs cancelled)
     */
    public function getServiceBookingRates(Request $request): Collection
    {
        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as providers', 'services.provider_id', '=', 'providers.id')
            ->select([
                'services.id as service_id',
                'services.name as service_name',
                'providers.name as provider_name',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw("SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings"),
            ])
            ->whereNull('bookings.deleted_at');

        $this->applyServiceRateFilters($query, $request);

        return $query->groupBy(['services.id', 'services.name', 'providers.name'])
            ->orderBy('total_bookings', 'desc')
            ->get()
            ->map(function ($item) {
                $total = $item->total_bookings;

                return [
                    'service_id' => $item->service_id,
                    'service_name' => $item->service_name,
                    'provider_name' => $item->provider_name,
                    'total_bookings' => (int) $total,
                    'pending_bookings' => (int) $item->pending_bookings,
                    'confirmed_bookings' => (int) $item->confirmed_bookings,
                    'completed_bookings' => (int) $item->completed_bookings,
                    'cancelled_bookings' => (int) $item->cancelled_bookings,
                    'confirmation_rate' => $total > 0 ? number_format(($item->confirmed_bookings / $total) * 100, 2, '.', '') : 0,
                    'cancellation_rate' => $total > 0 ? number_format(($item->cancelled_bookings / $total) * 100, 2, '.', '') : 0,
                    'completion_rate' => $total > 0 ? number_format(($item->completed_bookings / $total) * 100, 2, '.', '') : 0,
                    'pending_rate' => $total > 0 ? number_format(($item->pending_bookings / $total) * 100, 2, '.', '') : 0,
                ];
            });
    }

    /**
     * Get peak hours analysis
     */
    public function getPeakHoursAnalysis(Request $request): array
    {
        $baseQuery = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        $this->applyPeakHoursFilters($baseQuery, $request);

        $groupBy = $request->get('groupby', 'both');
        $results = [];
        $totalBookings = $baseQuery->count();

        if (in_array($groupBy, ['hour', 'both'])) {
            $results['by_hour'] = $this->getHourlyStats(clone $baseQuery, $totalBookings);
        }

        if (in_array($groupBy, ['day', 'both'])) {
            $results['by_day'] = $this->getDailyStats(clone $baseQuery, $totalBookings);
        }

        if ($groupBy === 'both') {
            $results['by_day_and_hour'] = $this->getCombinedStats(clone $baseQuery, $totalBookings);
        }

        return $results;
    }

    /**
     * Get customer booking duration analysis
     */
    public function getCustomerBookingDuration(Request $request): Collection
    {
        $minBookings = $request->get('min_bookings', 1);

        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as customers', 'bookings.user_id', '=', 'customers.id')
            ->select([
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.email as customer_email',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('AVG(services.duration) as average_duration_minutes'),
                DB::raw('SUM(services.duration) as total_duration_minutes'),
                DB::raw('AVG(bookings.price) as average_booking_value'),
                DB::raw('SUM(bookings.price) as total_spent'),
            ])
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        $this->applyCustomerDurationFilters($query, $request);

        $results = $query->groupBy(['customers.id', 'customers.name', 'customers.email'])
            ->havingRaw('COUNT(*) >= ?', [$minBookings])
            ->orderBy('total_bookings', 'desc')
            ->get();

        return $results->map(function ($customer) use ($request) {
            return [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->customer_name,
                'customer_email' => $customer->customer_email,
                'total_bookings' => (int) $customer->total_bookings,
                'average_duration_minutes' => (int) number_format($customer->average_duration_minutes, 0, '.', ''),
                'total_duration_minutes' => (int) $customer->total_duration_minutes,
                'average_booking_value' => number_format((float) $customer->average_booking_value, 2, '.', ''),
                'total_spent' => (float) $customer->total_spent,
                'favorite_service' => $this->getCustomerFavoriteService($customer->customer_id, $request),
                'most_frequent_day' => $this->getCustomerMostFrequentDay($customer->customer_id, $request),
                'most_frequent_hour' => $this->getCustomerMostFrequentHour($customer->customer_id, $request),
            ];
        });
    }

    /**
     * Apply filters for provider booking stats
     */
    protected function applyProviderFilters($query, Request $request): void
    {
        if ($request->has('provider_id') && ! is_null($request->provider_id)) {
            $query->where('providers.id', $request->provider_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->has('service_id') && ! is_null($request->service_id)) {
            $query->where('services.id', $request->service_id);
        }
    }

    /**
     * Apply filters for service rate reports
     */
    protected function applyServiceRateFilters($query, Request $request): void
    {
        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('provider_id')) {
            $query->where('providers.id', $request->provider_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }
    }

    /**
     * Apply filters for peak hours analysis
     */
    protected function applyPeakHoursFilters($query, Request $request): void
    {
        if ($request->has('provider_id')) {
            $query->where('services.provider_id', $request->provider_id);
        }

        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }
    }

    /**
     * Apply filters for customer duration analysis
     */
    protected function applyCustomerDurationFilters($query, Request $request): void
    {
        if ($request->has('provider_id')) {
            $query->where('services.provider_id', $request->provider_id);
        }

        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }
    }

    /**
     * Get hourly statistics
     */
    protected function getHourlyStats($query, int $totalBookings): Collection
    {
        return $query
            ->select([
                DB::raw('HOUR(date) as hour'),
                DB::raw('COUNT(*) as total_bookings'),
            ])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) use ($totalBookings) {
                return [
                    'hour' => (int) $item->hour,
                    'total_bookings' => (int) $item->total_bookings,
                    'percentage' => $totalBookings > 0 ? number_format(($item->total_bookings / $totalBookings) * 100, 1, '.', '') : 0,
                ];
            });
    }

    /**
     * Get daily statistics
     */
    protected function getDailyStats($query, int $totalBookings): Collection
    {
        return $query
            ->select([
                DB::raw('DAYOFWEEK(date) as day_number'),
                DB::raw('DAYNAME(date) as day_name'),
                DB::raw('COUNT(*) as total_bookings'),
            ])
            ->groupBy(['day_number', 'day_name'])
            ->orderBy('day_number')
            ->get()
            ->map(function ($item) use ($totalBookings) {
                return [
                    'day_name' => $item->day_name,
                    'day_number' => (int) $item->day_number,
                    'total_bookings' => (int) $item->total_bookings,
                    'percentage' => $totalBookings > 0 ? number_format(($item->total_bookings / $totalBookings) * 100, 1, '.', '') : 0,
                ];
            });
    }

    /**
     * Get combined day and hour statistics
     */
    protected function getCombinedStats($query, int $totalBookings): Collection
    {
        return $query
            ->select([
                DB::raw('DAYOFWEEK(date) as day_number'),
                DB::raw('DAYNAME(date) as day_name'),
                DB::raw('HOUR(date) as hour'),
                DB::raw('COUNT(*) as total_bookings'),
            ])
            ->groupBy(['day_number', 'day_name', 'hour'])
            ->orderBy('day_number')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) use ($totalBookings) {
                return [
                    'day_name' => $item->day_name,
                    'day_number' => (int) $item->day_number,
                    'hour' => (int) $item->hour,
                    'total_bookings' => (int) $item->total_bookings,
                    'percentage' => $totalBookings > 0 ? number_format(($item->total_bookings / $totalBookings) * 100, 1, '.', '') : 0,
                ];
            });
    }

    /**
     * Get customer's favorite service
     */
    protected function getCustomerFavoriteService(int $customerId, Request $request): string
    {
        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->select(['services.name', DB::raw('COUNT(*) as booking_count')])
            ->where('bookings.user_id', $customerId)
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        if ($request->has('provider_id')) {
            $query->where('services.provider_id', $request->provider_id);
        }

        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }

        $result = $query->groupBy('services.name')
            ->orderBy('booking_count', 'desc')
            ->first();

        return $result->name ?? 'N/A';
    }

    /**
     * Get customer's most frequent booking day
     */
    protected function getCustomerMostFrequentDay(int $customerId, Request $request): string
    {
        $query = DB::table('bookings')
            ->select([
                DB::raw('DAYNAME(date) as day_name'),
                DB::raw('COUNT(*) as frequency'),
            ])
            ->where('bookings.user_id', $customerId)
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }

        $result = $query->groupBy('day_name')
            ->orderBy('frequency', 'desc')
            ->first();

        return $result->day_name ?? 'N/A';
    }

    /**
     * Get customer's most frequent booking hour
     */
    protected function getCustomerMostFrequentHour(int $customerId, Request $request): ?int
    {
        $query = DB::table('bookings')
            ->select([
                DB::raw('HOUR(date) as hour'),
                DB::raw('COUNT(*) as frequency'),
            ])
            ->where('bookings.user_id', $customerId)
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        if ($request->has('date_from')) {
            $query->where('bookings.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.date', '<=', $request->date_to.' 23:59:59');
        }

        $result = $query->groupBy('hour')
            ->orderBy('frequency', 'desc')
            ->first();

        return $result ? (int) $result->hour : null;
    }
}
