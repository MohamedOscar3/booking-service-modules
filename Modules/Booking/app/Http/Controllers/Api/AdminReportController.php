<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Auth\Enums\Roles;
use Modules\Booking\Exports\CustomerBookingDurationExport;
use Modules\Booking\Exports\PeakHoursExport;
use Modules\Booking\Exports\ProviderBookingsExport;
use Modules\Booking\Exports\ServiceBookingRatesExport;
use Modules\Booking\Jobs\ProcessExcelExportJob;
use Modules\Booking\Models\Booking;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Admin Reports
 *
 * Administrative reporting endpoints for dashboard analytics.
 * All endpoints require admin authentication and provide comprehensive
 * booking analytics with filtering capabilities.
 */
class AdminReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    /**
     * Total bookings per provider
     *
     * Get comprehensive booking statistics grouped by provider.
     * Includes total bookings, revenue, and status breakdown for each provider.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Provider booking statistics retrieved successfully",
     *   "data": [
     *     {
     *       "provider_id": 3,
     *       "provider_name": "Jane Smith",
     *       "provider_email": "[email protected]",
     *       "total_bookings": 145,
     *       "total_revenue": 3625.00,
     *       "pending_bookings": 12,
     *       "confirmed_bookings": 98,
     *       "completed_bookings": 28,
     *       "cancelled_bookings": 7,
     *       "average_booking_value": 25.00,
     *       "services_offered": 4
     *     },
     *     {
     *       "provider_id": 5,
     *       "provider_name": "Mike Johnson",
     *       "provider_email": "[email protected]",
     *       "total_bookings": 89,
     *       "total_revenue": 2670.00,
     *       "pending_bookings": 8,
     *       "confirmed_bookings": 65,
     *       "completed_bookings": 14,
     *       "cancelled_bookings": 2,
     *       "average_booking_value": 30.00,
     *       "services_offered": 2
     *     }
     *   ]
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function totalBookingsPerProvider(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ]);

        $query = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->join('users as providers', 'services.provider_id', '=', 'providers.id')
            ->select([
                'providers.id as provider_id',
                'providers.name as provider_name',
                'providers.email as provider_email',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(bookings.price) as total_revenue'),
                DB::raw("SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings"),
                DB::raw("SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings"),
                DB::raw('AVG(bookings.price) as average_booking_value'),
                DB::raw('COUNT(DISTINCT services.id) as services_offered'),
            ])
            ->whereNull('bookings.deleted_at');

        // Apply filters
        if ($request->has('provider_id')) {
            $query->where('providers.id', $request->provider_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        $results = $query->groupBy(['providers.id', 'providers.name', 'providers.email'])
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
                    'average_booking_value' => round((float) $item->average_booking_value, 2),
                    'services_offered' => (int) $item->services_offered,
                ];
            });

        return $this->apiResponse->successResponse(
            'Provider booking statistics retrieved successfully',
            200,
            $results
        );
    }

    /**
     * Cancelled vs confirmed rate per service
     *
     * Get booking status analytics grouped by service.
     * Shows conversion rates and cancellation patterns for each service.
     *
     * @authenticated
     *
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Service booking rates retrieved successfully",
     *   "data": [
     *     {
     *       "service_id": 5,
     *       "service_name": "Hair Cut",
     *       "provider_name": "Jane Smith",
     *       "total_bookings": 120,
     *       "pending_bookings": 15,
     *       "confirmed_bookings": 85,
     *       "completed_bookings": 12,
     *       "cancelled_bookings": 8,
     *       "confirmation_rate": 70.83,
     *       "cancellation_rate": 6.67,
     *       "completion_rate": 10.00,
     *       "pending_rate": 12.50
     *     },
     *     {
     *       "service_id": 8,
     *       "service_name": "Massage Therapy",
     *       "provider_name": "Mike Johnson",
     *       "total_bookings": 89,
     *       "pending_bookings": 5,
     *       "confirmed_bookings": 70,
     *       "completed_bookings": 12,
     *       "cancelled_bookings": 2,
     *       "confirmation_rate": 78.65,
     *       "cancellation_rate": 2.25,
     *       "completion_rate": 13.48,
     *       "pending_rate": 5.62
     *     }
     *   ]
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function cancelledVsConfirmedRatePerService(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

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

        // Apply filters
        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('provider_id')) {
            $query->where('providers.id', $request->provider_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
        }

        $results = $query->groupBy(['services.id', 'services.name', 'providers.name'])
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
                    'confirmation_rate' => $total > 0 ? round(($item->confirmed_bookings / $total) * 100, 2) : 0,
                    'cancellation_rate' => $total > 0 ? round(($item->cancelled_bookings / $total) * 100, 2) : 0,
                    'completion_rate' => $total > 0 ? round(($item->completed_bookings / $total) * 100, 2) : 0,
                    'pending_rate' => $total > 0 ? round(($item->pending_bookings / $total) * 100, 2) : 0,
                ];
            });

        return $this->apiResponse->successResponse(
            'Service booking rates retrieved successfully',
            200,
            $results
        );
    }

    /**
     * Peak hours by day/week
     *
     * Analyze booking patterns to identify peak hours by day of week.
     * Helps optimize provider schedules and resource allocation.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam groupby string Group results by 'hour', 'day', or 'both'. Example: both
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Peak hours analysis retrieved successfully",
     *   "data": {
     *     "by_hour": [
     *       {
     *         "hour": 9,
     *         "total_bookings": 45,
     *         "percentage": 8.2
     *       },
     *       {
     *         "hour": 10,
     *         "total_bookings": 67,
     *         "percentage": 12.1
     *       },
     *       {
     *         "hour": 14,
     *         "total_bookings": 89,
     *         "percentage": 16.1
     *       }
     *     ],
     *     "by_day": [
     *       {
     *         "day_name": "Monday",
     *         "day_number": 1,
     *         "total_bookings": 78,
     *         "percentage": 14.2
     *       },
     *       {
     *         "day_name": "Saturday",
     *         "day_number": 6,
     *         "total_bookings": 124,
     *         "percentage": 22.5
     *       }
     *     ],
     *     "by_day_and_hour": [
     *       {
     *         "day_name": "Saturday",
     *         "day_number": 6,
     *         "hour": 14,
     *         "total_bookings": 25,
     *         "percentage": 4.5
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function peakHoursByDayWeek(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'groupby' => ['sometimes', 'string', 'in:hour,day,both'],
        ]);

        $baseQuery = DB::table('bookings')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed', 'completed']);

        // Apply filters to base query
        if ($request->has('provider_id')) {
            $baseQuery->where('services.provider_id', $request->provider_id);
        }

        if ($request->has('service_id')) {
            $baseQuery->where('services.id', $request->service_id);
        }

        if ($request->has('date_from')) {
            $baseQuery->where('bookings.scheduled_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $baseQuery->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
        }

        $groupBy = $request->get('groupby', 'both');
        $results = [];

        // Get total bookings for percentage calculations
        $totalBookings = $baseQuery->count();

        if (in_array($groupBy, ['hour', 'both'])) {
            $hourlyData = clone $baseQuery;
            $hourlyStats = $hourlyData
                ->select([
                    DB::raw('HOUR(scheduled_at) as hour'),
                    DB::raw('COUNT(*) as total_bookings'),
                ])
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->map(function ($item) use ($totalBookings) {
                    return [
                        'hour' => (int) $item->hour,
                        'total_bookings' => (int) $item->total_bookings,
                        'percentage' => $totalBookings > 0 ? round(($item->total_bookings / $totalBookings) * 100, 1) : 0,
                    ];
                });

            $results['by_hour'] = $hourlyStats;
        }

        if (in_array($groupBy, ['day', 'both'])) {
            $dailyData = clone $baseQuery;
            $dailyStats = $dailyData
                ->select([
                    DB::raw('DAYOFWEEK(scheduled_at) as day_number'),
                    DB::raw('DAYNAME(scheduled_at) as day_name'),
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
                        'percentage' => $totalBookings > 0 ? round(($item->total_bookings / $totalBookings) * 100, 1) : 0,
                    ];
                });

            $results['by_day'] = $dailyStats;
        }

        if ($groupBy === 'both') {
            $combinedData = clone $baseQuery;
            $combinedStats = $combinedData
                ->select([
                    DB::raw('DAYOFWEEK(scheduled_at) as day_number'),
                    DB::raw('DAYNAME(scheduled_at) as day_name'),
                    DB::raw('HOUR(scheduled_at) as hour'),
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
                        'percentage' => $totalBookings > 0 ? round(($item->total_bookings / $totalBookings) * 100, 1) : 0,
                    ];
                });

            $results['by_day_and_hour'] = $combinedStats;
        }

        return $this->apiResponse->successResponse(
            'Peak hours analysis retrieved successfully',
            200,
            $results
        );
    }

    /**
     * Average booking duration per customer
     *
     * Calculate average service duration and booking patterns per customer.
     * Helps identify customer preferences and service optimization opportunities.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam min_bookings integer Minimum number of bookings required to include customer. Example: 2
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Customer booking duration analysis retrieved successfully",
     *   "data": [
     *     {
     *       "customer_id": 10,
     *       "customer_name": "John Doe",
     *       "customer_email": "[email protected]",
     *       "total_bookings": 8,
     *       "average_duration_minutes": 75,
     *       "total_duration_minutes": 600,
     *       "average_booking_value": 32.50,
     *       "total_spent": 260.00,
     *       "favorite_service": "Deep Tissue Massage",
     *       "most_frequent_day": "Saturday",
     *       "most_frequent_hour": 14
     *     },
     *     {
     *       "customer_id": 15,
     *       "customer_name": "Alice Johnson",
     *       "customer_email": "[email protected]",
     *       "total_bookings": 5,
     *       "average_duration_minutes": 45,
     *       "total_duration_minutes": 225,
     *       "average_booking_value": 28.00,
     *       "total_spent": 140.00,
     *       "favorite_service": "Hair Cut & Style",
     *       "most_frequent_day": "Friday",
     *       "most_frequent_hour": 16
     *     }
     *   ]
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function averageBookingDurationPerCustomer(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_bookings' => ['sometimes', 'integer', 'min:1'],
        ]);

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

        // Apply filters
        if ($request->has('provider_id')) {
            $query->where('services.provider_id', $request->provider_id);
        }

        if ($request->has('service_id')) {
            $query->where('services.id', $request->service_id);
        }

        if ($request->has('date_from')) {
            $query->where('bookings.scheduled_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
        }

        $results = $query->groupBy(['customers.id', 'customers.name', 'customers.email'])
            ->havingRaw('COUNT(*) >= ?', [$minBookings])
            ->orderBy('total_bookings', 'desc')
            ->get();

        // Get additional details for each customer
        $enrichedResults = $results->map(function ($customer) use ($request) {
            // Get favorite service
            $favoriteServiceQuery = DB::table('bookings')
                ->join('services', 'bookings.service_id', '=', 'services.id')
                ->select(['services.name', DB::raw('COUNT(*) as booking_count')])
                ->where('bookings.user_id', $customer->customer_id)
                ->whereNull('bookings.deleted_at')
                ->whereIn('bookings.status', ['confirmed', 'completed']);

            // Apply same filters
            if ($request->has('provider_id')) {
                $favoriteServiceQuery->where('services.provider_id', $request->provider_id);
            }
            if ($request->has('service_id')) {
                $favoriteServiceQuery->where('services.id', $request->service_id);
            }
            if ($request->has('date_from')) {
                $favoriteServiceQuery->where('bookings.scheduled_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $favoriteServiceQuery->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
            }

            $favoriteService = $favoriteServiceQuery->groupBy('services.name')
                ->orderBy('booking_count', 'desc')
                ->first();

            // Get most frequent day and hour
            $frequencyQuery = DB::table('bookings')
                ->select([
                    DB::raw('DAYNAME(scheduled_at) as day_name'),
                    DB::raw('HOUR(scheduled_at) as hour'),
                    DB::raw('COUNT(*) as frequency'),
                ])
                ->where('bookings.user_id', $customer->customer_id)
                ->whereNull('bookings.deleted_at')
                ->whereIn('bookings.status', ['confirmed', 'completed']);

            // Apply same filters
            if ($request->has('date_from')) {
                $frequencyQuery->where('bookings.scheduled_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $frequencyQuery->where('bookings.scheduled_at', '<=', $request->date_to.' 23:59:59');
            }

            $mostFrequentDay = (clone $frequencyQuery)
                ->groupBy('day_name')
                ->orderBy('frequency', 'desc')
                ->first();

            $mostFrequentHour = (clone $frequencyQuery)
                ->groupBy('hour')
                ->orderBy('frequency', 'desc')
                ->first();

            return [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->customer_name,
                'customer_email' => $customer->customer_email,
                'total_bookings' => (int) $customer->total_bookings,
                'average_duration_minutes' => (int) round($customer->average_duration_minutes),
                'total_duration_minutes' => (int) $customer->total_duration_minutes,
                'average_booking_value' => round((float) $customer->average_booking_value, 2),
                'total_spent' => (float) $customer->total_spent,
                'favorite_service' => $favoriteService->name ?? 'N/A',
                'most_frequent_day' => $mostFrequentDay->day_name ?? 'N/A',
                'most_frequent_hour' => $mostFrequentHour ? (int) $mostFrequentHour->hour : null,
            ];
        });

        return $this->apiResponse->successResponse(
            'Customer booking duration analysis retrieved successfully',
            200,
            $enrichedResults
        );
    }

    /**
     * Export total bookings per provider to Excel
     *
     * Download comprehensive provider booking statistics as an Excel file.
     * Includes all the same data as the regular report endpoint with filtering support.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     *
     * @response 200 binary/application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function exportTotalBookingsPerProvider(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            abort(403, 'Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ]);

        $filename = 'provider-bookings-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new ProviderBookingsExport($request), $filename);
    }

    /**
     * Export cancelled vs confirmed rate per service to Excel
     *
     * Download service booking rate analysis as an Excel file.
     * Shows conversion rates and cancellation patterns for each service.
     *
     * @authenticated
     *
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     *
     * @response 200 binary/application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function exportCancelledVsConfirmedRatePerService(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            abort(403, 'Admin access required for reports');
        }

        $request->validate([
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $filename = 'service-booking-rates-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new ServiceBookingRatesExport($request), $filename);
    }

    /**
     * Export peak hours analysis to Excel
     *
     * Download booking pattern analysis as an Excel file with multiple sheets.
     * Helps optimize provider schedules and resource allocation.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam groupby string Group results by 'hour', 'day', or 'both'. Example: both
     *
     * @response 200 binary/application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function exportPeakHoursByDayWeek(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            abort(403, 'Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'groupby' => ['sometimes', 'string', 'in:hour,day,both'],
        ]);

        $filename = 'peak-hours-analysis-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new PeakHoursExport($request), $filename);
    }

    /**
     * Export average booking duration per customer to Excel
     *
     * Download customer booking analysis as an Excel file.
     * Shows average service duration and booking patterns per customer.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam min_bookings integer Minimum number of bookings required to include customer. Example: 2
     *
     * @response 200 binary/application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function exportAverageBookingDurationPerCustomer(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            abort(403, 'Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_bookings' => ['sometimes', 'integer', 'min:1'],
        ]);

        $filename = 'customer-booking-duration-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new CustomerBookingDurationExport($request), $filename);
    }

    /**
     * Queue provider bookings export
     *
     * Queue an Excel export for provider booking statistics.
     * The export will be processed in the background and an email with download link will be sent when complete.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Export has been queued and will be emailed to you when complete"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function queueProviderBookingsExport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ]);

        ProcessExcelExportJob::dispatch($user, 'provider-bookings', $request->all());

        return $this->apiResponse->successResponse(
            'Export has been queued and will be emailed to you when complete'
        );
    }

    /**
     * Queue service booking rates export
     *
     * Queue an Excel export for service booking rate analysis.
     * The export will be processed in the background and an email with download link will be sent when complete.
     *
     * @authenticated
     *
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Export has been queued and will be emailed to you when complete"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function queueServiceBookingRatesExport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        ProcessExcelExportJob::dispatch($user, 'service-booking-rates', $request->all());

        return $this->apiResponse->successResponse(
            'Export has been queued and will be emailed to you when complete'
        );
    }

    /**
     * Queue peak hours export
     *
     * Queue an Excel export for peak hours analysis.
     * The export will be processed in the background and an email with download link will be sent when complete.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam groupby string Group results by 'hour', 'day', or 'both'. Example: both
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Export has been queued and will be emailed to you when complete"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function queuePeakHoursExport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'groupby' => ['sometimes', 'string', 'in:hour,day,both'],
        ]);

        ProcessExcelExportJob::dispatch($user, 'peak-hours', $request->all());

        return $this->apiResponse->successResponse(
            'Export has been queued and will be emailed to you when complete'
        );
    }

    /**
     * Queue customer booking duration export
     *
     * Queue an Excel export for customer booking duration analysis.
     * The export will be processed in the background and an email with download link will be sent when complete.
     *
     * @authenticated
     *
     * @queryParam provider_id integer Filter by specific provider ID. Example: 3
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam min_bookings integer Minimum number of bookings required to include customer. Example: 2
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Export has been queued and will be emailed to you when complete"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function queueCustomerBookingDurationExport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $user = auth()->user();
        if ($user->role !== Roles::ADMIN) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_bookings' => ['sometimes', 'integer', 'min:1'],
        ]);

        ProcessExcelExportJob::dispatch($user, 'customer-booking-duration', $request->all());

        return $this->apiResponse->successResponse(
            'Export has been queued and will be emailed to you when complete'
        );
    }
}
