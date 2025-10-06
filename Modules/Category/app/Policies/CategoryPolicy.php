<?php

namespace Modules\Category\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Auth\Models\User;
use Modules\Category\Models\Category;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view categories
    }

    public function view(User $user, Category $category): bool
    {
        return true; // All authenticated users can view individual categories
    }

    public function create(User $user): bool
    {
        // Only admin can create categories
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        // Only admin can update categories
        return $user->isAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        // Only admin can delete categories
        return $user->isAdmin();
    }

    public function restore(User $user, Category $category): bool
    {
        // Only admin can restore categories
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Category $category): bool
    {
        // Only admin can force delete categories
        return $user->isAdmin();
    }
}
