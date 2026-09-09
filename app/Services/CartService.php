<?php

namespace App\Services;

use App\Contracts\Repositories\CartRepository;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @phpstan-type CartSummary array{items: list<array<string, mixed>>, subtotal: string, shippingTotal: string, total: string, quantity: int, canCheckout: bool, paymentMethods: list<string>} */
class CartService
{
    public function __construct(
        private readonly CartRepository $carts,
        private readonly MarketplaceSettingsService $settings,
        private readonly ListingPricingService $pricing,
        private readonly CashOnDeliveryService $cashOnDelivery,
    ) {}

    /** @return CartSummary */
    public function summary(Request $request): array
    {
        $this->mergeGuest($request);
        $entries = $request->user() === null
            ? array_values($request->session()->get('guest_cart', []))
            : $this->carts->forBuyer($request->user())->items->toArray();

        return $this->summarize($entries);
    }

    /** @param array<int, array<string, mixed>> $entries
     * @return CartSummary
     */
    public function summarize(array $entries): array
    {
        $listings = $this->carts->listings(array_values(array_unique(array_column($entries, 'listing_id'))));
        $subtotal = BigDecimal::zero();
        $items = [];
        foreach ($entries as $entry) {
            $listing = $listings->get($entry['listing_id']);
            $variant = $listing?->variants->firstWhere('id', $entry['listing_variant_id'] ?? null);
            $error = null;
            if ($listing === null || $listing->listing_type !== 'buy_now') {
                $error = 'This item is no longer available. Remove it to continue.';
            } elseif (($listing->product_type === 'variant' && ($variant === null || ! $variant->is_active)) || ($listing->product_type !== 'variant' && ($entry['listing_variant_id'] ?? null) !== null)) {
                $error = 'This product option is no longer available. Remove it and choose another option.';
            }
            $pricing = $listing === null
                ? null
                : $this->pricing->forQuantity($listing, $variant, (int) $entry['quantity']);
            $price = $pricing['unitPrice'] ?? null;
            $available = max(0, $variant?->availableQuantity() ?? (($listing->stock_quantity ?? 0) - ($listing->reserved_quantity ?? 0)));
            if ($error === null && $price === null) {
                $minimum = $this->pricing->wholesaleMinimum($listing, $variant);
                $error = $minimum === null
                    ? 'This quantity is no longer available.'
                    : "This wholesale item requires at least {$minimum} units.";
            } elseif ($error === null && ! $listing->allow_backorders && $entry['quantity'] > $available) {
                $error = $available === 0
                    ? 'This item is out of stock.'
                    : 'This quantity is no longer available.';
            }
            if ($entry['quantity'] > 100000) {
                $error = 'Choose no more than 100,000 of each item.';
            }
            $lineTotal = BigDecimal::of($price ?? '0')->multipliedBy($entry['quantity'])->toScale(2);
            $subtotal = $subtotal->plus($lineTotal);
            $items[] = [
                'id' => $entry['id'],
                'listing_id' => $entry['listing_id'],
                'listing_variant_id' => $entry['listing_variant_id'] ?? null,
                'selection_key' => $entry['selection_key'],
                'quantity' => $entry['quantity'],
                'unitPrice' => $price ?? '0.00',
                'pricingTier' => $pricing['tier'] ?? null,
                'appliedTierMinimumQuantity' => $pricing['appliedTierMinimumQuantity'] ?? null,
                'minimumQuantity' => $pricing['minimumQuantity'] ?? ($listing === null ? 1 : ($this->pricing->wholesaleMinimum($listing, $variant) ?? 1)),
                'total' => (string) $lineTotal,
                'error' => $error,
                'availableQuantity' => $listing?->allow_backorders ? 100000 : min(100000, $available),
                'variant' => $variant === null ? null : [
                    'sku' => $variant->sku,
                    'selling_price' => $price,
                    'option_values' => $variant->optionValues->map(fn ($value): array => [
                        'value' => $value->value,
                        'option' => ['name' => $value->option->name],
                    ])->all(),
                ],
                'listing' => [
                    'title' => $listing->title ?? data_get($entry, 'listing.title', 'Unavailable item'),
                    'slug' => $listing?->slug,
                    'price' => $price ?? '0.00',
                    'sale_price' => null,
                    'media' => $listing?->media->map(fn ($media): array => [
                        'cardUrl' => $media->urlForVariant('card'),
                        'card2xUrl' => $media->urlForVariant('card_2x'),
                    ])->all() ?? [],
                    'seller_profile' => ['store_name' => $listing->sellerProfile->store_name ?? 'Marketplace seller'],
                ],
            ];
        }
        $shipping = $items === [] ? 0 : max(0, $this->settings->integer('checkout.shipping_fee', 600));
        $total = $subtotal->plus($shipping)->toScale(2);

        return [
            'items' => $items,
            'subtotal' => (string) $subtotal->toScale(2),
            'shippingTotal' => (string) BigDecimal::of($shipping)->toScale(2),
            'total' => (string) $total,
            'quantity' => array_sum(array_column($items, 'quantity')),
            'canCheckout' => $items !== [] && ! array_filter(array_column($items, 'error')),
            'paymentMethods' => array_values(array_filter([
                'cod' => $this->cashOnDelivery->allows((string) $total) ? 'cod' : null,
                'stripe' => config('services.stripe.secret') && config('services.stripe.webhook_secret') ? 'stripe' : null,
            ])),
        ];
    }

