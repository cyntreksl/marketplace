<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->sellerProfile()->exists() || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Listing $listing): bool
    {
        return $listing->seller_profile_id === $user->sellerProfile()->value('id') || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->sellerProfile()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $listing->seller_profile_id === $user->sellerProfile()->value('id') || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Listing $listing): bool
    {
        return $listing->seller_profile_id === $user->sellerProfile()->value('id');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Listing $listing): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Listing $listing): bool
    {
        return false;
    }

    public function moderate(User $user, Listing $listing): bool
    {
        return $this->isOperationsUser($user);
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', ['admin', 'super_admin'])->exists();
    }
}
