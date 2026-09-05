<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Services\CheckoutPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckoutPaymentController extends Controller
{
    public function __construct(private readonly CheckoutPaymentService $payments) {}

    public function retry(CustomerOrder $customerOrder): Response
    {
        Gate::authorize('view', $customerOrder);
        try {
            $url = $this->payments->start($customerOrder);
            if ($url !== null) {
                return Inertia::location($url);
            }
        } catch (Throwable $exception) {
            report($exception);

            return to_route('checkout.thank_you.show', $customerOrder->number)->withErrors(['payment' => 'We could not connect to the payment provider. Your order is saved; please try again.']);
        }

        return to_route('checkout.thank_you.show', $customerOrder->number);
    }

    public function returned(CustomerOrder $customerOrder): Response
    {
        Gate::authorize('view', $customerOrder);
        try {
            $this->payments->refresh($customerOrder);
        } catch (Throwable $exception) {
            report($exception);
        }

        return to_route('checkout.thank_you.show', $customerOrder->number);
    }

    public function webhook(Request $request): Response
    {
        $this->payments->webhook($request->getContent(), $request->header('Stripe-Signature'));

        return response()->noContent();
    }
}