    public function mergeGuest(Request $request): void
    {
        $items = $request->session()->get('guest_cart', []);
        if ($request->user() !== null && $items !== []) {
            $this->carts->merge($request->user(), array_values($items), $request->session()->get('guest_cart_token', (string) Str::uuid()));
            $request->session()->forget(['guest_cart', 'guest_cart_token']);
        }
    }

    /** @return array<string, mixed> */
    public function add(Request $request, int $listingId, ?int $variantId, int $quantity): array
    {
        return DB::transaction(function () use ($request, $listingId, $variantId, $quantity): array {
            if ($request->user() !== null) {
                $this->carts->lock($request->user());
            }
            $summary = $this->summary($request);
            $listing = $this->carts->listings([$listingId])->get($listingId);
            abort_if($listing === null, 404);
            $variant = $listing->variants->firstWhere('id', $variantId);
            $key = $variant->combination_key ?? 'base';
            $existing = collect($summary['items'])->first(fn (array $item): bool => $item['listing_id'] === $listingId && $item['listing_variant_id'] === $variantId);
            $entry = ['id' => $existing['id'] ?? $listingId.'-'.($variantId ?? 'base'), 'listing_id' => $listingId, 'listing_variant_id' => $variantId, 'selection_key' => $key, 'quantity' => ($existing['quantity'] ?? 0) + $quantity];
            $this->validateEntry($entry);
            if ($request->user() !== null) {
                $this->carts->setQuantity($request->user(), $listingId, $variantId, $key, $entry['quantity']);
            } else {
                $request->session()->put('guest_cart.'.$entry['id'], $entry);
                if (! $request->session()->has('guest_cart_token')) {
                    $request->session()->put('guest_cart_token', (string) Str::uuid());
                }
            }

            return $this->summarize([array_replace($entry, ['quantity' => $quantity])])['items'][0];
        });
    }

    public function update(Request $request, string $itemId, ?int $quantity): void
    {
        DB::transaction(function () use ($request, $itemId, $quantity): void {
            if ($request->user() !== null) {
                $this->carts->lock($request->user());
            }
            $item = collect($this->summary($request)['items'])->first(fn (array $item): bool => (string) $item['id'] === $itemId);
            abort_if($item === null, 404);
            if ($quantity !== null) {
                $item['quantity'] = $quantity;
                $this->validateEntry($item);
            }
            if ($request->user() !== null) {
                if ($quantity === null) {
                    $this->carts->remove($request->user(), (int) $itemId);
                } else {
                    $this->carts->setQuantity($request->user(), $item['listing_id'], $item['listing_variant_id'], $item['selection_key'], $quantity);
                }
            } elseif ($quantity === null) {
                $request->session()->forget('guest_cart.'.$itemId);
            } else {
                $request->session()->put('guest_cart.'.$itemId.'.quantity', $quantity);
            }
        });
    }

    /** @param array<string, mixed> $entry */
    private function validateEntry(array $entry): void
    {
        if ($entry['quantity'] < 1) {
            throw ValidationException::withMessages(['quantity' => 'Choose at least one item.']);
        }
        $item = $this->summarize([$entry])['items'][0];
        if ($item['error'] !== null) {
            throw ValidationException::withMessages([str_contains($item['error'], 'option') ? 'listing_variant_id' : 'quantity' => $item['error']]);
        }
    }
}
