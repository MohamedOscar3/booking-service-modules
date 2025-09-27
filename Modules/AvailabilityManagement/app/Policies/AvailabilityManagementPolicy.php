<?php

namespace Modules\AvailabilityManagement\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Auth\Enums\Roles;
use Modules\Auth\Models\User;
use Modules\AvailabilityManagement\Models\AvailabilityManagement;

class AvailabilityManagementPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any availability slots.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view availability slots
    }

    /**
     * Determine whether the user can view the availability slot.
     */
    public function view(User $user, AvailabilityManagement $availabilityManagement): bool
    {
        return true; // All authenticated users can view individual availability slots
    }

    /**
     * Determine whether the user can create availability slots.
     */
    public function create(User $user): bool
    {
        // Only admin and provider can create availability slots
        return $user->role === Roles::ADMIN || $user->role === Roles::PROVIDER;
    }

    /**
     * Determine whether the user can update the availability slot.
     */
    public function update(User $user, AvailabilityManagement $availabilityManagement): bool
    {
        // Admin can update any availability slot, providers can only update their own slots
        return $user->role === Roles::ADMIN ||
               ($user->role === Roles::PROVIDER && $user->id === $availabilityManagement->provider_id);
    }

    /**
     * Determine whether the user can delete the availability slot.
     */
    public function delete(User $user, AvailabilityManagement $availabilityManagement): bool
    {
        // Admin can delete any availability slot, providers can only delete their own slots
        return $user->role === Roles::ADMIN ||
               ($user->role === Roles::PROVIDER && $user->id === $availabilityManagement->provider_id);
    }

    /**
     * Determine whether the user can restore the availability slot.
     */
    public function restore(User $user, AvailabilityManagement $availabilityManagement): bool
    {
        // Admin can restore any availability slot, providers can only restore their own slots
        return $user->role === Roles::ADMIN ||
               ($user->role === Roles::PROVIDER && $user->id === $availabilityManagement->provider_id);
    }

    /**
     * Determine whether the user can permanently delete the availability slot.
     */
    public function forceDelete(User $user, AvailabilityManagement $availabilityManagement): bool
    {
        // Only admin can force delete availability slots
        return $user->role === Roles::ADMIN;
    }
}
