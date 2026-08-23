<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSellerOrderStatusRequest;
use App\Models\SellerOrder;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();

        return Inertia::render('seller/orders/index', ['orders' => $seller->sellerOrders()->with(['items', 'shipment'])->latest()->paginate(15)->withQueryString()]);
    }

    public function ready(UpdateSellerOrderStatusRequest $request, SellerOrder $sellerOrder, ShipmentService $shipments): RedirectResponse
    {
        abort_unless($sellerOrder->seller_profile_id === $request->user()->sellerProfile()->value('id'), 403);
        $shipments->markReadyToShip($request->user(), $sellerOrder, $request->validated('courier_name'), $request->validated('tracking_number'));

        return to_route('seller.orders.index')->with('status', 'Order marked ready to ship.');
    }
}
