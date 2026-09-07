<?php

namespace App\Services;

use App\Contracts\MetaConversionsGateway;
use App\Contracts\Repositories\CustomerOrderRepository;
use App\Jobs\SendMetaConversion;
use App\Jobs\SendMetaPurchase;
use App\Models\CustomerOrder;
use App\Models\User;
use App\Support\MetaConversionEvent;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MetaConversionsService
{
    private const string CRAWLER_PATTERN = '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|facebot|headless|lighthouse|pagespeed|preview/i';

    public function __construct(
        private readonly MetaConversionsGateway $gateway,
        private readonly CustomerOrderRepository $orders,
    ) {}

    public function isEnabled(): bool
    {
        return config('services.meta_conversions.enabled') === true
            && filled(config('services.meta_conversions.pixel_id'))
            && filled(config('services.meta_conversions.access_token'));
    }

    /** @param array<string, mixed> $listing */
    public function trackViewContent(Request $request, array $listing, ?int $selectedVariantId): void
    {
        if (! $this->isEnabled() || $this->shouldIgnoreListingView($request)) {
            return;
        }

        $contentId = (string) ($selectedVariantId ?? $listing['id']);
        $price = (string) ($listing['effectivePrice'] ?? '0.00');

        $this->queue(new MetaConversionEvent(
            name: 'ViewContent',
            id: (string) Str::uuid(),
            occurredAt: now()->getTimestamp(),
            sourceUrl: $request->fullUrl(),
            userData: $this->requestUserData($request),
            customData: [
                'currency' => 'LKR',
                'value' => $price,
                'content_ids' => [$contentId],
                'content_type' => 'product',
                'content_name' => (string) $listing['title'],
                'contents' => [['id' => $contentId, 'quantity' => 1, 'item_price' => $price]],
            ],
        ));
    }

    /** @param array<string, mixed> $item */
    public function trackAddToCart(Request $request, array $item): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $contentId = (string) ($item['listing_variant_id'] ?? $item['listing_id']);
        $quantity = (int) $item['quantity'];
        $unitPrice = (string) $item['unitPrice'];

        $this->queue(new MetaConversionEvent(
            name: 'AddToCart',
            id: (string) Str::uuid(),
            occurredAt: now()->getTimestamp(),
            sourceUrl: $request->fullUrl(),
            userData: $this->requestUserData($request),
            customData: [
                'currency' => 'LKR',
                'value' => (string) $item['total'],
                'content_ids' => [$contentId],
                'content_type' => 'product',
                'content_name' => (string) data_get($item, 'listing.title'),
                'contents' => [['id' => $contentId, 'quantity' => $quantity, 'item_price' => $unitPrice]],
            ],
        ));
    }

    /** @param array<string, mixed> $cart */
    public function trackInitiateCheckout(Request $request, array $cart): void
    {
        if (! $this->isEnabled() || $cart['items'] === [] || $cart['canCheckout'] !== true) {
            return;
        }

        $contents = array_map(fn (array $item): array => [
            'id' => (string) ($item['listing_variant_id'] ?? $item['listing_id']),
            'quantity' => (int) $item['quantity'],
            'item_price' => (string) $item['unitPrice'],
        ], $cart['items']);

        $this->queue(new MetaConversionEvent(
            name: 'InitiateCheckout',
            id: (string) Str::uuid(),
            occurredAt: now()->getTimestamp(),
            sourceUrl: $request->fullUrl(),
            userData: $this->requestUserData($request),
            customData: [
                'currency' => 'LKR',
                'value' => (string) $cart['total'],
                'content_ids' => array_column($contents, 'id'),
                'content_type' => 'product',
                'num_items' => (int) $cart['quantity'],
                'contents' => $contents,
            ],
        ));
    }

    /** @return array<string, string|null>|null */
    public function captureAttribution(Request $request): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $fbc = $this->bounded($request->cookie('_fbc'));
        $fbclid = $this->bounded($request->query('fbclid'));

        if ($fbc === null && $fbclid !== null && preg_match('/^[A-Za-z0-9._-]+$/', $fbclid) === 1) {
            $fbc = 'fb.1.'.now()->getTimestampMs().'.'.$fbclid;
        }

        return [
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $this->bounded($request->userAgent()),
            'fbp' => $this->bounded($request->cookie('_fbp')),
            'fbc' => $fbc,
            'source_url' => $request->fullUrl(),
        ];
    }

    public function trackPurchase(CustomerOrder $order): void
    {
        if (! $this->isEnabled() || $order->meta_attribution === null) {
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

        if ($order === null || $order->meta_attribution === null) {
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
                    'item_price' => (string) $item->unit_price,
                ];
            }
        }

        $attribution = $order->meta_attribution;
        $this->gateway->send(new MetaConversionEvent(
            name: 'Purchase',
            id: 'Purchase:'.$order->number,
            occurredAt: $eventTime,
            sourceUrl: $attribution['source_url'] ?? (string) config('app.url'),
            userData: $this->purchaseUserData($order, $attribution),
            customData: [
                'currency' => 'LKR',
                'value' => $order->total,
                'order_id' => $order->number,
                'content_ids' => array_column($contents, 'id'),
                'content_type' => 'product',
                'num_items' => array_sum(array_column($contents, 'quantity')),
                'contents' => $contents,
            ],
        ));
        $this->orders->clearMetaAttribution($order);
    }

    public function sendTest(MetaConversionEvent $event, string $testEventCode): void
    {
        $this->gateway->send($event, $testEventCode);
    }

    /** @return array<string, mixed> */
    private function requestUserData(Request $request): array
    {
        $data = array_filter($this->captureAttribution($request) ?? [], fn (?string $value): bool => filled($value));
        unset($data['source_url']);

        if ($request->user() instanceof User) {
            $data['em'] = [$this->hash($request->user()->email)];
            $data['external_id'] = [$this->hash((string) $request->user()->id)];
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
        $recipientName = (string) ($address['recipient_name'] ?? $order->buyer->name);
        [$firstName, $lastName] = $this->splitName($recipientName);
        $data = array_filter([
            'client_ip_address' => $attribution['client_ip_address'] ?? null,
            'client_user_agent' => $attribution['client_user_agent'] ?? null,
            'fbp' => $attribution['fbp'] ?? null,
            'fbc' => $attribution['fbc'] ?? null,
        ], fn (?string $value): bool => filled($value));

        $this->addHashed($data, 'em', $order->buyer->email);
        $this->addHashed($data, 'external_id', (string) $order->buyer->id);
        $this->addHashed($data, 'ph', $address['phone'] ?? null, digitsOnly: true);
        $this->addHashed($data, 'fn', $firstName);
        $this->addHashed($data, 'ln', $lastName);
        $this->addHashed($data, 'ct', $address['city'] ?? null);
        $this->addHashed($data, 'zp', $address['postal_code'] ?? null);
        $this->addHashed($data, 'country', 'lk');

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function addHashed(array &$data, string $key, ?string $value, bool $digitsOnly = false): void
    {
        $normalized = $this->normalize($value, $digitsOnly);

        if ($normalized !== null) {
            $data[$key] = [hash('sha256', $normalized)];
        }
    }

    private function hash(string $value): string
    {
        return hash('sha256', $this->normalize($value) ?? '');
    }

    private function normalize(?string $value, bool $digitsOnly = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = Str::lower(trim($value));
        $normalized = $digitsOnly
            ? preg_replace('/\D+/', '', $normalized)
            : preg_replace('/\s+/', '', $normalized);

        if ($digitsOnly && is_string($normalized) && strlen($normalized) === 10 && str_starts_with($normalized, '0')) {
            $normalized = '94'.substr($normalized, 1);
        }

        return filled($normalized) ? $normalized : null;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function shouldIgnoreListingView(Request $request): bool
    {
        $purpose = implode(' ', array_filter([
            $request->header('Purpose'),
            $request->header('Sec-Purpose'),
            $request->header('X-Purpose'),
        ]));
        $userAgent = $request->userAgent();

        return str_contains(Str::lower($purpose), 'prefetch')
            || str_contains(Str::lower($purpose), 'prerender')
            || ! is_string($userAgent)
            || $userAgent === ''
            || preg_match(self::CRAWLER_PATTERN, $userAgent) === 1;
    }

    private function bounded(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 500 ? $value : null;
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
