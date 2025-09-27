<?php

namespace Modules\Service\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Group;
use Modules\AvailabilityManagement\Services\SlotService;
use Modules\Service\DTOs\CreateServiceDto;
use Modules\Service\DTOs\UpdateServiceDto;
use Modules\Service\Http\Requests\CreateServiceRequest;
use Modules\Service\Http\Requests\UpdateServiceRequest;
use Modules\Service\Http\Resources\ServiceResource;
use Modules\Service\Models\Service;
use Modules\Service\Services\ServiceService;

/**
 * Service API Controller
 *
 * @group Services
 *
 * @description Manage services through CRUD operations
 */
#[Group('Services')]
class ServiceController extends Controller
{
    /**
     * Display a listing of services
     *
     * @group Services
     *
     * @api {get} /api/services Get all services
     *
     * @apiName GetServices
     *
     * @queryParam q string Filter by service name or description. Example: "cleaning"
     * @queryParam provider_id integer Filter by provider ID. Example: 1
     * @queryParam category_id integer Filter by category ID. Example: 1
     * @queryParam status boolean Filter by status. Example: true
     * @queryParam page integer Current page. Example: 1
     *
     * @response 200 {
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "House Cleaning",
     *             "description": "Professional house cleaning service",
     *             "duration": 120,
     *             "price": "50.00",
     *             "provider_id": 1,
     *             "category_id": 1,
     *             "status": true,
     *             "created_at": "2025-09-25T08:40:31.000000Z",
     *             "updated_at": "2025-09-25T08:40:31.000000Z",
     *             "provider": {
     *                 "id": 1,
     *                 "name": "John Doe"
     *             },
     *             "category": {
     *                 "id": 1,
     *                 "name": "Cleaning"
     *             }
     *         }
     *     ],
     *     "message": "Services retrieved successfully"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function index(
        ServiceService $serviceService,
        ApiResponseService $apiResponseService,
        Request $request
    ): JsonResponse {
        $services = $serviceService->getAllServices($request);

        return $apiResponseService->pagination(
            'Services retrieved successfully',
            data: $services,
            resource: ServiceResource::class
        );
    }

    /**
     * Store a newly created service
     *
     * @group Services
     *
     * @api {post} /api/services Create a new service
     *
     * @apiName CreateService
     *
     * @bodyParam name string required The service name. Must not exceed 255 characters. Example: House Cleaning
     * @bodyParam description string required The service description. Example: Professional house cleaning service
     * @bodyParam duration integer required The service duration in minutes. Must be at least 1. Example: 120
     * @bodyParam price numeric required The service price. Must be at least 0. Example: 50.00
     * @bodyParam category_id integer required The category ID. Must exist in categories table. Example: 1
     * @bodyParam status boolean optional The service status. Defaults to true. Example: true
     *
     * @response 201 {
     *     "data": {
     *         "id": 1,
     *         "name": "House Cleaning",
     *         "description": "Professional house cleaning service",
     *         "duration": 120,
     *         "price": "50.00",
     *         "provider_id": 1,
     *         "category_id": 1,
     *         "status": true,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         },
     *         "category": {
     *             "id": 1,
     *             "name": "Cleaning"
     *         }
     *     },
     *     "message": "Service created successfully"
     * }
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": [
     *             "The service name is required."
     *         ]
     *     }
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function store(
        CreateServiceRequest $request,
        ApiResponseService $apiResponseService,
        ServiceService $serviceService
    ): JsonResponse {
        try {
            $serviceDto = new CreateServiceDto(
                name: $request->validated()['name'],
                description: $request->validated()['description'],
                duration: $request->validated()['duration'],
                price: $request->validated()['price'],
                provider_id: auth()->id(),
                category_id: $request->validated()['category_id'],
                status: $request->validated()['status'] ?? true
            );

            $service = $serviceService->createService($serviceDto);

            return $apiResponseService->created(
                new ServiceResource($service),
                'Service created successfully'
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to create service', 500);
        }
    }

    /**
     * Display the specified service
     *
     * @group Services
     *
     * @api {get} /api/services/{id} Get a specific service
     *
     * @apiName GetService
     *
     * @urlParam id integer required The service ID. Example: 1
     *
     * @response 200 {
     *     "data": {
     *         "id": 1,
     *         "name": "House Cleaning",
     *         "description": "Professional house cleaning service",
     *         "duration": 120,
     *         "price": "50.00",
     *         "provider_id": 1,
     *         "category_id": 1,
     *         "status": true,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T08:40:31.000000Z",
     *         "provider": {
     *             "id": 1,
     *             "name": "John Doe"
     *         },
     *         "category": {
     *             "id": 1,
     *             "name": "Cleaning"
     *         }
     *     },
     *     "message": "Service retrieved successfully"
     * }
     * @response 404 {
     *     "message": "Service not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function show(
        Service $service,
        ApiResponseService $apiResponseService,
        ServiceService $serviceService,
        Request $request
    ): JsonResponse {
        $service = $serviceService->getServiceById($service);
        $availabilityService = app(SlotService::class);

        $slots = $availabilityService->getAvailableSlots($service->id, auth()->user()->timezone);

        $request->merge(['slots' => $slots]);

        return $apiResponseService->successResponse(
            'Service retrieved successfully',
            200,
            (new ServiceResource($service))->toArray($request)
        );
    }

    /**
     * Update the specified service
     *
     * @group Services
     *
     * @api {put} /api/services/{id} Update a service
     *
     * @apiName UpdateService
     *
     * @urlParam id integer required The service ID. Example: 1
     * @bodyParam name string optional The service name. Must not exceed 255 characters. Example: Updated House Cleaning
     * @bodyParam description string optional The service description. Example: Updated professional house cleaning service
     * @bodyParam duration integer optional The service duration in minutes. Must be at least 1. Example: 180
     * @bodyParam price numeric optional The service price. Must be at least 0. Example: 75.00
     * @bodyParam category_id integer optional The category ID. Must exist in categories table. Example: 2
     * @bodyParam status boolean optional The service status. Example: false
     * @bodyParam _method string required The HTTP method. Example: PUT
     *
     * @response 200 {
     *     "data": {
     *         "id": 1,
     *         "name": "Updated House Cleaning",
     *         "description": "Updated professional house cleaning service",
     *         "duration": 180,
     *         "price": "75.00",
     *         "provider_id": 2,
     *         "category_id": 2,
     *         "status": false,
     *         "created_at": "2025-09-25T08:40:31.000000Z",
     *         "updated_at": "2025-09-25T09:00:00.000000Z",
     *         "provider": {
     *             "id": 2,
     *             "name": "Jane Smith"
     *         },
     *         "category": {
     *             "id": 2,
     *             "name": "Maintenance"
     *         }
     *     },
     *     "message": "Service updated successfully"
     * }
     * @response 422 {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "name": [
     *             "The service name must not exceed 255 characters."
     *         ]
     *     }
     * }
     * @response 404 {
     *     "message": "Service not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function update(
        UpdateServiceRequest $request,
        Service $service,
        ApiResponseService $apiResponseService,
        ServiceService $serviceService
    ): JsonResponse {
        try {
            $validated = $request->validated();

            $serviceDto = new UpdateServiceDto(
                name: $validated['name'] ?? null,
                description: $validated['description'] ?? null,
                duration: $validated['duration'] ?? null,
                price: $validated['price'] ?? null,
                category_id: $validated['category_id'] ?? null,
                status: $validated['status'] ?? null
            );

            $updatedService = $serviceService->updateService($service, $serviceDto);

            return $apiResponseService->successResponse(
                'Service updated successfully',
                200,
                new ServiceResource($updatedService)
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to update service', 500);
        }
    }

    /**
     * Remove the specified service
     *
     * @group Services
     *
     * @api {delete} /api/services/{id} Delete a service
     *
     * @apiName DeleteService
     *
     * @urlParam id integer required The service ID. Example: 1
     *
     * @bodyParam _method string required The HTTP method. Example: DELETE
     *
     * @response 200 {
     *     "data": null,
     *     "message": "Service deleted successfully"
     * }
     * @response 404 {
     *     "message": "Service not found"
     * }
     * @response 401 {
     *     "message": "Unauthenticated."
     * }
     */
    public function destroy(
        Service $service,
        ApiResponseService $apiResponseService,
        ServiceService $serviceService
    ): JsonResponse {
        try {
            $serviceService->deleteService($service);

            return $apiResponseService->successResponse(
                'Service deleted successfully',
                200,
                null
            );
        } catch (\Exception $e) {
            return $apiResponseService->failedResponse('Failed to delete service', null, 500);
        }
    }
}
