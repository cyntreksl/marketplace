<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Services\CheckoutPaymentService;
use App\Services\GuestOrderAccessService;
use App\Services\PurchaseTrackingSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckoutPaymentController extends Controller
{
    public function __construct(
        private readonly CheckoutPaymentService $payments,
        private readonly PurchaseTrackingSessionService $purchaseTracking,
        private readonly GuestOrderAccessService $guestOrderAccess,
    ) {}

    public function retry(Request $request, CustomerOrder $customerOrder): Response
    {
        $this->guestOrderAccess->authorizePayment($request, $customerOrder);
        $accessToken = $this->guestOrderAccess->token($request, $customerOrder);
        try {
            $url = $this->payments->start($customerOrder, $accessToken);
            if ($url !== null) {
                $this->purchaseTracking->register($request, $customerOrder);

                return Inertia::location($url);
            }
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->to($this->guestOrderAccess->confirmationUrl($customerOrder, $accessToken))->withErrors(['payment' => 'We could not connect to the payment provider. Your order is saved; please try again.']);
        }

        return redirect()->to($this->guestOrderAccess->confirmationUrl($customerOrder, $accessToken));
    }

    public function returned(Request $request, CustomerOrder $customerOrder): Response
    {
        $this->guestOrderAccess->authorizePayment($request, $customerOrder);
        $accessToken = $this->guestOrderAccess->token($request, $customerOrder);
        try {
            $this->payments->refresh($customerOrder);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->to($this->guestOrderAccess->confirmationUrl($customerOrder, $accessToken));
    }

    public function webhook(Request $request): Response
    {
        $this->payments->webhook($request->getContent(), $request->header('Stripe-Signature'));

        return response()->noContent();
    }
}
