<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontBrowseRequest;
use App\Services\StorefrontService;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StorefrontService $storefront,
    ) {}

    public function home(): Response
    {
        return Inertia::render('storefront/home', $this->storefront->homeData());
    }

    public function index(StorefrontBrowseRequest $request): Response
    {
        return Inertia::render('storefront/listings/index', $this->storefront->browseData($request->filters()));
    }

    public function show(string $listing): Response
    {
        return Inertia::render('storefront/listings/show', $this->storefront->listingDetailsData($listing));
    }
}
