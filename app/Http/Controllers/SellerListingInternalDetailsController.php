<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateListingInternalDetailsRequest;
use App\Models\Listing;
use App\Services\ListingInternalDetailsService;
use Illuminate\Http\RedirectResponse;

class SellerListingInternalDetailsController extends Controller
{
    public function __construct(private readonly ListingInternalDetailsService $details) {}

    public function update(UpdateListingInternalDetailsRequest $request, Listing $listing): RedirectResponse
    {
        $this->details->update($request->user(), $listing->id, $request->validated());

        return back()->with('status', 'Internal product details updated.');
    }
}
