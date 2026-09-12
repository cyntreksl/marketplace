<?php

namespace App\Services;

use App\Models\CustomerOrder;
use Illuminate\Http\Request;

final class PurchaseTrackingSessionService
{
    private const string SESSION_KEY = 'tracking.purchase_orders';

    public function register(Request $request, CustomerOrder $order): void
    {
        $request->session()->put(self::SESSION_KEY.'.'.$order->number, true);
    }

    public function consume(Request $request, CustomerOrder $order): bool
    {
        if ($order->status !== 'confirmed') {
            return false;
        }

        return $request->session()->pull(
            self::SESSION_KEY.'.'.$order->number,
            false,
        ) === true;
    }
}
