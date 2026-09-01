<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('buyer/orders/index', [
            'orders' => CustomerOrder::query()
                ->where('buyer_id', $request->user()->id)
                ->with([
                    'sellerOrders.sellerProfile:id,store_name',
                    'sellerOrders.items:id,seller_order_id,listing_id,title,variant_sku,variant_options,quantity,unit_price,total',
                    'sellerOrders.items.listing:id,slug',
                    'sellerOrders.items.review:id,order_item_id,rating,comment',
                    'payments:id,customer_order_id,method,status,amount',
                ])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
