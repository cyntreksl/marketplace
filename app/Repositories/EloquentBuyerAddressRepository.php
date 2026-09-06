<?php

namespace App\Repositories;

use App\Contracts\Repositories\BuyerAddressRepository;
use App\Models\BuyerAddress;
use App\Models\User;
use Illuminate\Support\Collection;

class EloquentBuyerAddressRepository implements BuyerAddressRepository
{
    public function lockBuyer(int $buyerId): User
    {
        return User::withTrashed()->lockForUpdate()->findOrFail($buyerId);
    }

    public function allFor(User $buyer): Collection
    {
        return BuyerAddress::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->latest('updated_at')
            ->get();
    }

    public function findOwned(User $buyer, int $addressId): ?BuyerAddress
    {
        return BuyerAddress::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->find($addressId);
    }

    public function defaultFor(User $buyer, string $purpose): ?BuyerAddress
    {
        return BuyerAddress::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->where('is_default_'.$purpose, true)
            ->first();
    }

    public function hasFor(User $buyer, string $purpose): bool
    {
        return BuyerAddress::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->where($purpose.'_enabled', true)
            ->exists();
    }

    public function create(User $buyer, array $data): BuyerAddress
    {
        return $buyer->buyerAddresses()->create($data);
    }

    public function save(BuyerAddress $address, array $data): BuyerAddress
    {
        $address->update($data);

        return $address->refresh();
    }

    public function clearDefault(User $buyer, string $purpose): void
    {
        BuyerAddress::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->where('is_default_'.$purpose, true)
            ->update(['is_default_'.$purpose => false]);
    }

    public function delete(BuyerAddress $address): void
    {
        $address->delete();
    }
}
