<?php

namespace Modules\Booking\Services;

use App\Services\LoggingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Enums\Roles;
use Modules\Booking\DTOs\CreateBookingDto;
use Modules\Booking\DTOs\UpdateBookingDto;
use Modules\Booking\Enums\BookingStatusEnum;
use Modules\Booking\Events\BookingCancelled;
use Modules\Booking\Events\BookingConfirmed;
use Modules\Booking\Events\BookingCreated;
use Modules\Booking\Events\BookingStatusChanged;
use Modules\Booking\Models\Booking;

/**
 * BookingService
 *
 * @description Service class for managing booking operations
 */
class BookingService
{
    public function __construct(
        protected LoggingService $loggingService
    ) {}

    /**
     * Get all bookings
     *
     * @throws \Throwable
     */
    public function getAllBookings(Request $request): LengthAwarePaginator
    {
        $query = Booking::with(['user', 'service']);

        // Role-based filtering
        if (auth()->user()->role == Roles::USER) {
            $query->where('user_id', auth()->id());
        } elseif (auth()->user()->role == Roles::PROVIDER) {
            $query->whereHas('service', function ($q) {
                $q->where('provider_id', auth()->id());
            });
        }
        // Admin sees all bookings

        // Search functionality
        if ($request->has('q')) {
            $query->where('service_name', 'like', "%{$request->input('q')}%")
                ->orWhere('service_description', 'like', "%{$request->input('q')}%");
        }

        // Filter by user
        if ($request->has('user_id') && auth()->user()->role != Roles::USER) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by service
        if ($request->has('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $status = BookingStatusEnum::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        // Filter by price range
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->input('price_min'));
        }

        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->input('price_max'));
        }

        return $query->orderBy('date', 'desc')->paginate(10);
    }

    /**
     * Create a new booking with availability validation
     *
     * @throws \Exception
     */
    public function createBooking(CreateBookingDto $bookingDto): Booking
    {
        // Validate booking constraints before creation
        $this->validateBookingConstraints($bookingDto);

        try {
            $booking = Booking::create($bookingDto->toArray());

            $this->loggingService->log('Booking created', [
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'service_name' => $booking->service_name,
                'user_id' => $booking->user_id,
                'created_by' => Auth::id(),
                'booking_date' => $booking->date,
                'status' => $booking->status->value,
            ]);

            // Fire booking created event
            BookingCreated::dispatch($booking);

            return $booking->load(['user', 'service', 'provider']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to create booking', [
                'error' => $e->getMessage(),
                'service_id' => $bookingDto->service_id,
                'user_id' => $bookingDto->user_id,
                'created_by' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a specific booking by ID
     */
    public function getBookingById(Booking $booking): Booking
    {
        return $booking->load(['user', 'service']);
    }

    /**
     * Update an existing booking
     *
     * @throws \Exception
     */
    public function updateBooking(Booking $booking, UpdateBookingDto $bookingDto): Booking
    {
        try {
            $oldStatus = $booking->status;

            // Validate status transitions
            if ($bookingDto->status && ! $oldStatus->canTransitionTo($bookingDto->status)) {
                throw new \Exception("Cannot transition from {$oldStatus->value} to {$bookingDto->status->value}");
            }

            $booking->update($bookingDto->toArray());

            $logData = [
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'user_id' => $booking->user_id,
                'updated_by' => Auth::id(),
            ];

            // Log status changes specifically
            if ($bookingDto->status && $oldStatus !== $bookingDto->status) {
                $logData['status_changed'] = [
                    'from' => $oldStatus->value,
                    'to' => $bookingDto->status->value,
                ];

                // Fire specific status change events
                BookingStatusChanged::dispatch($booking, $oldStatus, $bookingDto->status);

                if ($bookingDto->status === BookingStatusEnum::CONFIRMED) {
                    BookingConfirmed::dispatch($booking);
                } elseif ($bookingDto->status === BookingStatusEnum::CANCELLED) {
                    $cancelledBy = auth()->id() === $booking->user_id ? 'customer' : 'provider';
                    BookingCancelled::dispatch($booking, $cancelledBy);
                }
            }

            $this->loggingService->log('Booking updated', $logData);

            return $booking->load(['user', 'service', 'provider']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to update booking', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
                'updated_by' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a booking (soft delete)
     *
     * @throws \Exception
     */
    public function deleteBooking(Booking $booking): bool
    {
        try {
            $bookingId = $booking->id;
            $serviceId = $booking->service_id;
            $userId = $booking->user_id;
            $status = $booking->status->value;

            $deleted = $booking->delete();

            $this->loggingService->log('Booking deleted', [
                'booking_id' => $bookingId,
                'service_id' => $serviceId,
                'user_id' => $userId,
                'status' => $status,
                'deleted_by' => Auth::id(),
            ]);

            return $deleted;
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to delete booking', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
                'deleted_by' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get bookings by user
     */
    public function getBookingsByUser(int $userId, Request $request): LengthAwarePaginator
    {
        $query = Booking::where('user_id', $userId)
            ->with(['user', 'service']);

        if ($request->has('status')) {
            $status = BookingStatusEnum::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return $query->orderBy('date', 'desc')->paginate(10);
    }

    /**
     * Get bookings by service
     */
    public function getBookingsByService(int $serviceId, Request $request): LengthAwarePaginator
    {
        $query = Booking::where('service_id', $serviceId)
            ->with(['user', 'service']);

        if ($request->has('status')) {
            $status = BookingStatusEnum::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return $query->orderBy('date', 'desc')->paginate(10);
    }

    /**
     * Get bookings by status
     */
    public function getBookingsByStatus(BookingStatusEnum $status, Request $request): LengthAwarePaginator
    {
        $query = Booking::where('status', $status)
            ->with(['user', 'service']);

        // Role-based filtering
        if (auth()->user()->role == Roles::USER) {
            $query->where('user_id', auth()->id());
        } elseif (auth()->user()->role == Roles::PROVIDER) {
            $query->whereHas('service', function ($q) {
                $q->where('provider_id', auth()->id());
            });
        }

        if ($request->has('user_id') && auth()->user()->role != Roles::USER) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return $query->orderBy('date', 'desc')->paginate(10);
    }

    /**
     * Cancel a booking
     *
     * @throws \Exception
     */
    public function cancelBooking(Booking $booking): Booking
    {
        if ($booking->status === BookingStatusEnum::CANCELLED) {
            throw new \Exception('Booking is already cancelled');
        }

        if ($booking->status === BookingStatusEnum::COMPLETED) {
            throw new \Exception('Cannot cancel a completed booking');
        }

        return $this->updateBooking($booking, new UpdateBookingDto(
            status: BookingStatusEnum::CANCELLED
        ));
    }

    /**
     * Confirm a booking
     *
     * @throws \Exception
     */
    public function confirmBooking(Booking $booking): Booking
    {
        if ($booking->status === BookingStatusEnum::CONFIRMED) {
            throw new \Exception('Booking is already confirmed');
        }

        if ($booking->status === BookingStatusEnum::CANCELLED) {
            throw new \Exception('Cannot confirm a cancelled booking');
        }

        if ($booking->status === BookingStatusEnum::COMPLETED) {
            throw new \Exception('Cannot confirm a completed booking');
        }

        return $this->updateBooking($booking, new UpdateBookingDto(
            status: BookingStatusEnum::CONFIRMED
        ));
    }

    /**
     * Complete a booking
     *
     * @throws \Exception
     */
    public function completeBooking(Booking $booking): Booking
    {
        if ($booking->status === BookingStatusEnum::COMPLETED) {
            throw new \Exception('Booking is already completed');
        }

        if ($booking->status === BookingStatusEnum::CANCELLED) {
            throw new \Exception('Cannot complete a cancelled booking');
        }

        return $this->updateBooking($booking, new UpdateBookingDto(
            status: BookingStatusEnum::COMPLETED
        ));
    }

    /**
     * Validate booking constraints before creation
     *
     * @throws \Exception
     */
    protected function validateBookingConstraints(CreateBookingDto $bookingDto): void
    {
        // Check if booking is in the past
        $bookingDateTime = \Carbon\Carbon::parse($bookingDto->date);
        if ($bookingDateTime->isPast()) {
            throw new \Exception('Cannot book appointments in the past');
        }

        // Check for double booking
        if ($this->isSlotOccupied($bookingDto->service_id, $bookingDateTime)) {
            throw new \Exception('This time slot is already occupied');
        }

        // Check user doesn't already have a booking at this time
        if ($this->userHasOverlappingBooking($bookingDto->user_id, $bookingDto->service_id, $bookingDateTime)) {
            throw new \Exception('You already have a booking during this time');
        }
    }

    /**
     * Check if a time slot is occupied for a service
     */
    public function isSlotOccupied(int $serviceId, \Carbon\Carbon $dateTime): bool
    {
        $service = \Modules\Service\Models\Service::findOrFail($serviceId);
        $endTime = $dateTime->copy()->addMinutes($service->duration);

        return Booking::where('service_id', $serviceId)
            ->whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where(function ($query) use ($dateTime, $endTime) {
                $query->whereBetween('date', [$dateTime, $endTime])
                    ->orWhere(function ($subQuery) use ($dateTime, $endTime) {
                        // Check if booking overlaps with our requested time
                        $subQuery->where('date', '<', $endTime)
                            ->whereExists(function ($q) use ($dateTime) {
                                $q->select(\DB::raw('1'))
                                    ->from('services')
                                    ->whereColumn('services.id', 'bookings.service_id')
                                    ->whereRaw('DATE_ADD(bookings.date, INTERVAL services.duration MINUTE) > ?', [$dateTime->toDateTimeString()]);
                            });
                    });
            })
            ->exists();
    }

    /**
     * Check if user has overlapping booking
     */
    public function userHasOverlappingBooking(int $userId, int $serviceId, \Carbon\Carbon $dateTime): bool
    {
        $service = \Modules\Service\Models\Service::findOrFail($serviceId);
        $endTime = $dateTime->copy()->addMinutes($service->duration);

        return Booking::where('user_id', $userId)
            ->whereNotIn('status', [BookingStatusEnum::CANCELLED])
            ->where(function ($query) use ($dateTime, $endTime) {
                $query->whereBetween('date', [$dateTime, $endTime])
                    ->orWhere(function ($subQuery) use ($dateTime, $endTime) {
                        // Check if user's booking overlaps with requested time
                        $subQuery->where('date', '<', $endTime)
                            ->whereExists(function ($q) use ($dateTime) {
                                $q->select(\DB::raw('1'))
                                    ->from('services')
                                    ->whereColumn('services.id', 'bookings.service_id')
                                    ->whereRaw('DATE_ADD(bookings.date, INTERVAL services.duration MINUTE) > ?', [$dateTime->toDateTimeString()]);
                            });
                    });
            })
            ->exists();
    }

    /**
     * Get real-time availability for a service on a specific date
     */
    public function getRealTimeAvailability(int $serviceId, string $date, string $timezone = 'UTC'): array
    {
        $slotService = app(\Modules\AvailabilityManagement\Services\SlotService::class);
        $availableSlots = $slotService->getAvailableSlots($serviceId, $timezone, $date);

        return $availableSlots[$date] ?? [];
    }

    /**
     * Check if a specific time slot is available
     */
    public function isTimeSlotAvailable(int $serviceId, string $dateTime, string $timezone = 'UTC'): bool
    {
        $requestedDateTime = \Carbon\Carbon::parse($dateTime, $timezone);
        $dateString = $requestedDateTime->format('Y-m-d');
        $timeString = $requestedDateTime->format('H:i');

        $availableSlots = $this->getRealTimeAvailability($serviceId, $dateString, $timezone);

        return in_array($timeString, $availableSlots);
    }
}
