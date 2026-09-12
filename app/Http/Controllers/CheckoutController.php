<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CheckoutRepository;
use App\Http\Requests\CheckoutPaymentRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\CustomerOrder;
use App\Models\User;
use App\Services\BuyerAddressService;
use App\Services\CartService;
use App\Services\CashOnDeliveryService;
use App\Services\CheckoutAddressService;
use App\Services\CheckoutPaymentService;
use App\Services\CheckoutService;
use App\Services\GuestOrderAccessService;
use App\Services\MetaConversionsService;
use App\Services\PurchaseTrackingSessionService;
use App\Support\CheckoutContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    private const array PAYMENT_METHODS = ['stripe', 'cod'];

    public function __construct(
        private readonly CartService $carts,
        private readonly CashOnDeliveryService $cashOnDelivery,
        private readonly CheckoutRepository $orders,
        private readonly CheckoutPaymentService $payments,
        private readonly CheckoutService $checkout,
        private readonly CheckoutAddressService $checkoutAddresses,
        private readonly BuyerAddressService $buyerAddresses,
        private readonly MetaConversionsService $metaConversions,
        private readonly PurchaseTrackingSessionService $purchaseTracking,
        private readonly GuestOrderAccessService $guestOrderAccess,
    ) {}

    public function show(Request $request): Response
    {
        $request->session()->forget('checkout_intended');
        $buyer = $this->currentBuyer($request);

        $shippingAddress = $request->session()->get('checkout.shipping_address');
        $billingAddress = $request->session()->get('checkout.billing_address');

        if (! is_array($shippingAddress) && $buyer !== null) {
            $shippingAddress = $this->buyerAddresses->selected($buyer, null, 'shipping')?->snapshot();
        }

        if (! is_array($billingAddress) && $buyer !== null) {
            $billingAddress = $this->buyerAddresses->selected($buyer, null, 'billing')?->snapshot();
        }

        $cart = $this->carts->summary($request);
        $metaEventId = $this->metaConversions->trackInitiateCheckout($request, $cart);

        return Inertia::render('buyer/checkout', [
            'cart' => $cart,
            'metaEventId' => $metaEventId,
            'shippingAddress' => $shippingAddress,
            'billingAddress' => $billingAddress,
            'contactEmail' => $this->contactEmail($request) ?? '',
            'customerName' => $shippingAddress['recipient_name'] ?? $buyer->name ?? '',
            'isGuest' => $buyer === null,
            'savedAddresses' => $buyer === null ? [] : $this->buyerAddresses->all($buyer),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $buyer = $this->currentBuyer($request);
        $preparedAddresses = $this->checkoutAddresses->prepare($request->validated());

        foreach ($preparedAddresses as $key => $address) {
            $request->session()->put('checkout.'.$key, $address);
        }

        $request->session()->put('checkout.contact_email', $buyer->email ?? Str::lower((string) $request->validated('email')));
        $request->session()->put('checkout.marketing_opt_in', $request->boolean('marketing_opt_in'));

        if ($buyer !== null && $request->boolean('save_shipping_address')) {
            $this->buyerAddresses->create($buyer, [
                ...$preparedAddresses['shipping_address'],
                'label' => $request->validated('shipping_address_label'),
                'shipping_enabled' => true,
                'billing_enabled' => $request->boolean('save_shipping_for_billing'),
            ]);
        }

        if ($buyer !== null && $request->boolean('save_billing_address') && is_array($preparedAddresses['billing_address'])) {
            $this->buyerAddresses->create($buyer, [
                ...$preparedAddresses['billing_address'],
                'label' => $request->validated('billing_address_label'),
                'shipping_enabled' => false,
                'billing_enabled' => true,
            ]);
        }

        return to_route('checkout.payment.show');
    }

    public function showPayment(Request $request): Response|RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress) || $this->contactEmail($request) === null) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before choosing a payment method.']);
        }

        return Inertia::render('buyer/payment', [
            'cart' => $this->carts->summary($request),
            'shippingAddress' => $shippingAddress,
            'paymentMethod' => $this->paymentMethod($request),
        ]);
    }

    public function storePayment(CheckoutPaymentRequest $request): RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress) || $this->contactEmail($request) === null) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before choosing a payment method.']);
        }

        $paymentMethod = (string) $request->validated('payment_method');
        $this->cashOnDelivery->ensureAllowed($paymentMethod, $this->carts->summary($request)['total']);
        $request->session()->put('checkout.payment_method', $paymentMethod);

        return to_route('checkout.review.show');
    }

    public function showReview(Request $request): Response|RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress) || $this->contactEmail($request) === null) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before reviewing your order.']);
        }

        $paymentMethod = $this->paymentMethod($request);

        if ($paymentMethod === null) {
            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Choose a payment method before reviewing your order.']);
        }

        $cart = $this->carts->summary($request);
        if ($paymentMethod === 'cod' && ! $this->cashOnDelivery->allows($cart['total'])) {
            $request->session()->forget('checkout.payment_method');

            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Cash on delivery is unavailable for this total, including delivery. Choose another payment method.']);
        }
        $token = $request->session()->get('checkout.token', (string) Str::uuid());
        $request->session()->put('checkout.token', $token);
        $this->guestOrderAccess->identityHash($request);
        $this->guestOrderAccess->checkoutAccessToken($request);

        return Inertia::render('buyer/review', [
            'checkoutToken' => $token,
            'reviewHash' => $this->checkout->reviewHash($cart),
            'billingAddress' => $request->session()->get('checkout.billing_address'),
            'cart' => $cart,
            'shippingAddress' => $shippingAddress,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function placeOrder(PlaceOrderRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $buyer = $this->currentBuyer($request);
        $checkoutToken = (string) $request->validated('checkout_token');
        $guestIdentityHash = $this->guestOrderAccess->submissionIdentityHash($request, $checkoutToken);
        $guestAccessToken = $this->guestOrderAccess->submissionAccessToken($request, $checkoutToken);
        $existing = $this->orders->findSubmission($buyer, $checkoutToken, $guestIdentityHash);
        if ($existing !== null) {
            $this->guestOrderAccess->remember($request, $existing, $guestAccessToken);

            return redirect()->to($this->guestOrderAccess->confirmationUrl($existing, $guestAccessToken));
        }
        abort_unless($request->session()->get('checkout.token') === $checkoutToken, 419);
        $shippingAddress = $request->session()->get('checkout.shipping_address');
        $contactEmail = $this->contactEmail($request);

        if (! is_array($shippingAddress) || $contactEmail === null) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before placing your order.']);
        }

        $paymentMethod = $this->paymentMethod($request);

        if ($paymentMethod === null) {
            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Choose a payment method before placing your order.']);
        }

        $order = $this->checkout->checkout(
            new CheckoutContext(
                buyer: $buyer,
                contactEmail: $contactEmail,
                marketingOptIn: (bool) $request->session()->get('checkout.marketing_opt_in', false),
                guestCartEntries: $buyer === null ? $this->carts->entries($request) : [],
                guestIdentityHash: $guestIdentityHash,
                guestAccessToken: $guestAccessToken,
            ),
            $paymentMethod,
            $shippingAddress,
            $checkoutToken,
            $request->validated('review_hash'),
            $request->session()->get('checkout.billing_address'),
            $this->metaConversions->captureAttribution($request),
        );
        $this->purchaseTracking->register($request, $order);
        $this->guestOrderAccess->rememberSubmission($request, $checkoutToken, $guestIdentityHash, $guestAccessToken);
        $this->guestOrderAccess->remember($request, $order, $guestAccessToken);
        $request->session()->forget('checkout');
        if ($buyer === null) {
            $request->session()->forget(['guest_cart', 'guest_cart_token']);
        }

        if ($paymentMethod === 'stripe') {
            try {
                $url = $this->payments->start($order, $guestAccessToken);
                if ($url !== null) {
                    return Inertia::location($url);
                }
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->to($this->guestOrderAccess->confirmationUrl($order, $guestAccessToken))->withErrors(['payment' => 'Your order is saved. Please retry card payment.']);
            }
        }

        return redirect()->to($this->guestOrderAccess->confirmationUrl($order, $guestAccessToken));
    }

    public function thankYou(Request $request, CustomerOrder $customerOrder): Response
    {
        $this->guestOrderAccess->authorize($request, $customerOrder);
        $this->guestOrderAccess->remember($request, $customerOrder, $this->guestOrderAccess->token($request, $customerOrder));

        return Inertia::render('buyer/thank-you', [
            'order' => $this->checkout->confirmationSummary($customerOrder),
            'shouldTrackPurchase' => $this->purchaseTracking->consume($request, $customerOrder),
            'isGuestOrder' => $customerOrder->buyer_id === null,
            'claimUrl' => $customerOrder->buyer_id === null ? $this->guestOrderAccess->claimUrl($customerOrder) : null,
        ]);
    }

    private function paymentMethod(Request $request): ?string
    {
        $paymentMethod = $request->session()->get('checkout.payment_method');

        return is_string($paymentMethod) && in_array($paymentMethod, self::PAYMENT_METHODS, true)
            ? $paymentMethod
            : null;
    }

    private function contactEmail(Request $request): ?string
    {
        $email = $this->currentBuyer($request)->email ?? $request->session()->get('checkout.contact_email');

        return is_string($email) && $email !== '' ? Str::lower($email) : null;
    }

    private function currentBuyer(Request $request): ?User
    {
        $buyer = $request->user();

        return $buyer instanceof User ? $buyer : null;
    }
}
