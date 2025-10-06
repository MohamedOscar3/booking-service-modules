<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Enums\Roles;
use Modules\Booking\Jobs\ProcessExcelExportJob;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\AdminReportService;

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
        protected ApiResponseService $apiResponse,
        protected AdminReportService $reportService
    ) {}

    /**
     * Check if the current user is an admin
     */
    protected function isAdmin(): bool
    {
        return auth()->user()->role === Roles::ADMIN;
    }

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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
        ]);

        $results = $this->reportService->getProviderBookingStats($request);

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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $results = $this->reportService->getServiceBookingRates($request);

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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'groupby' => ['sometimes', 'string', 'in:hour,day,both'],
        ]);

        $results = $this->reportService->getPeakHoursAnalysis($request);

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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_bookings' => ['sometimes', 'integer', 'min:1'],
        ]);

        $results = $this->reportService->getCustomerBookingDuration($request);

        return $this->apiResponse->successResponse(
            'Customer booking duration analysis retrieved successfully',
            200,
            $results
        );
    }

    /**
     * Queue provider bookings export
     *
     * Queue an Excel export for provider booking statistics.
     * The export will be processed in the background and an email with download link will be sent when complete.
     *
     * @authenticated
     *
     * @bodyParam provider_id integer Filter by specific provider ID. Example: 3
     * @bodyParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @bodyParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @bodyParam service_id integer Filter by specific service ID. Example: 5
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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable',  'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'service_id' => ['sometimes', 'nullable', 'exists:services,id'],
        ]);

        ProcessExcelExportJob::dispatch(auth()->user(), 'provider-bookings', $request->all());

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
     * @bodyParam service_id integer Filter by specific service ID. Example: 5
     * @bodyParam provider_id integer Filter by specific provider ID. Example: 3
     * @bodyParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @bodyParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        ProcessExcelExportJob::dispatch(auth()->user(), 'service-booking-rates', $request->all());

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
     * @bodyParam provider_id integer Filter by specific provider ID. Example: 3
     * @bodyParam service_id integer Filter by specific service ID. Example: 5
     * @bodyParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @bodyParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @bodyParam groupby string Group results by 'hour', 'day', or 'both'. Example: both
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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'groupby' => ['sometimes', 'string', 'in:hour,day,both'],
        ]);

        ProcessExcelExportJob::dispatch(auth()->user(), 'peak-hours', $request->all());

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
     * @bodyParam provider_id integer Filter by specific provider ID. Example: 3
     * @bodyParam service_id integer Filter by specific service ID. Example: 5
     * @bodyParam date_from date Filter bookings from this date (YYYY-MM-DD). Example: 2024-01-01
     * @bodyParam date_to date Filter bookings to this date (YYYY-MM-DD). Example: 2024-12-31
     * @bodyParam min_bookings integer Minimum number of bookings required to include customer. Example: 2
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

        if (! $this->isAdmin()) {
            return $this->apiResponse->unauthorized('Admin access required for reports');
        }

        $request->validate([
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_bookings' => ['sometimes', 'integer', 'min:1'],
        ]);

        ProcessExcelExportJob::dispatch(auth()->user(), 'customer-booking-duration', $request->all());

        return $this->apiResponse->successResponse(
            'Export has been queued and will be emailed to you when complete'
        );
    }
}
