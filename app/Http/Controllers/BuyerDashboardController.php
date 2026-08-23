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
                ->with(['sellerOrders.sellerProfile:id,store_name', 'payments:id,customer_order_id,method,status,amount'])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
