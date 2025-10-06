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
        // Users can view their own bookings or bookings for services they provide, or admins can view all
        return $user->isAdmin() ||
               $user->id === $booking->user_id ||
               $user->id === $booking->service->provider_id;
    }

    public function create(User $user): bool
    {
        // Only users with client role (USER) can create bookings
        return $user->isUser();
    }

    public function update(User $user, Booking $booking): bool
    {
        // Customer can update their own bookings (limited fields via UpdateBookingRequest)
        // Provider can update bookings for their services
        // Admin can update any booking
        return $user->isAdmin() ||
               $user->id === $booking->user_id ||
               $user->id === $booking->service->provider_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Both customer and provider can cancel/delete bookings, or admin
        return $user->isAdmin() ||
               $user->id === $booking->user_id ||
               $user->id === $booking->service->provider_id;
    }

    public function restore(User $user, Booking $booking): bool
    {
        // Only the service provider or admin can restore bookings
        return $user->isAdmin() ||
               $user->id === $booking->service->provider_id;
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        // Only admin can force delete bookings
        return $user->isAdmin();
    }
}
