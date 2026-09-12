<?php

namespace App\Services;

use App\AuctionOfferStatus;
use App\Contracts\Repositories\AuctionRepository;
use App\Contracts\Repositories\CheckoutRepository;
use App\Models\CustomerOrder;
use App\Models\ListingVariant;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuctionOrderService
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly CheckoutRepository $orders,
        private readonly MarketplaceSettingsService $settings,
        private readonly PaymentAttemptService $paymentAttempts,
        private readonly AuditLogService $auditLogs,
    ) {}

    /**
     * @param  array<string, string|null>  $shippingAddress
     * @param  array<string, string|null>|null  $billingAddress
     */
    public function accept(User $buyer, int $offerId, array $shippingAddress, ?array $billingAddress = null): CustomerOrder
    {
        return DB::transaction(function () use ($buyer, $offerId, $shippingAddress, $billingAddress): CustomerOrder {
            $offer = $this->auctions->offerForBuyerOrFail($buyer, $offerId, true);
            if ($offer->order !== null) {
                return $this->orders->details($offer->order);
            }

            if ($offer->status !== AuctionOfferStatus::Offered || $offer->expires_at === null || $offer->expires_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['offer' => 'This auction offer is no longer available.']);
            }

            $auction = $offer->auction;
            $listing = $auction->listing;
            $variant = $auction->variant;
            $subtotal = BigDecimal::of($offer->unit_price)->multipliedBy($offer->quantity);
            $shipping = BigDecimal::of($this->settings->integer('checkout.shipping_fee', 600));
            $total = $subtotal->plus($shipping);
            $commissionPercentage = (string) ($listing->commission_percentage ?? '0');
            $commission = $subtotal->multipliedBy($commissionPercentage)->dividedBy(100, 2, RoundingMode::Down);
            $order = $this->orders->createOrder([
                'number' => (string) Str::uuid(),
                'buyer_id' => $buyer->id,
                'contact_email' => $buyer->email,
                'marketing_opt_in' => false,
                'auction_offer_id' => $offer->id,
                'status' => 'pending_payment',
                'subtotal' => (string) $subtotal->toScale(2),
                'shipping_total' => (string) $shipping->toScale(2),
                'total' => (string) $total->toScale(2),
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress ?? $shippingAddress,
            ]);
            $sellerOrder = $this->orders->createSellerOrder([
                'number' => $this->orderNumber('SO'),
                'customer_order_id' => $order->id,
                'seller_profile_id' => $listing->seller_profile_id,
                'status' => 'pending_payment',
                'subtotal' => (string) $subtotal->toScale(2),
                'shipping_charge' => (string) $shipping->toScale(2),
                'seller_earnings' => (string) $subtotal->minus($commission)->toScale(2),
            ]);
            $this->orders->addItem($sellerOrder, [
                'listing_id' => $listing->id,
                'listing_variant_id' => $variant?->id,
                'title' => $listing->title,
                'variant_sku' => $variant?->sku,
                'variant_options' => $variant === null ? null : $this->variantOptions($variant),
                'quantity' => $offer->quantity,
                'unit_price' => $offer->unit_price,
                'pricing_tier' => 'auction',
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => (string) $commission,
                'total' => (string) $subtotal->toScale(2),
            ]);
            $payment = $this->orders->createPayment([
                'customer_order_id' => $order->id,
                'method' => 'stripe',
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => (string) $total->toScale(2),
                'expires_at' => $offer->expires_at,
            ]);
            $this->paymentAttempts->begin($payment);
            $this->auctions->saveOffer($offer, [
                'status' => AuctionOfferStatus::Accepted,
                'accepted_at' => now(),
            ]);
            $this->auditLogs->record($buyer, 'auction.offer_accepted', $offer, after: ['customer_order_id' => $order->id]);

            return $this->orders->details($order);
        }, attempts: 3);
    }

    private function orderNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    /** @return array<string, string> */
    private function variantOptions(ListingVariant $variant): array
    {
        return $variant->optionValues
            ->sortBy(fn ($value) => $value->option->position)
            ->mapWithKeys(fn ($value): array => [$value->option->name => $value->value])
            ->all();
    }
}
