<?php

namespace Modules\Booking\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Auth\Models\User;
use Modules\Booking\Models\Booking;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // Users can view bookings (with appropriate filtering applied)
    }

    public function view(User $user, Booking $booking): bool
    {
        // Users can view their own bookings or bookings for services they provide
        return $user->id === $booking->user_id ||
               $user->id === $booking->service->provider_id;
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated user can create bookings
    }

    public function update(User $user, Booking $booking): bool
    {
        // Only the service provider can update bookings
        return $user->id === $booking->service->provider_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Both customer and provider can cancel/delete bookings
        return $user->id === $booking->user_id ||
               $user->id === $booking->service->provider_id;
    }

    public function restore(User $user, Booking $booking): bool
    {
        // Only the service provider can restore bookings
        return $user->id === $booking->service->provider_id;
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        // Only the service provider can force delete bookings
        return $user->id === $booking->service->provider_id;
    }
}
