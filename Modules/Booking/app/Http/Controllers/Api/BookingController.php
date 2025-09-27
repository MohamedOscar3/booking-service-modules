<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\DTOs\CreateBookingDto;
use Modules\Booking\DTOs\UpdateBookingDto;
use Modules\Booking\Http\Requests\CreateBookingRequest;
use Modules\Booking\Http\Requests\UpdateBookingRequest;
use Modules\Booking\Http\Resources\BookingResource;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;

/**
 * @group Booking Management
 *
 * APIs for managing bookings including creation, updates, status changes, and availability checking.
 * All endpoints require authentication and proper authorization based on user roles.
 */
class BookingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BookingService $bookingService,
        protected ApiResponseService $apiResponse
    ) {}

    /**
     * Get paginated bookings with filtering
     *
     * Retrieve a paginated list of bookings with optional filtering capabilities.
     * Users can only see their own bookings, providers see their service bookings, and admins see all.
     *
     * @authenticated
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page (max 100). Example: 15
     * @queryParam service_id integer Filter by specific service ID. Example: 5
     * @queryParam status string Filter by booking status. Example: confirmed
     * @queryParam provider_id integer Filter by provider ID (admin only). Example: 3
     * @queryParam user_id integer Filter by customer ID (admin only). Example: 10
     * @queryParam date_from date Filter bookings from this date. Example: 2024-01-01
     * @queryParam date_to date Filter bookings until this date. Example: 2024-12-31
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Bookings retrieved successfully",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "service": {
     *           "id": 5,
     *           "name": "Hair Cut",
     *           "duration": 60
     *         },
     *         "user": {
     *           "id": 10,
     *           "name": "John Doe",
     *           "email": "[email protected]"
     *         },
     *         "status": "confirmed",
     *         "scheduled_at": "2024-02-15T10:00:00Z",
     *         "price": 25.00,
     *         "customer_notes": "Please call 30 minutes before",
     *         "provider_notes": null,
     *         "created_at": "2024-02-10T08:30:00Z",
     *         "updated_at": "2024-02-12T14:20:00Z"
     *       }
     *     ],
     *     "total": 45,
     *     "per_page": 15,
     *     "last_page": 3
     *   }
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = $this->bookingService->getAllBookings($request);

        return $this->apiResponse->pagination(
            'Bookings retrieved successfully',
            $bookings,
            BookingResource::class
        );
    }

    /**
     * Create a new booking
     *
     * Create a new booking with comprehensive validation and business rule checks.
     * Validates availability, time constraints, and user permissions.
     *
     * @authenticated
     *
     * @bodyParam service_id integer required The ID of the service to book. Example: 5
     * @bodyParam scheduled_at string required The date and time for the booking (ISO 8601 format). Example: 2024-02-15T10:00:00Z
     * @bodyParam timezone string required The timezone for the booking. Example: America/New_York
     * @bodyParam customer_notes string optional Notes from the customer. Example: Please call 30 minutes before arrival
     * @bodyParam price numeric optional Custom price (admin only). Example: 35.00
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Booking created successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "duration": 60,
     *       "price": 25.00
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "pending",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 25.00,
     *     "customer_notes": "Please call 30 minutes before arrival",
     *     "provider_notes": null,
     *     "created_at": "2024-02-10T08:30:00Z",
     *     "updated_at": "2024-02-10T08:30:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid",
     *   "errors": {
     *     "scheduled_at": ["The selected time slot is not available"]
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $bookingDto = CreateBookingDto::from($request->getBookingData());
        $booking = $this->bookingService->createBooking($bookingDto);

        return $this->apiResponse->created(
            new BookingResource($booking),
            'Booking created successfully'
        );
    }

    /**
     * Get a specific booking
     *
     * Retrieve detailed information about a specific booking.
     * Users can only view their own bookings, providers can view bookings for their services,
     * and admins can view any booking.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking retrieved successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "description": "Professional hair cutting service",
     *       "duration": 60,
     *       "price": 25.00,
     *       "provider": {
     *         "id": 3,
     *         "name": "Jane Smith",
     *         "email": "[email protected]"
     *       }
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "confirmed",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 25.00,
     *     "customer_notes": "Please call 30 minutes before arrival",
     *     "provider_notes": "Customer is a regular, knows the drill",
     *     "created_at": "2024-02-10T08:30:00Z",
     *     "updated_at": "2024-02-12T14:20:00Z"
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Booking not found"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking = $this->bookingService->getBookingById($booking);

        return $this->apiResponse->successResponse(
            'Booking retrieved successfully',
            200,
            new BookingResource($booking)
        );
    }

    /**
     * Update a booking
     *
     * Update booking details with role-based field restrictions.
     * Customers can update notes, providers can update status and notes,
     * admins can update any field including price.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking to update. Example: 15
     *
     * @bodyParam status string optional New booking status (provider/admin only). Example: confirmed
     * @bodyParam provider_notes string optional Notes from the provider (provider/admin only). Example: Customer was 5 minutes late
     * @bodyParam customer_notes string optional Notes from the customer. Example: Running 10 minutes late
     * @bodyParam price numeric optional Custom price (admin only). Example: 30.00
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking updated successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "duration": 60
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "confirmed",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 30.00,
     *     "customer_notes": "Running 10 minutes late",
     *     "provider_notes": "Customer was 5 minutes late",
     *     "updated_at": "2024-02-12T14:25:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Cannot change status from confirmed to pending"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $updateDto = UpdateBookingDto::from($request->getUpdateData());
        $booking = $this->bookingService->updateBooking($booking, $updateDto);

        return $this->apiResponse->updated(
            'Booking updated successfully',
            new BookingResource($booking)
        );
    }

    /**
     * Delete a booking
     *
     * Soft delete a booking. Users can delete their own pending bookings,
     * providers can delete bookings for their services, and admins can delete any booking.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking to delete. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking deleted successfully"
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Booking not found"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Cannot delete a confirmed booking"
     * }
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $this->bookingService->deleteBooking($booking);

        return $this->apiResponse->deleted('Booking deleted successfully');
    }

    /**
     * Get real-time availability
     *
     * Retrieve available time slots for a specific service on a given date.
     * Returns slots that are not already booked and within provider availability.
     *
     * @authenticated
     *
     * @queryParam service_id integer required The ID of the service to check availability for. Example: 5
     * @queryParam date date required The date to check availability (YYYY-MM-DD format). Example: 2024-02-15
     * @queryParam timezone string required The timezone for the availability check. Example: America/New_York
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Availability retrieved successfully",
     *   "data": {
     *     "date": "2024-02-15",
     *     "timezone": "America/New_York",
     *     "available_slots": [
     *       {
     *         "start_time": "09:00:00",
     *         "end_time": "10:00:00",
     *         "datetime": "2024-02-15T09:00:00-05:00"
     *       },
     *       {
     *         "start_time": "11:00:00",
     *         "end_time": "12:00:00",
     *         "datetime": "2024-02-15T11:00:00-05:00"
     *       },
     *       {
     *         "start_time": "14:00:00",
     *         "end_time": "15:00:00",
     *         "datetime": "2024-02-15T14:00:00-05:00"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid",
     *   "errors": {
     *     "service_id": ["The selected service id is invalid"]
     *   }
     * }
     */
    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $availability = $this->bookingService->getRealTimeAvailability(
            $request->input('service_id'),
            $request->input('date'),
            $request->input('timezone', 'UTC')
        );

        return $this->apiResponse->successResponse(
            'Availability retrieved successfully',
            200,
            [
                'date' => $request->input('date'),
                'available_slots' => $availability,
                'timezone' => $request->input('timezone', 'UTC'),
            ]
        );
    }

    /**
     * Check slot availability
     *
     * Check if a specific date and time slot is available for booking.
     * Useful for real-time validation during booking creation.
     *
     * @authenticated
     *
     * @bodyParam service_id integer required The ID of the service to check. Example: 5
     * @bodyParam datetime string required The exact date and time to check (ISO 8601 format). Example: 2024-02-15T14:00:00Z
     * @bodyParam timezone string required The timezone for the check. Example: America/New_York
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Slot availability checked successfully",
     *   "data": {
     *     "available": true,
     *     "datetime": "2024-02-15T14:00:00Z",
     *     "timezone": "America/New_York"
     *   }
     * }
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Slot availability checked successfully",
     *   "data": {
     *     "available": false,
     *     "datetime": "2024-02-15T14:00:00Z",
     *     "timezone": "America/New_York"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid",
     *   "errors": {
     *     "datetime": ["The datetime must be a date after now"]
     *   }
     * }
     */
    public function checkSlot(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'datetime' => ['required', 'date', 'after:now'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $isAvailable = $this->bookingService->isTimeSlotAvailable(
            $request->input('service_id'),
            $request->input('datetime'),
            $request->input('timezone', 'UTC')
        );

        return $this->apiResponse->successResponse(
            'Slot availability checked successfully',
            200,
            [
                'available' => $isAvailable,
                'datetime' => $request->input('datetime'),
                'timezone' => $request->input('timezone', 'UTC'),
            ]
        );
    }

    /**
     * Confirm a booking
     *
     * Confirm a pending booking. Only providers and admins can confirm bookings.
     * The booking must be in a 'pending' status to be confirmed.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking to confirm. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking confirmed successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "duration": 60
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "confirmed",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 25.00,
     *     "updated_at": "2024-02-12T14:30:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Booking cannot be confirmed in its current state"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function confirm(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        if (! $booking->canBeConfirmed()) {
            return $this->apiResponse->unprocessable(
                'Booking cannot be confirmed in its current state'
            );
        }

        $booking = $this->bookingService->confirmBooking($booking);

        return $this->apiResponse->updated(
            'Booking confirmed successfully',
            new BookingResource($booking)
        );
    }

    /**
     * Cancel a booking
     *
     * Cancel an existing booking. Users can cancel their own bookings,
     * providers can cancel bookings for their services, and admins can cancel any booking.
     * Some statuses may not allow cancellation.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking to cancel. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking cancelled successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "duration": 60
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "cancelled",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 25.00,
     *     "updated_at": "2024-02-12T14:35:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Booking cannot be cancelled"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function cancel(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        if (! $booking->canBeCancelled()) {
            return $this->apiResponse->unprocessable(
                'Booking cannot be cancelled'
            );
        }

        $booking = $this->bookingService->cancelBooking($booking);

        return $this->apiResponse->updated(
            'Booking cancelled successfully',
            new BookingResource($booking)
        );
    }

    /**
     * Complete a booking
     *
     * Mark a booking as completed. Only providers and admins can complete bookings.
     * The booking must be in a 'confirmed' status and the scheduled time must have passed.
     *
     * @authenticated
     *
     * @urlParam booking integer required The ID of the booking to complete. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Booking completed successfully",
     *   "data": {
     *     "id": 15,
     *     "service": {
     *       "id": 5,
     *       "name": "Hair Cut",
     *       "duration": 60
     *     },
     *     "user": {
     *       "id": 10,
     *       "name": "John Doe",
     *       "email": "[email protected]"
     *     },
     *     "status": "completed",
     *     "scheduled_at": "2024-02-15T10:00:00Z",
     *     "price": 25.00,
     *     "updated_at": "2024-02-15T11:05:00Z"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Booking cannot be completed in its current state"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "This action is unauthorized"
     * }
     */
    public function complete(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        if (! $booking->canBeCompleted()) {
            return $this->apiResponse->unprocessable(
                'Booking cannot be completed in its current state'
            );
        }

        $booking = $this->bookingService->completeBooking($booking);

        return $this->apiResponse->updated(
            'Booking completed successfully',
            new BookingResource($booking)
        );
    }

    /**
     * Get bookings by status
     *
     * Retrieve paginated bookings filtered by a specific status.
     * Users see only their own bookings, providers see their service bookings, admins see all.
     *
     * @authenticated
     *
     * @urlParam status string required The booking status to filter by. No-example
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page (max 100). Example: 15
     *
     * @response 200 scenario="Getting pending bookings" {
     *   "success": true,
     *   "message": "Bookings with status 'pending' retrieved successfully",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 12,
     *         "service": {
     *           "id": 5,
     *           "name": "Hair Cut",
     *           "duration": 60
     *         },
     *         "user": {
     *           "id": 8,
     *           "name": "Alice Johnson",
     *           "email": "[email protected]"
     *         },
     *         "status": "pending",
     *         "scheduled_at": "2024-02-16T15:00:00Z",
     *         "price": 25.00,
     *         "created_at": "2024-02-11T10:20:00Z"
     *       }
     *     ],
     *     "total": 8,
     *     "per_page": 15,
     *     "last_page": 1
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Invalid status provided"
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     */
    public function byStatus(Request $request, string $status): JsonResponse
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $statusEnum = \Modules\Booking\Enums\BookingStatusEnum::tryFrom($status);
        if (! $statusEnum) {
            return $this->apiResponse->unprocessable('Invalid status provided');
        }

        $bookings = $this->bookingService->getBookingsByStatus($statusEnum, $request);

        return $this->apiResponse->pagination(
            "Bookings with status '{$status}' retrieved successfully",
            $bookings,
            BookingResource::class
        );
    }
}
