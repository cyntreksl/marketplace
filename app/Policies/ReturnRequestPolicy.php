<?php

namespace App\Policies;

use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;

class ReturnRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReturnRequest $returnRequest): bool
    {
        return $returnRequest->buyer_id === $user->id
            || $this->isOperationsUser($user)
            || $this->belongsToSeller($user, $returnRequest);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReturnRequest $returnRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReturnRequest $returnRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReturnRequest $returnRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReturnRequest $returnRequest): bool
    {
        return false;
    }

    public function decide(User $user, ReturnRequest $returnRequest): bool
    {
        return $this->belongsToSeller($user, $returnRequest);
    }

    public function manageRefund(User $user, ReturnRequest $returnRequest): bool
    {
        return $this->isOperationsUser($user);
    }

    private function belongsToSeller(User $user, ReturnRequest $returnRequest): bool
    {
        return $returnRequest->orderItem()
            ->whereHas('sellerOrder.sellerProfile', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', [Role::Admin, Role::FinanceAdmin, Role::SuperAdmin])->exists();
    }
}
