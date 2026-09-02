<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Rules\ValidGtin;
use Illuminate\Support\Str;

class ProductStructuredDataService
{
    /** @param array<int, array{name: string, slug: string}> $categoryTrail
     * @return array<int, array<string, mixed>>
     */
    public function forListing(Listing $listing, array $categoryTrail): array
    {
        $breadcrumbs = $this->breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ...collect($categoryTrail)->map(fn (array $category): array => [
                'name' => $category['name'],
                'url' => route('categories.show', $category['slug']),
            ])->all(),
            ['name' => (string) $listing->title, 'url' => route('listings.show', $listing->slug)],
        ]);

        if ($listing->listing_type !== 'buy_now') {
            return [$breadcrumbs];
        }

        return [
            $listing->product_type === 'variant'
                ? $this->productGroup($listing, $categoryTrail)
                : $this->product($listing, $categoryTrail),
            $breadcrumbs,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function homepage(): array
    {
        $sameAs = collect((array) config('marketplace.social_urls', []))->filter()->values()->all();
        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => route('home').'#organization',
            'name' => (string) config('app.name', 'ProDeals.lk'),
            'url' => route('home'),
            'logo' => app(StaticMediaService::class)->url('prodeals-logo.svg'),
            'email' => config('marketplace.support.email'),
            'contactPoint' => $this->withoutEmpty([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'email' => config('marketplace.support.email'),
                'telephone' => config('marketplace.support.phone'),
                'areaServed' => 'LK',
                'availableLanguage' => 'en-LK',
            ]),
            'areaServed' => ['@type' => 'Country', 'name' => 'LK'],
            'sameAs' => $sameAs,
        ];

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => route('home').'#website',
            'url' => route('home'),
            'name' => (string) config('app.name', 'ProDeals.lk'),
            'publisher' => ['@id' => route('home').'#organization'],
            'inLanguage' => 'en-LK',
        ];

        return [$this->withoutEmpty($organization), $website];
    }

    /** @param array<int, array{name: string, url: string}> $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /** @param array<int, array{name: string, slug: string}> $categoryTrail
     * @return array<string, mixed>
     */
    private function product(Listing $listing, array $categoryTrail): array
    {
        return $this->withoutEmpty([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => route('listings.show', $listing->slug).'#product',
            'url' => route('listings.show', $listing->slug),
            'name' => $listing->title,
            'description' => $this->plainDescription($listing),
            'image' => $this->listingImages($listing),
            'brand' => $listing->brand === null ? null : ['@type' => 'Brand', 'name' => $listing->brand->name],
            'sku' => $listing->sku,
            'model' => $listing->model,
            ...$this->identifier($listing->gtin, $listing->mpn),
            'category' => $this->categories($listing, $categoryTrail),
            'aggregateRating' => $this->rating($listing),
            'offers' => $this->offer(
                listing: $listing,
                url: route('listings.show', $listing->slug),
                price: $listing->buyNowPrice(),
                marketPrice: $listing->sale_price === null ? null : (string) $listing->price,
                availability: $listing->stockStatus(),
            ),
        ]);
    }

    /** @param array<int, array{name: string, slug: string}> $categoryTrail
     * @return array<string, mixed>
     */
    private function productGroup(Listing $listing, array $categoryTrail): array
    {
        $recognizedProperties = [
            'color' => 'https://schema.org/color',
            'colour' => 'https://schema.org/color',
            'size' => 'https://schema.org/size',
            'material' => 'https://schema.org/material',
            'pattern' => 'https://schema.org/pattern',
        ];
        $variesBy = $listing->variantOptions
            ->map(fn ($option): ?string => $recognizedProperties[Str::lower(trim((string) $option->name))] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->withoutEmpty([
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            '@id' => route('listings.show', $listing->slug).'#product-group',
            'url' => route('listings.show', $listing->slug),
            'name' => $listing->title,
            'description' => $this->plainDescription($listing),
            'image' => $this->listingImages($listing),
            'brand' => $listing->brand === null ? null : ['@type' => 'Brand', 'name' => $listing->brand->name],
            'productGroupID' => $listing->sku,
            'category' => $this->categories($listing, $categoryTrail),
            'aggregateRating' => $this->rating($listing),
            'variesBy' => $variesBy,
            'hasVariant' => $listing->variants
                ->where('is_active', true)
                ->values()
                ->map(fn (ListingVariant $variant): array => $this->variantProduct($listing, $variant, $recognizedProperties))
                ->all(),
        ]);
    }

    /** @param array<string, string> $recognizedProperties
     * @return array<string, mixed>
     */
    private function variantProduct(Listing $listing, ListingVariant $variant, array $recognizedProperties): array
    {
        $selections = $variant->optionValues
            ->sortBy(fn ($value) => $value->option->position)
            ->mapWithKeys(fn ($value): array => [Str::lower(trim((string) $value->option->name)) => (string) $value->value]);
        $properties = [];

        foreach ($selections as $name => $value) {
            if (isset($recognizedProperties[$name])) {
                $properties[Str::after($recognizedProperties[$name], 'https://schema.org/')] = $value;
            }
        }

        $url = route('listings.show', ['listing' => $listing->slug, 'variant' => $variant->id]);
        $image = $variant->image === null
            ? $this->listingImages($listing)
            : [$this->absoluteUrl($variant->image->urlForVariant('card'))];
        $variantName = collect($selections)->values()->implode(' / ');

        return $this->withoutEmpty([
            '@type' => 'Product',
            '@id' => $url.'#product',
            'isVariantOf' => ['@id' => route('listings.show', $listing->slug).'#product-group'],
            'url' => $url,
            'name' => $variantName === '' ? $listing->title : $listing->title.' — '.$variantName,
            'image' => $image,
            'sku' => $variant->sku,
            ...$this->identifier($variant->gtin, $variant->mpn),
            ...$properties,
            'offers' => $this->offer(
                listing: $listing,
                url: $url,
                price: $variant->buyNowPrice(),
                marketPrice: $variant->market_price === null ? null : (string) $variant->market_price,
                availability: $variant->availableQuantity() > 0 ? 'in_stock' : ($listing->allow_backorders ? 'backorder' : 'out_of_stock'),
            ),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function offer(Listing $listing, string $url, ?string $price, ?string $marketPrice, string $availability): ?array
    {
        if ($listing->listing_type !== 'buy_now' || $price === null) {
            return null;
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $url,
            'price' => $price,
            'priceCurrency' => (string) config('marketplace.seo.currency', 'LKR'),
            'availability' => 'https://schema.org/'.match ($availability) {
                'backorder' => 'BackOrder',
                'out_of_stock' => 'OutOfStock',
                default => 'InStock',
            },
            'itemCondition' => 'https://schema.org/'.match ($listing->condition) {
                'new' => 'NewCondition',
                'refurbished' => 'RefurbishedCondition',
                default => 'UsedCondition',
            },
            'seller' => ['@type' => 'Organization', 'name' => $listing->sellerProfile?->store_name],
            'hasMerchantReturnPolicy' => $this->returnPolicy((int) ($listing->category->return_window_days ?? 0)),
            'shippingDetails' => $this->shippingDetails(),
        ];

        if ($marketPrice !== null && (float) $marketPrice > (float) $price) {
            $offer['priceSpecification'] = [[
                '@type' => 'UnitPriceSpecification',
                'price' => $marketPrice,
                'priceCurrency' => (string) config('marketplace.seo.currency', 'LKR'),
                'priceType' => 'https://schema.org/StrikethroughPrice',
            ]];
        }

        return $this->withoutEmpty($offer);
    }

    /** @return array<string, mixed> */
    private function returnPolicy(int $days): array
    {
        if ($days <= 0) {
            return [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'LK',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
            ];
        }

        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'LK',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $days,
        ];
    }

    /** @return array<string, mixed>|null */
    private function shippingDetails(): ?array
    {
        $shipping = (array) config('marketplace.seo.shipping', []);
        $required = ['rate', 'handling_days_min', 'handling_days_max', 'transit_days_min', 'transit_days_max'];

        if (collect($required)->contains(fn (string $key): bool => ! isset($shipping[$key]))) {
            return null;
        }

        return [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'LK'],
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $shipping['rate'], 'currency' => 'LKR'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => $shipping['handling_days_min'], 'maxValue' => $shipping['handling_days_max'], 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $shipping['transit_days_min'], 'maxValue' => $shipping['transit_days_max'], 'unitCode' => 'DAY'],
            ],
        ];
    }

    /** @return array<string, string> */
    private function identifier(?string $gtin, ?string $mpn): array
    {
        $identifier = [];

        if (ValidGtin::isValid($gtin)) {
            $identifier['gtin'.strlen($gtin)] = $gtin;
        }

        if ($mpn !== null) {
            $identifier['mpn'] = $mpn;
        }

        return $identifier;
    }

    /** @param array<int, array{name: string, slug: string}> $categoryTrail
     * @return string|array<int, string|array<string, string>>
     */
    private function categories(Listing $listing, array $categoryTrail): string|array
    {
        $path = collect($categoryTrail)->pluck('name')->implode(' > ');

        if ($listing->category?->google_product_category_id === null) {
            return $path;
        }

        return [
            $path,
            [
                '@type' => 'CategoryCode',
                'codeValue' => (string) $listing->category->google_product_category_id,
                'inCodeSet' => 'https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function rating(Listing $listing): ?array
    {
        $count = (int) $listing->getAttribute('reviews_count');
        $average = $listing->getAttribute('rating_average');

        return $count > 0 && $average !== null ? [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float) $average, 1),
            'reviewCount' => $count,
        ] : null;
    }

    private function plainDescription(Listing $listing): string
    {
        $description = filled($listing->short_description) ? $listing->short_description : $listing->description;

        return html_entity_decode(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $description))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<int, string> */
    private function listingImages(Listing $listing): array
    {
        return $listing->media
            ->map(fn (ListingMedia $media): string => $this->absoluteUrl($media->urlForVariant('card')))
            ->values()
            ->all();
    }

    private function absoluteUrl(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $values): array
    {
        return collect($values)
            ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [])
            ->all();
    }
}
