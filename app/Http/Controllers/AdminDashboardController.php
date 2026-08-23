<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->roles()->whereIn('name', ['admin', 'finance_admin', 'super_admin'])->exists(), 403);

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'pendingSellers' => SellerProfile::query()->where('status', 'pending_review')->count(),
                'pendingListings' => Listing::query()->where('status', 'pending_review')->count(),
                'openOrders' => CustomerOrder::query()->whereIn('status', ['pending_payment', 'confirmed'])->count(),
                'buyers' => User::query()->count(),
            ],
        ]);
    }
}
