<?php

namespace Modules\AvailabilityManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\TimezoneService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Group;
use Modules\AvailabilityManagement\DTOs\CreateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\DTOs\UpdateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\Http\Requests\CreateAvailabilityManagementRequest;
use Modules\AvailabilityManagement\Http\Requests\UpdateAvailabilityManagementRequest;
use Modules\AvailabilityManagement\Http\Resources\AvailabilityManagementResource;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;
use Modules\AvailabilityManagement\Services\AvailabilityManagementService;

/**
 * Availability Management API Controller
 *
 * @group Availability Management
 *
 * @description Manage provider availability through CRUD operations
 */
#[Group('Availability Management')]
class AvailabilityManagementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of availability slots
     *
     * @group Availability Management
     *
     * @queryParam provider_id integer Filter by provider ID. Example: 1
     * @queryParam type string Filter by slot type (recurring|once). Example: "recurring"
     * @queryParam week_day integer Filter by week day (0=Sunday, 6=Saturday). Example: 1
     * @queryParam date string Filter by specific date (YYYY-MM-DD). Example: "2025-09-26"
     * @queryParam status boolean Filter by status. Example: true
     * @queryParam page integer Current page. Example: 1
     *
     * @response 200 scenario="Recurring availability slots" {
     *     "status": true,
     *     "message": "Availability slots retrieved successfully",
     *     "data": [
     *         {
     *             "id": 1,
     *             "provider_id": 1,
     *             "type": "recurring",
     *             "week_day": 1,
     *             "from": "2025-09-26 09:00",
     *             "to": "2025-09-26 17:00",
     *             "status": true,
     *             "created_at": "2025-09-25T08:40:31.000000Z",
     *             "updated_at": "2025-09-25T08:40:31.000000Z",
     *             "provider": {
     *                 "id": 1,
     *                 "name": "John Doe"
     *             }
     *         }
     *     ],
     *     "links": {
     *         "first": "http://localhost/api/availability-management?page=1",
     *         "last": "http://localhost/api/availability-management?page=1",
     *         "prev": null,
     *         "next": null
     *     },
     *     "meta": {
     *         "current_page": 1,
     *         "from": 1,
     *         "last_page": 1,
     *         "per_page": 10,
     *         "to": 1,
     *         "total": 1
     *     }
     * }
     *
     * @response 200 scenario="Once availability slots" {
     *     "status": true,
     *     "message": "Availability slots retrieved successfully",
     *     "data": [
     *         {
     *             "id": 2,
     *             "provider_id": 1,
     *             "type": "once",
     *             "week_day": null,
     *             "from": "2025-09-24 09:00",
     *             "to": "2025-09-24 17:00",
     *             "status": true,
     *             "created_at": "2025-09-25T08:40:31.000000Z",
     *             "updated_at": "2025-09-25T08:40:31.000000Z",
     *             "provider": {
     *                 "id": 1,
     *                 "name": "John Doe"
     *             }
     *         }
     *     ],
     *     "links": {
     *         "first": "http://localhost/api/availability-management?page=1",
     *         "last": "http://localhost/api/availability-management?page=1",
     *         "prev": null,
     *         "next": null
     *     },
     *     "meta": {
     *         "current_page": 1,
     *         "from": 1,
     *         "last_page": 1,
     *         "per_page": 10,
     *         "to": 1,
     *         "total": 1
     *     }
     * }
     *
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function index(
        AvailabilityManagementService $availabilityService,
        ApiResponseService $apiResponseService,
        Request $request
    ): JsonResponse {
        $this->authorize('viewAny', AvailabilityManagement::class);

        $availabilitySlots = $availabilityService->getAllAvailabilitySlots($request);

        return $apiResponseService->pagination(
            'Availability slots retrieved successfully',
            data: $availabilitySlots,
            resource: AvailabilityManagementResource::class
        );
    }

    /**
     * Store a newly created availability slot
     *
     * @group Availability Management
     *
     * @bodyParam type string required The slot type (recurring|once). Example: "recurring"
     * @bodyParam week_day integer required if type is recurring The week day (0=Sunday, 6=Saturday). Example: 1
     * @bodyParam from string required The start time. For recurring: HH:MM format, for once: YYYY-MM-DD HH:MM format. Example: "09:00"
     * @bodyParam to string required The end time. For recurring: HH:MM format, for once: YYYY-MM-DD HH:MM format. Must be after start time. Example: "17:00"
     * @bodyParam status boolean optional The status. Defaults to true. Example: true
     *
     * @response 201 scenario="Recurring slot created" {
     *     "status": true,
     *     "message": "Availability slot created successfully",
     *     "data": {
     *         "id": 1,
     *         "provider_id": 1,
     *         "type": "recurring",
     *         "week_day": 1,
     *         "from": "2025-09-26 09:00",
     *         "to": "2025-09-26 17:00",
     *         "status": true,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         }
     *     }
     * }
     *
     * @response 201 scenario="Once slot created" {
     *     "status": true,
     *     "message": "Availability slot created successfully",
     *     "data": {
     *         "id": 2,
     *         "provider_id": 1,
     *         "type": "once",
     *         "week_day": null,
     *         "from": "2025-09-24 09:00",
     *         "to": "2025-09-24 17:00",
     *         "status": true,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         }
     *     }
     * }
     *
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "type": [
     *             "The slot type is required."
     *         ],
     *         "week_day": [
     *             "The week day field is required when type is recurring."
     *         ]
     *     }
     * }
     *
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function store(
        CreateAvailabilityManagementRequest $request,
        ApiResponseService $apiResponseService,
        AvailabilityManagementService $availabilityService,
        TimezoneService $timezoneService
    ): JsonResponse {
        $this->authorize('create', AvailabilityManagement::class);

        try {
            $validated = $request->validated();

            $validated['from'] = $timezoneService->convertToTimezone($validated['from']);
            $validated['to'] = $timezoneService->convertToTimezone($validated['to']);
            $availabilityDto = new CreateAvailabilityManagementDTO(
                provider_id: auth()->id(),
                type: \Modules\AvailabilityManagement\Enums\SlotType::from($validated['type']),
                week_day: $validated['week_day'],
                from: $validated['from'],
                to: $validated['to'],
                status: $validated['status'] ?? true,
            );

            $availability = $availabilityService->createAvailabilitySlot($availabilityDto);

            return $apiResponseService->created(
                new AvailabilityManagementResource($availability),
                'Availability slot created successfully'
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to create availability slot', 500);
        }
    }

    /**
     * Display the specified availability slot
     *
     * @group Availability Management
     *
     * @urlParam id integer required The availability slot ID. Example: 1
     *
     * @response 200 {
     *     "status": true,
     *     "message": "Availability slot retrieved successfully",
     *     "data": {
     *         "id": 1,
     *         "provider_id": 1,
     *         "type": "recurring",
     *         "week_day": 1,
     *         "from": "2025-09-26 09:00",
     *         "to": "2025-09-26 17:00",
     *         "status": true,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         }
     *     }
     * }
     *
     * @response 404 {
     *     "message": "No query results for model [Modules\\AvailabilityManagement\\Models\\AvailabilityManagement] 1"
     * }
     *
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function show(
        AvailabilityManagement $availabilityManagement,
        ApiResponseService $apiResponseService,
        AvailabilityManagementService $availabilityService
    ): JsonResponse {
        $this->authorize('view', $availabilityManagement);

        $availability = $availabilityService->getAvailabilitySlotById($availabilityManagement);

        return $apiResponseService->successResponse(
            'Availability slot retrieved successfully',
            200,
            new AvailabilityManagementResource($availability)
        );
    }

    /**
     * Update the specified availability slot
     *
     * @group Availability Management
     *
     * @urlParam id integer required The availability slot ID. Example: 1
     * @bodyParam type string optional The slot type (recurring|once). Example: "once"
     * @bodyParam week_day integer optional The week day (0=Sunday, 6=Saturday). Example: 2
     * @bodyParam from string optional The start time. For recurring: HH:MM format, for once: YYYY-MM-DD HH:MM format. Example: "10:00"
     * @bodyParam to string optional The end time. For recurring: HH:MM format, for once: YYYY-MM-DD HH:MM format. Must be after start time. Example: "18:00"
     * @bodyParam status boolean optional The status. Example: false
     * @bodyParam _method string required The HTTP method. Example: PUT
     *
     * @response 200 {
     *     "status": true,
     *     "message": "Availability slot updated successfully",
     *     "data": {
     *         "id": 1,
     *         "provider_id": 1,
     *         "type": "once",
     *         "week_day": 2,
     *         "from": "2025-09-27 10:00",
     *         "to": "2025-09-27 18:00",
     *         "status": false,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T09:00:00.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         }
     *     }
     * }
     *
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "to": [
     *             "The end time must be after the start time."
     *         ]
     *     }
     * }
     *
     * @response 404 {
     *     "message": "No query results for model [Modules\\AvailabilityManagement\\Models\\AvailabilityManagement] 1"
     * }
     *
     * @response 403 {
     *     "message": "This action is unauthorized."
     * }
     *
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function update(
        UpdateAvailabilityManagementRequest $request,
        AvailabilityManagement $availabilityManagement,
        ApiResponseService $apiResponseService,
        AvailabilityManagementService $availabilityService,
        TimezoneService $timezoneService
    ): JsonResponse {
        $this->authorize('update', $availabilityManagement);

        try {
            $validated = $request->validated();
            if (isset($validated['from']) && isset($validated['to'])) {
                $validated['from'] = $timezoneService->convertToTimezone($validated['from']);
                $validated['to'] = $timezoneService->convertToTimezone($validated['to']);
            }

            $availabilityDto = new UpdateAvailabilityManagementDTO(
                provider_id: $validated['provider_id'] ?? null,
                type: isset($validated['type']) ? \Modules\AvailabilityManagement\Enums\SlotType::from($validated['type']) : null,
                week_day: $validated['week_day'] ?? null,
                from: $validated['from'] ?? null,
                to: $validated['to'] ?? null,
                status: $validated['status'] ?? null,
            );

            $updatedAvailability = $availabilityService->updateAvailabilitySlot($availabilityManagement, $availabilityDto);

            return $apiResponseService->successResponse(
                'Availability slot updated successfully',
                200,
                new AvailabilityManagementResource($updatedAvailability)
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to update availability slot', 500);
        }
    }

    /**
     * Remove the specified availability slot
     *
     * @group Availability Management
     *
     * @urlParam id integer required The availability slot ID. Example: 1
     *
     * @bodyParam _method string required The HTTP method. Example: DELETE
     *
     * @response 200 {
     *     "status": true,
     *     "message": "Availability slot deleted successfully",
     *     "data": null
     * }
     *
     * @response 404 {
     *     "message": "No query results for model [Modules\\AvailabilityManagement\\Models\\AvailabilityManagement] 1"
     * }
     *
     * @response 403 {
     *     "message": "This action is unauthorized."
     * }
     *
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function destroy(
        AvailabilityManagement $availabilityManagement,
        ApiResponseService $apiResponseService,
        AvailabilityManagementService $availabilityService
    ): JsonResponse {
        $this->authorize('delete', $availabilityManagement);

        try {
            $availabilityService->deleteAvailabilitySlot($availabilityManagement);

            return $apiResponseService->successResponse(
                'Availability slot deleted successfully',
                200,
                null
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to delete availability slot', null, 500);
        }
    }

    /**
     * Get availability slots by provider
     *
     * @group Availability Management
     *
     * @api {get} /api/availability-management/provider/{providerId} Get availability slots for a specific provider
     *
     * @apiName GetAvailabilitySlotsByProvider
     *
     * @urlParam providerId integer required The provider ID. Example: 1
     * @queryParam type string Filter by slot type (recurring|once). Example: "recurring"
     * @queryParam week_day integer Filter by week day (0=Sunday, 6=Saturday). Example: 1
     * @queryParam from_date string Filter from date (YYYY-MM-DD). Example: "2025-09-25"
     * @queryParam to_date string Filter to date (YYYY-MM-DD). Example: "2025-10-25"
     * @queryParam status boolean Filter by status. Example: true
     * @queryParam page integer Current page. Example: 1
     */
    public function getByProvider(
        int $providerId,
        Request $request,
        AvailabilityManagementService $availabilityService,
        ApiResponseService $apiResponseService
    ): JsonResponse {
        $availabilitySlots = $availabilityService->getAvailabilitySlotsByProvider($providerId, $request);

        return $apiResponseService->pagination(
            'Provider availability slots retrieved successfully',
            data: $availabilitySlots,
            resource: AvailabilityManagementResource::class
        );
    }

    /**
     * Get available slots for a specific date
     *
     * @group Availability Management
     *
     * @api {get} /api/availability-management/available/{date} Get available slots for a specific date
     *
     * @apiName GetAvailableSlotsForDate
     *
     * @urlParam date string required The date (HH:ii). Example: "17:00"
     * @queryParam provider_id integer Filter by provider ID. Example: 1
     * @queryParam page integer Current page. Example: 1
     */
    public function getAvailableForDate(
        string $date,
        Request $request,
        AvailabilityManagementService $availabilityService,
        ApiResponseService $apiResponseService
    ): JsonResponse {
        $providerId = $request->query('provider_id');
        $availableSlots = $availabilityService->getAvailableSlotsForDate($date, $providerId);

        return $apiResponseService->pagination(
            'Available slots retrieved successfully',
            data: $availableSlots,
            resource: AvailabilityManagementResource::class
        );
    }

    /**
     * Get recurring availability for a provider
     *
     * @group Availability Management
     *
     * @api {get} /api/availability-management/provider/{providerId}/recurring Get recurring availability for a provider
     *
     * @apiName GetRecurringAvailability
     *
     * @urlParam providerId integer required The provider ID. Example: 1
     * @queryParam week_day integer Filter by week day (0=Sunday, 6=Saturday). Example: 1
     * @queryParam status boolean Filter by status. Example: true
     * @queryParam page integer Current page. Example: 1
     */
    public function getRecurring(
        int $providerId,
        Request $request,
        AvailabilityManagementService $availabilityService,
        ApiResponseService $apiResponseService
    ): JsonResponse {
        $recurringAvailability = $availabilityService->getRecurringAvailability($providerId, $request);

        return $apiResponseService->pagination(
            'Recurring availability retrieved successfully',
            data: $recurringAvailability,
            resource: AvailabilityManagementResource::class
        );
    }
}
