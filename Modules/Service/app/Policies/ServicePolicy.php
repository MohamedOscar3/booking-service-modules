<?php

namespace Modules\Service\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Auth\Models\User;
use Modules\Service\Models\Service;

class ServicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view services
    }

    public function view(User $user, Service $service): bool
    {
        return true; // All authenticated users can view individual services
    }

    public function create(User $user): bool
    {
        // Only admin and provider can create services
        return $user->isAdmin() || $user->isProvider();
    }

    public function update(User $user, Service $service): bool
    {
        // Admin can update any service, providers can only update their own services
        return $user->isAdmin() ||
               ($user->isProvider() && $user->id === $service->provider_id);
    }

    public function delete(User $user, Service $service): bool
    {
        // Admin can delete any service, providers can only delete their own services
        return $user->isAdmin() ||
               ($user->isProvider() && $user->id === $service->provider_id);
    }

    public function restore(User $user, Service $service): bool
    {
        // Admin can restore any service, providers can only restore their own services
        return $user->isAdmin() ||
               ($user->isProvider() && $user->id === $service->provider_id);
    }

    public function forceDelete(User $user, Service $service): bool
    {
        // Only admin can force delete services
        return $user->isAdmin();
    }
}
