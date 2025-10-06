<?php

namespace Modules\AvailabilityManagement\Services;

use App\Services\LoggingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Enums\Roles;
use Modules\AvailabilityManagement\DTOs\CreateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\DTOs\UpdateAvailabilityManagementDTO;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;

/**
 * AvailabilityManagementService
 *
 * @description Service class for managing availability operations
 */
class AvailabilityManagementService
{
    public function __construct(
        protected LoggingService $loggingService
    ) {}

    /**
     * Get all availability slots
     *
     * @throws \Throwable
     */
    public function getAllAvailabilitySlots(Request $request): LengthAwarePaginator
    {
        $query = AvailabilityManagement::with(['provider']);

        // Filter by provider if user is a provider
        if (auth()->user()->role == Roles::PROVIDER) {
            $query->where('provider_id', auth()->id());
        } elseif (auth()->user()->role == Roles::ADMIN) {
            if ($request->has('provider_id')) {
                $query->where('provider_id', $request->input('provider_id'));
            }
        } else {
            abort(403, 'Unauthorized action.');
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by week day
        if ($request->has('week_day')) {
            $query->where('week_day', $request->integer('week_day'));
        }

        // Filter by time
        if ($request->has('from')) {
            $query->whereTime('from', $request->input('from'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $query
            ->orderBy('week_day')
            ->orderBy('from')
            ->paginate(10);
    }

    /**
     * Create a new availability slot
     *
     * @throws \Exception
     */
    public function createAvailabilitySlot(CreateAvailabilityManagementDTO $availabilityDto): AvailabilityManagement
    {
        try {
            $availability = AvailabilityManagement::create($availabilityDto->toArray());

            $this->loggingService->log('Availability slot created', [
                'availability_id' => $availability->id,
                'provider_id' => $availability->provider_id,
                'type' => $availability->type->value,
                'from' => $availability->from,
                'to' => $availability->to,
                'user_id' => Auth::id(),
            ]);

            return $availability->load(['provider']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to create availability slot', [
                'error' => $e->getMessage(),
                'provider_id' => $availabilityDto->provider_id,
                'type' => $availabilityDto->type->value,
                'from' => $availabilityDto->from,
                'to' => $availabilityDto->to,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a specific availability slot by ID
     */
    public function getAvailabilitySlotById(AvailabilityManagement $availability): AvailabilityManagement
    {
        return $availability->load(['provider']);
    }

    /**
     * Update an existing availability slot
     *
     * @throws \Exception
     */
    public function updateAvailabilitySlot(AvailabilityManagement $availability, UpdateAvailabilityManagementDTO $availabilityDto): AvailabilityManagement
    {
        try {
            $data = $availabilityDto->toArray();

            foreach ($data as $key => $value) {
                $availability->{$key} = $value;
            }

            $availability->save();

            $this->loggingService->log('Availability slot updated', [
                'availability_id' => $availability->id,
                'provider_id' => $availability->provider_id,
                'type' => $availability->type->value,
                'from' => $availability->from,
                'to' => $availability->to,
                'status' => $availability->status,
                'week_day' => $availability->week_day,
                'user_id' => Auth::id(),
            ]);

            return $availability->load(['provider']);
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to update availability slot', [
                'error' => $e->getMessage(),
                'availability_id' => $availability->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete an availability slot
     *
     * @throws \Exception
     */
    public function deleteAvailabilitySlot(AvailabilityManagement $availability): bool
    {
        try {
            $availabilityId = $availability->id;
            $providerId = $availability->provider_id;
            $type = $availability->type->value;
            $from = $availability->from;
            $to = $availability->to;

            $deleted = $availability->delete();

            $this->loggingService->log('Availability slot deleted', [
                'availability_id' => $availabilityId,
                'provider_id' => $providerId,
                'type' => $type,
                'from' => $from,
                'to' => $to,
                'user_id' => Auth::id(),
            ]);

            return $deleted;
        } catch (\Exception $e) {
            $this->loggingService->log('Failed to delete availability slot', [
                'error' => $e->getMessage(),
                'availability_id' => $availability->id,
                'user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }

    /**
     * Get availability slots by provider
     */
    public function getAvailabilitySlotsByProvider(int $providerId, Request $request): LengthAwarePaginator
    {
        $query = AvailabilityManagement::where('provider_id', $providerId)
            ->with(['provider']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by week day
        if ($request->has('week_day')) {
            $query->where('week_day', $request->integer('week_day'));
        }

        // Filter by time range
        if ($request->has('from_time')) {
            $query->whereTime('from', '>=', $request->input('from_time'));
        }

        if ($request->has('to_time')) {
            $query->whereTime('to', '<=', $request->input('to_time'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $query->orderBy('from')
            ->paginate(10);
    }

    /**
     * Get available slots for a specific time
     */
    public function getAvailableSlotsForDate(string $time, ?int $providerId = null): LengthAwarePaginator
    {
        $query = AvailabilityManagement::where('status', true)
            ->whereTime('from', $time)
            ->with(['provider']);

        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        return $query->orderBy('from')
            ->paginate(10);
    }

    /**
     * Get recurring availability for a provider
     */
    public function getRecurringAvailability(int $providerId, Request $request): LengthAwarePaginator
    {
        $query = AvailabilityManagement::where('provider_id', $providerId)
            ->where('type', 'recurring')
            ->with(['provider']);

        // Filter by week day
        if ($request->has('week_day')) {
            $query->where('week_day', $request->integer('week_day'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        return $query->orderBy('week_day')
            ->orderBy('from')
            ->paginate(10);
    }
}
