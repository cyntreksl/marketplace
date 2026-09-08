<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellerListingIndexRequest;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerWholesaleController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    public function index(SellerListingIndexRequest $request): Response
    {
        return Inertia::render('seller/wholesale/index', $this->listings->sellerWholesaleIndex($request->user(), $request->validated()));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('seller/wholesale/create', $this->listings->sellerCreateData($request->user(), 'wholesale'));
    }
}
