<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerOrderRepository;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuestOrderClaimService
{
    public function __construct(private readonly CustomerOrderRepository $orders) {}

    public function claim(User $buyer, CustomerOrder $customerOrder): CustomerOrder
    {
        abort_unless($buyer->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($buyer, $customerOrder): CustomerOrder {
            $order = $this->orders->lockForClaim($customerOrder->id);

            if ($order->buyer_id !== null) {
                abort_unless($order->buyer_id === $buyer->id, 403);

                return $order;
            }

            abort_unless(hash_equals(mb_strtolower($order->contact_email), mb_strtolower($buyer->email)), 403);

            return $this->orders->claim($order, $buyer);
        }, attempts: 3);
    }
}
