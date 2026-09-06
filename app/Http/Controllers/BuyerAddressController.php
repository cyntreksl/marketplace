<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetDefaultBuyerAddressRequest;
use App\Http\Requests\StoreBuyerAddressRequest;
use App\Http\Requests\UpdateBuyerAddressRequest;
use App\Models\BuyerAddress;
use App\Services\BuyerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuyerAddressController extends Controller
{
    public function __construct(private readonly BuyerAddressService $addresses) {}

    public function index(Request $request): Response
    {
        return Inertia::render('buyer/addresses/index', [
            'addresses' => $this->addresses->all($request->user()),
        ]);
    }

    public function store(StoreBuyerAddressRequest $request): RedirectResponse
    {
        $this->addresses->create($request->user(), $request->validated());

        return back()->with('status', 'Address saved.');
    }

    public function update(UpdateBuyerAddressRequest $request, BuyerAddress $buyerAddress): RedirectResponse
    {
        Gate::authorize('update', $buyerAddress);
        $this->addresses->update($request->user(), $buyerAddress, $request->validated());

        return back()->with('status', 'Address updated.');
    }

    public function destroy(Request $request, BuyerAddress $buyerAddress): RedirectResponse
    {
        Gate::authorize('delete', $buyerAddress);
        $this->addresses->delete($request->user(), $buyerAddress);

        return back()->with('status', 'Address removed.');
    }

    public function setDefault(SetDefaultBuyerAddressRequest $request, BuyerAddress $buyerAddress): RedirectResponse
    {
        Gate::authorize('update', $buyerAddress);
        $this->addresses->setDefault($request->user(), $buyerAddress, (string) $request->validated('purpose'));

        return back()->with('status', 'Default address updated.');
    }
}
