<?php

namespace App\Contracts\Repositories;

use App\Models\BuyerAddress;
use App\Models\User;
use Illuminate\Support\Collection;

interface BuyerAddressRepository
{
    public function lockBuyer(int $buyerId): User;

    /** @return Collection<int, BuyerAddress> */
    public function allFor(User $buyer): Collection;

    public function findOwned(User $buyer, int $addressId): ?BuyerAddress;

    public function defaultFor(User $buyer, string $purpose): ?BuyerAddress;

    public function hasFor(User $buyer, string $purpose): bool;

    /** @param array<string, mixed> $data */
    public function create(User $buyer, array $data): BuyerAddress;

    /** @param array<string, mixed> $data */
    public function save(BuyerAddress $address, array $data): BuyerAddress;

    public function clearDefault(User $buyer, string $purpose): void;

    public function delete(BuyerAddress $address): void;
}
