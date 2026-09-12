<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptAuctionOfferRequest;
use App\Services\AuctionOrderService;
use App\Services\AuctionService;
use App\Services\CheckoutAddressService;
use App\Services\CheckoutPaymentService;
use App\Services\PurchaseTrackingSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BuyerAuctionOfferController extends Controller
{
    public function __construct(private readonly AuctionService $auctions) {}

    public function index(Request $request): Response
    {
        return Inertia::render('buyer/auction-offers/index', [
            'offers' => $this->auctions->buyerOffers($request->user()),
        ]);
    }

    public function show(Request $request, int $auctionOffer): Response
    {
        return Inertia::render('buyer/auction-offers/show', [
            'offer' => $this->auctions->buyerOffer($request->user(), $auctionOffer),
        ]);
    }

    public function accept(
        AcceptAuctionOfferRequest $request,
        int $auctionOffer,
        AuctionOrderService $orders,
        CheckoutAddressService $addresses,
        CheckoutPaymentService $payments,
        PurchaseTrackingSessionService $purchaseTracking,
    ): HttpResponse {
        $prepared = $addresses->prepare($request->validated());
        $order = $orders->accept(
            $request->user(),
            $auctionOffer,
            $prepared['shipping_address'],
            $prepared['billing_address'],
        );
        $url = $payments->start($order);

        if ($url !== null) {
            $purchaseTracking->register($request, $order);
        }

        return $url === null
            ? to_route('checkout.thank_you.show', $order->number)
            : Inertia::location($url);
    }
}
