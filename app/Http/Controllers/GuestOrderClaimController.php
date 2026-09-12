<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Services\GuestOrderClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuestOrderClaimController extends Controller
{
    public function __invoke(Request $request, CustomerOrder $customerOrder, GuestOrderClaimService $claims): RedirectResponse
    {
        $order = $claims->claim($request->user(), $customerOrder);

        return to_route('buyer.orders.show', $order->number)
            ->with('status', 'This order is now linked to your account.');
    }
}
