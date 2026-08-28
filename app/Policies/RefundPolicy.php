<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\Role;
use App\Models\User;

class RefundPolicy
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
    public function view(User $user, Refund $refund): bool
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
    public function update(User $user, Refund $refund): bool
    {
        return $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Refund $refund): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Refund $refund): bool
    {
        return false;
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', [Role::Admin, Role::FinanceAdmin, Role::SuperAdmin])->exists();
    }
}
