<?php

namespace App\Services;

use App\Contracts\Repositories\BuyerAddressRepository;
use App\Models\BuyerAddress;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerAddressService
{
    public function __construct(private readonly BuyerAddressRepository $addresses) {}

    /** @return Collection<int, BuyerAddress> */
    public function all(User $buyer): Collection
    {
        return $this->addresses->allFor($buyer);
    }

    /** @param array<string, mixed> $data */
    public function create(User $buyer, array $data): BuyerAddress
    {
        return DB::transaction(function () use ($buyer, $data): BuyerAddress {
            $lockedBuyer = $this->addresses->lockBuyer($buyer->id);
            $data = $this->withDefaultState($lockedBuyer, $data);

            $this->clearRequestedDefaults($lockedBuyer, $data);

            return $this->addresses->create($lockedBuyer, $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $buyer, BuyerAddress $address, array $data): BuyerAddress
    {
        return DB::transaction(function () use ($buyer, $address, $data): BuyerAddress {
            $lockedBuyer = $this->addresses->lockBuyer($buyer->id);
            $ownedAddress = $this->owned($lockedBuyer, $address->id);

            if (! ($data['shipping_enabled'] ?? false)) {
                $data['is_default_shipping'] = false;
            }

            if (! ($data['billing_enabled'] ?? false)) {
                $data['is_default_billing'] = false;
            }

            $this->clearRequestedDefaults($lockedBuyer, $data);

            return $this->addresses->save($ownedAddress, $data);
        });
    }

    public function setDefault(User $buyer, BuyerAddress $address, string $purpose): BuyerAddress
    {
        return DB::transaction(function () use ($buyer, $address, $purpose): BuyerAddress {
            $lockedBuyer = $this->addresses->lockBuyer($buyer->id);
            $ownedAddress = $this->owned($lockedBuyer, $address->id);

            if (! $ownedAddress->getAttribute($purpose.'_enabled')) {
                throw ValidationException::withMessages([
                    'purpose' => "Enable this address for {$purpose} before making it the default.",
                ]);
            }

            $this->addresses->clearDefault($lockedBuyer, $purpose);

            return $this->addresses->save($ownedAddress, ['is_default_'.$purpose => true]);
        });
    }

    public function delete(User $buyer, BuyerAddress $address): void
    {
        DB::transaction(function () use ($buyer, $address): void {
            $lockedBuyer = $this->addresses->lockBuyer($buyer->id);
            $this->addresses->delete($this->owned($lockedBuyer, $address->id));
        });
    }

    public function selected(User $buyer, ?int $addressId, string $purpose): ?BuyerAddress
    {
        if ($addressId === null) {
            return $this->addresses->defaultFor($buyer, $purpose);
        }

        $address = $this->addresses->findOwned($buyer, $addressId);

        if ($address === null || ! $address->getAttribute($purpose.'_enabled')) {
            throw ValidationException::withMessages([
                $purpose.'_address_id' => "Choose a valid saved {$purpose} address.",
            ]);
        }

        return $address;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDefaultState(User $buyer, array $data): array
    {
        foreach (['shipping', 'billing'] as $purpose) {
            $enabled = (bool) ($data[$purpose.'_enabled'] ?? false);
            $requested = (bool) ($data['is_default_'.$purpose] ?? false);
            $data['is_default_'.$purpose] = $enabled
                && ($requested || ! $this->addresses->hasFor($buyer, $purpose));
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function clearRequestedDefaults(User $buyer, array $data): void
    {
        foreach (['shipping', 'billing'] as $purpose) {
            if ($data['is_default_'.$purpose] ?? false) {
                $this->addresses->clearDefault($buyer, $purpose);
            }
        }
    }

    private function owned(User $buyer, int $addressId): BuyerAddress
    {
        return $this->addresses->findOwned($buyer, $addressId) ?? abort(404);
    }
}
