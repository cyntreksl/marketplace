<?php

namespace App\Policies;

use App\Models\SeoRedirect;
use App\Models\User;

class SeoRedirectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SeoRedirect $seoRedirect): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SeoRedirect $seoRedirect): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SeoRedirect $seoRedirect): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SeoRedirect $seoRedirect): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SeoRedirect $seoRedirect): bool
    {
        return false;
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', ['admin', 'super_admin'])->exists();
    }
}
