<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Rules\ValidGtin;
use App\Support\SeoText;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MerchantFeedService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function generate(): string
    {
        $items = $this->listings->merchantProducts()
            ->flatMap(fn (Listing $listing): Collection => $this->itemsForListing($listing))
            ->filter(fn (array $item): bool => filled($item['image_link'] ?? null))
            ->map(fn (array $item): string => $this->itemXml($item))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel>'
            .'<title>'.$this->escape((string) config('app.name', 'ProDeals.lk')).'</title>'
            .'<link>'.$this->escape(route('home')).'</link>'
            .'<description>Live buy-now products and prices from ProDeals.lk</description>'
            .$items
            .'</channel></rss>';
    }

    /** @return Collection<int, non-empty-array<string, mixed>> */
    private function itemsForListing(Listing $listing): Collection
    {
        if ($listing->product_type !== 'variant') {
            return collect([$this->baseItem($listing)]);
        }

        return $listing->variants
            ->where('is_active', true)
            ->values()
            ->map(function (ListingVariant $variant) use ($listing): array {
                $item = $this->baseItem($listing);
                $selections = $variant->optionValues
                    ->sortBy(fn ($value): int => (int) $value->option->position)
                    ->mapWithKeys(fn ($value): array => [Str::lower((string) $value->option->name) => (string) $value->value]);
                $optionLabel = $selections->values()->implode(' / ');

                return [
                    ...$item,
                    'id' => 'listing-'.$listing->id.'-variant-'.$variant->id,
                    'item_group_id' => 'listing-'.$listing->id,
                    'title' => $optionLabel === '' ? $listing->title : $listing->title.' - '.$optionLabel,
                    'link' => route('listings.show', ['listing' => $listing->slug, 'variant' => $variant->id]),
                    'image_link' => $variant->image?->urlForVariant('card_2x') ?? $item['image_link'],
                    'price' => $variant->buyNowPrice(),
                    'availability' => $variant->availableQuantity() > 0 ? 'in_stock' : ($listing->allow_backorders ? 'backorder' : 'out_of_stock'),
                    'gtin' => ValidGtin::isValid($variant->gtin) ? $variant->gtin : null,
                    'mpn' => filled($variant->mpn) ? $variant->mpn : null,
                    'color' => $selections->get('color') ?? $selections->get('colour'),
                    'size' => $selections->get('size'),
                    'material' => $selections->get('material'),
                    'pattern' => $selections->get('pattern'),
                ];
            });
    }

    /** @return non-empty-array<string, mixed> */
    private function baseItem(Listing $listing): array
    {
        $images = $listing->media
            ->filter(fn ($media): bool => $media->type === 'image')
            ->map(fn ($media): string => $media->urlForVariant('card_2x'))
            ->values();

        return [
            'id' => 'listing-'.$listing->id,
            'title' => $listing->title,
            'description' => SeoText::plain((string) (filled($listing->short_description) ? $listing->short_description : $listing->description)),
            'link' => route('listings.show', $listing->slug),
            'image_link' => $images->first(),
            'additional_image_links' => $images->slice(1)->take(10)->all(),
            'price' => $listing->buyNowPrice(),
            'availability' => match ($listing->stockStatus()) {
                'backorder' => 'backorder',
                'out_of_stock' => 'out_of_stock',
                default => 'in_stock',
            },
            'condition' => match ($listing->condition) {
                'new' => 'new',
                'refurbished' => 'refurbished',
                default => 'used',
            },
            'brand' => $listing->brand?->name,
            'gtin' => ValidGtin::isValid($listing->gtin) ? $listing->gtin : null,
            'mpn' => filled($listing->mpn) ? $listing->mpn : null,
            'google_product_category' => $listing->category?->google_product_category_id,
            'product_type' => $listing->category?->name,
            'external_seller_id' => $this->externalSellerId((int) $listing->seller_profile_id),
            'return_policy_label' => 'returns-'.max(0, (int) $listing->category->return_window_days).'-days',
        ];
    }

    /** @param array<string, mixed> $item */
    private function itemXml(array $item): string
    {
        $xml = '<item>';
        $fields = [
            'id', 'item_group_id', 'title', 'description', 'link', 'image_link', 'availability', 'condition',
            'brand', 'gtin', 'mpn', 'google_product_category', 'product_type', 'external_seller_id',
            'return_policy_label', 'color', 'size', 'material', 'pattern',
        ];

        foreach ($fields as $field) {
            if (filled($item[$field] ?? null)) {
                $xml .= '<g:'.$field.'>'.$this->escape((string) $item[$field]).'</g:'.$field.'>';
            }
        }

        foreach ((array) ($item['additional_image_links'] ?? []) as $image) {
            $xml .= '<g:additional_image_link>'.$this->escape((string) $image).'</g:additional_image_link>';
        }

        $xml .= '<g:price>'.$this->escape(number_format((float) $item['price'], 2, '.', '').' LKR').'</g:price>';
        $shipping = (array) config('marketplace.seo.shipping');
        $xml .= '<g:shipping><g:country>LK</g:country><g:service>Standard</g:service><g:price>'
            .$this->escape(number_format((float) $this->shippingRate(), 2, '.', '').' LKR')
            .'</g:price><g:min_handling_time>'.$this->escape((string) $shipping['handling_days_min']).'</g:min_handling_time>'
            .'<g:max_handling_time>'.$this->escape((string) $shipping['handling_days_max']).'</g:max_handling_time>'
            .'<g:min_transit_time>'.$this->escape((string) $shipping['transit_days_min']).'</g:min_transit_time>'
            .'<g:max_transit_time>'.$this->escape((string) $shipping['transit_days_max']).'</g:max_transit_time></g:shipping>';

        if (! filled($item['gtin'] ?? null) && ! filled($item['mpn'] ?? null)) {
            $xml .= '<g:identifier_exists>no</g:identifier_exists>';
        }

        return $xml.'</item>';
    }

    private function shippingRate(): int
    {
        return $this->settings->integer(
            'checkout.shipping_fee',
            (int) config('marketplace.seo.shipping.rate', 600),
        );
    }

    private function externalSellerId(int $sellerId): string
    {
        return 'seller-'.Str::substr(hash_hmac('sha256', (string) $sellerId, (string) config('app.key')), 0, 20);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
