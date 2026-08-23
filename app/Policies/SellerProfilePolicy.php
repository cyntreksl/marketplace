<?php

namespace App\Policies;

use App\Models\SellerProfile;
use App\Models\User;

class SellerProfilePolicy
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
    public function view(User $user, SellerProfile $sellerProfile): bool
    {
        return $sellerProfile->user_id === $user->id || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! $user->sellerProfile()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SellerProfile $sellerProfile): bool
    {
        return $sellerProfile->user_id === $user->id || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SellerProfile $sellerProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SellerProfile $sellerProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SellerProfile $sellerProfile): bool
    {
        return false;
    }

    public function review(User $user, SellerProfile $sellerProfile): bool
    {
        return $this->isOperationsUser($user);
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', ['admin', 'super_admin'])->exists();
    }
}
