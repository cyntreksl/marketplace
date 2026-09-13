<?php

namespace App\Services;

use App\Contracts\MetaConversionsGateway;
use App\Contracts\Repositories\CustomerOrderRepository;
use App\Jobs\SendMetaConversion;
use App\Jobs\SendMetaPurchase;
use App\Models\CustomerOrder;
use App\Models\User;
use App\Support\MetaConversionEvent;
use App\Support\MetaConversionReceipt;
use App\Support\MetaParameterContext;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MetaConversionsService
{
    public function __construct(
        private readonly MetaConversionsGateway $gateway,
        private readonly CustomerOrderRepository $orders,
        private readonly MetaParameterBuilderService $parameterBuilder,
        private readonly HumanPageViewService $pageViews,
        private readonly MetaTestSessionService $testSession,
    ) {}

    public function isEnabled(): bool
    {
        return config('services.meta_conversions.enabled') === true
            && filled(config('services.meta_conversions.pixel_id'))
            && filled(config('services.meta_conversions.access_token'));
    }

    /** @param array<string, mixed> $listing */
    public function trackViewContent(Request $request, array $listing, ?int $selectedVariantId): ?string
    {
        if (! $this->isEnabled() || ! $this->pageViews->isTrackable($request)) {
            return null;
        }

        $contentId = (string) ($selectedVariantId ?? $listing['id']);
        $eventId = (string) Str::uuid();
        $price = (float) ($listing['effectivePrice'] ?? 0);
        $context = $this->parameterBuilder->process($request);

        $this->queue(new MetaConversionEvent(
            name: 'ViewContent',
            id: $eventId,
            occurredAt: now()->getTimestamp(),
            sourceUrl: $context->sourceUrl ?? $request->fullUrl(),
            userData: $this->requestUserData($request, $context),
            customData: [
                'currency' => 'LKR',
                'value' => $price,
                'content_ids' => [$contentId],
                'content_type' => 'product',
                'content_name' => (string) $listing['title'],
                'contents' => [['id' => $contentId, 'quantity' => 1, 'item_price' => $price]],
            ],
            referrerUrl: $context->referrerUrl,
        ));

        return $eventId;
    }

    /** @param array<string, mixed> $item */
    public function trackAddToCart(Request $request, array $item): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $contentId = (string) ($item['listing_variant_id'] ?? $item['listing_id']);
        $eventId = (string) Str::uuid();
        $quantity = (int) $item['quantity'];
        $unitPrice = (float) $item['unitPrice'];
        $context = $this->parameterBuilder->process($request);

        $this->queue(new MetaConversionEvent(
            name: 'AddToCart',
            id: $eventId,
            occurredAt: now()->getTimestamp(),
            sourceUrl: $context->sourceUrl ?? $request->fullUrl(),
            userData: $this->requestUserData($request, $context),
            customData: [
                'currency' => 'LKR',
                'value' => (float) $item['total'],
                'content_ids' => [$contentId],
                'content_type' => 'product',
                'content_name' => (string) data_get($item, 'listing.title'),
                'contents' => [['id' => $contentId, 'quantity' => $quantity, 'item_price' => $unitPrice]],
            ],
            referrerUrl: $context->referrerUrl,
        ));

        return $eventId;
    }

    /** @param array<string, mixed> $cart */
    public function trackInitiateCheckout(Request $request, array $cart): ?string
    {
        if (! $this->isEnabled() || $cart['items'] === [] || $cart['canCheckout'] !== true) {
            return null;
        }

        $eventId = (string) Str::uuid();
        $contents = array_map(fn (array $item): array => [
            'id' => (string) ($item['listing_variant_id'] ?? $item['listing_id']),
            'quantity' => (int) $item['quantity'],
            'item_price' => (float) $item['unitPrice'],
        ], $cart['items']);
        $context = $this->parameterBuilder->process($request);

        $this->queue(new MetaConversionEvent(
            name: 'InitiateCheckout',
            id: $eventId,
            occurredAt: now()->getTimestamp(),
            sourceUrl: $context->sourceUrl ?? $request->fullUrl(),
            userData: $this->requestUserData($request, $context),
            customData: [
                'currency' => 'LKR',
                'value' => (float) $cart['total'],
                'content_ids' => array_column($contents, 'id'),
                'content_type' => 'product',
                'num_items' => (int) $cart['quantity'],
                'contents' => $contents,
            ],
            referrerUrl: $context->referrerUrl,
        ));

        return $eventId;
    }

    /** @return array<string, string|null>|null */
    public function captureAttribution(Request $request): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $context = $this->parameterBuilder->process($request);

        return [
            'client_ip_address' => $context->clientIpAddress ?? $request->ip(),
            'client_user_agent' => $this->bounded($request->userAgent()),
            'fbp' => $context->fbp,
            'fbc' => $context->fbc,
            'source_url' => $context->sourceUrl ?? $request->fullUrl(),
            'referrer_url' => $context->referrerUrl,
            'test_event_code' => $this->testSession->consume($request),
        ];
    }

    public function trackPurchase(CustomerOrder $order): void
    {
        if (! $this->isEnabled() || $order->meta_attribution === null || $order->meta_purchase_sent_at !== null) {
            return;
        }

        try {
            SendMetaPurchase::dispatch($order->id)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Meta Purchase event could not be queued.', [
                'order_id' => $order->id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendPurchase(int $orderId): void
    {
        $order = $this->orders->findForMetaConversion($orderId);

        if ($order === null || $order->meta_attribution === null || $order->meta_purchase_sent_at !== null) {
            return;
        }

        $order = $this->orders->withMetaConversionDetails($order);
        $payment = $order->payments->firstWhere('status', 'paid') ?? $order->payments->first();
        $paidAt = $payment?->getAttribute('paid_at');
        $eventTime = $payment?->method === 'stripe' && $paidAt instanceof CarbonInterface
            ? $paidAt->getTimestamp()
            : $order->created_at->getTimestamp();
        $contents = [];

        foreach ($order->sellerOrders as $sellerOrder) {
            foreach ($sellerOrder->items as $item) {
                $contents[] = [
                    'id' => (string) ($item->listing_variant_id ?? $item->listing_id),
                    'quantity' => (int) $item->quantity,
                    'item_price' => (float) $item->unit_price,
                ];
            }
        }

        $attribution = $order->meta_attribution;
        $event = new MetaConversionEvent(
            name: 'Purchase',
            id: 'Purchase:'.$order->number,
            occurredAt: $eventTime,
            sourceUrl: $attribution['source_url'] ?? (string) config('app.url'),
            userData: $this->purchaseUserData($order, $attribution),
            customData: [
                'currency' => 'LKR',
                'value' => (float) $order->total,
                'order_id' => $order->number,
                'content_ids' => array_column($contents, 'id'),
                'content_type' => 'product',
                'num_items' => array_sum(array_column($contents, 'quantity')),
                'contents' => $contents,
            ],
            referrerUrl: $attribution['referrer_url'] ?? null,
        );
        $receipt = null;

        try {
            $receipt = $this->gateway->send($event, $attribution['test_event_code'] ?? null);
            $this->orders->markMetaPurchaseDelivered($order, $receipt);
        } catch (Throwable $exception) {
            Log::warning('Meta Purchase event delivery failed.', $this->purchaseLogContext($order, $event, $receipt) + [
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        Log::info('Meta Purchase event delivered.', $this->purchaseLogContext($order, $event, $receipt));
    }

    public function sendTest(MetaConversionEvent $event, string $testEventCode): MetaConversionReceipt
    {
        return $this->gateway->send($event, $testEventCode);
    }

    /** @return array<string, mixed> */
    private function requestUserData(Request $request, MetaParameterContext $context): array
    {
        $data = array_filter([
            'client_ip_address' => $context->clientIpAddress ?? $request->ip(),
            'client_user_agent' => $this->bounded($request->userAgent()),
            'fbp' => $context->fbp,
            'fbc' => $context->fbc,
        ], fn (?string $value): bool => filled($value));

        if ($request->user() instanceof User) {
            $this->addBuilderHash($data, 'em', $request->user()->email, MetaParameterBuilderService::PII_EMAIL);
            $this->addBuilderHash($data, 'external_id', (string) $request->user()->id, MetaParameterBuilderService::PII_EXTERNAL_ID);
        }

        return $data;
    }

    /**
     * @param  array<string, string|null>  $attribution
     * @return array<string, mixed>
     */
    private function purchaseUserData(CustomerOrder $order, array $attribution): array
    {
        $address = $order->shipping_address;
        $recipientName = (string) ($address['recipient_name'] ?? $order->buyer->name ?? 'Customer');
        [$firstName, $lastName] = $this->splitName($recipientName);
        $data = array_filter([
            'client_ip_address' => $attribution['client_ip_address'] ?? null,
            'client_user_agent' => $attribution['client_user_agent'] ?? null,
            'fbp' => $attribution['fbp'] ?? null,
            'fbc' => $attribution['fbc'] ?? null,
        ], fn (?string $value): bool => filled($value));

        $this->addBuilderHash($data, 'em', $order->contact_email, MetaParameterBuilderService::PII_EMAIL);
        if ($order->buyer !== null) {
            $this->addBuilderHash($data, 'external_id', (string) $order->buyer->id, MetaParameterBuilderService::PII_EXTERNAL_ID);
        }
        $this->addBuilderHash(
            $data,
            'ph',
            $this->parameterBuilder->normalizedSriLankanPhone($address['phone'] ?? null),
            MetaParameterBuilderService::PII_PHONE,
        );
        $this->addBuilderHash($data, 'fn', $firstName, MetaParameterBuilderService::PII_FIRST_NAME);
        $this->addBuilderHash($data, 'ln', $lastName, MetaParameterBuilderService::PII_LAST_NAME);
        $this->addBuilderHash($data, 'ct', $address['city'] ?? null, MetaParameterBuilderService::PII_CITY);
        $this->addBuilderHash($data, 'zp', $address['postal_code'] ?? null, MetaParameterBuilderService::PII_ZIP_CODE);
        $this->addBuilderHash($data, 'country', 'lk', MetaParameterBuilderService::PII_COUNTRY);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function addBuilderHash(array &$data, string $key, ?string $value, string $dataType): void
    {
        $hashed = $this->parameterBuilder->normalizedAndHashedPii($value, $dataType);

        if ($hashed !== null) {
            $data[$key] = [$hashed];
        }
    }

    /** @return array{0: string|null, 1: string|null} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function bounded(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 500 ? $value : null;
    }

    /** @return array{order_id: int, event_id: string, events_received: int|null, trace_id: string|null} */
    private function purchaseLogContext(
        CustomerOrder $order,
        MetaConversionEvent $event,
        ?MetaConversionReceipt $receipt,
    ): array {
        return [
            'order_id' => $order->id,
            'event_id' => $event->id,
            'events_received' => $receipt?->eventsReceived,
            'trace_id' => $receipt?->fbtraceId,
        ];
    }

    private function queue(MetaConversionEvent $event): void
    {
        try {
            SendMetaConversion::dispatch($event)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Meta conversion event could not be queued.', [
                'event_name' => $event->name,
                'event_id' => $event->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
