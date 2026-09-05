<?php

namespace App\Contracts\Repositories;

use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Collection;

interface CheckoutRepository
{
    public function cart(User $buyer): Cart;

    public function listing(int $id): Listing;

    public function variant(int $id): ?ListingVariant;

    public function findSubmission(User $buyer, string $token): ?CustomerOrder;

    /** @param array<string, mixed> $data */
    public function createOrder(array $data): CustomerOrder;

    /** @param array<string, mixed> $data */
    public function createSellerOrder(array $data): SellerOrder;

    /** @param array<string, mixed> $data */
    public function createPayment(array $data): Payment;

    /** @param array<string, mixed> $data */
    public function addItem(SellerOrder $order, array $data): void;

    public function reserve(Listing $listing, ?ListingVariant $variant, int $quantity): void;

    public function clear(Cart $cart): void;

    public function details(CustomerOrder $order): CustomerOrder;

    public function payment(CustomerOrder $order): Payment;

    public function lockPayment(int $id): Payment;

    /** @param array<string, mixed> $data */
    public function savePayment(Payment $payment, array $data): void;

    public function confirm(CustomerOrder $order): void;

    public function expire(CustomerOrder $order): void;

    public function findPayment(int $id): ?Payment;

    /** @return Collection<int, Payment> */
    public function duePayments(): Collection;
}
