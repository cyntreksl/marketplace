<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoHeadService
{
    public function __construct(
        private readonly StaticMediaService $staticMedia,
        private readonly ProductStructuredDataService $structuredData,
    ) {}

    /** @return array<string, mixed> */
    public function defaultPayload(Request $request): array
    {
        $name = (string) config('app.name', 'ProDeals.lk');
        $title = $name.' - Better deals. Closer to home.';
        $description = "Discover more with {$name}, Sri Lanka's marketplace for everyday finds and better deals.";
        $routeName = (string) optional($request->route())->getName();
        $pageLabel = match ($routeName) {
            'about' => 'About Us',
            'contact' => 'Contact Us',
            'help' => 'Help Centre',
            'faq' => 'Frequently Asked Questions',
            'buying' => 'Buying on ProDeals.lk',
            'selling' => 'Selling on ProDeals.lk',
            'brands.index' => 'Brands',
            'listings.index' => 'Products in Sri Lanka',
            'collections.show' => Str::of((string) $request->route('collection'))->replace('-', ' ')->title()->toString(),
            'policies.shipping' => 'Shipping Policy',
            'policies.returns' => 'Returns and Refunds',
            'policies.sellers' => 'Seller Policy',
            'policies.prohibited' => 'Prohibited Items',
            'legal.terms' => 'Terms and Conditions',
            'legal.privacy' => 'Privacy Policy',
            'legal.cookies' => 'Cookie Policy',
            default => null,
        };

        if ($pageLabel !== null) {
            $title = $pageLabel.' - '.$name;
        }
        $graphs = $routeName === 'home' ? $this->structuredData->homepage() : $this->contentBreadcrumbs($request);

        return $this->payload(
            title: $title,
            description: $description,
            canonical: $this->canonicalForRequest($request),
            type: 'website',
            image: $this->staticMedia->url('prodeals-social-card.png'),
            imageWidth: 1200,
            imageHeight: 630,
            robots: $this->robotsPolicy($request),
            graphs: $graphs,
        );
    }

    public function robotsPolicy(Request $request): string
    {
        $indexableRoutes = [
            'home', 'about', 'contact', 'help', 'faq', 'buying', 'selling', 'brands.index',
            'brands.show', 'categories.show', 'listings.index', 'listings.show', 'collections.show',
            'policies.shipping', 'policies.returns', 'policies.sellers', 'policies.prohibited',
            'legal.terms', 'legal.privacy', 'legal.cookies',
        ];
        $indexable = in_array((string) optional($request->route())->getName(), $indexableRoutes, true)
            && ! $this->hasNonIndexableCatalogQuery($request);

        return $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow,max-image-preview:large';
    }

    /** @param array<int, array{name: string, slug: string}> $categoryTrail
     * @return array<string, mixed>
     */
    public function listingPayload(Listing $listing, array $categoryTrail): array
    {
        $name = (string) config('app.name', 'ProDeals.lk');
        $title = filled($listing->meta_title)
            ? $this->plainText((string) $listing->meta_title)
            : $this->plainText("{$listing->title} - {$name}");
        [$image, $width, $height] = $this->listingImage($listing->media->first());
        $availability = match ($listing->stockStatus()) {
            'backorder' => 'backorder',
            'out_of_stock' => 'out of stock',
            default => 'in stock',
        };

        return $this->payload(
            title: $title,
            description: $this->listingDescription($listing),
            canonical: route('listings.show', $listing->slug),
            type: 'product',
            image: $image,
            imageWidth: $width,
            imageHeight: $height,
            robots: 'index,follow,max-image-preview:large',
            graphs: $this->structuredData->forListing($listing, $categoryTrail),
            product: $listing->listing_type === 'buy_now' ? [
                'price' => $listing->buyNowPrice(),
                'currency' => (string) config('marketplace.seo.currency', 'LKR'),
                'availability' => $availability,
            ] : null,
        );
    }

    /** @param array<int, array{name: string, url: string}> $breadcrumbs
     * @return array<string, mixed>
     */
    public function catalogPayload(string $title, string $description, string $canonical, array $breadcrumbs, bool $indexable = true): array
    {
        return $this->payload(
            title: $title,
            description: $description,
            canonical: $canonical,
            type: 'website',
            image: $this->staticMedia->url('prodeals-social-card.png'),
            imageWidth: 1200,
            imageHeight: 630,
            robots: $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow,max-image-preview:large',
            graphs: [$this->structuredData->breadcrumbs($breadcrumbs)],
        );
    }

    /** @param array<string, mixed> $payload
     * @return array<int, string>
     */
    public function tags(array $payload): array
    {
        $openGraph = (array) $payload['openGraph'];
        $tags = [
            $this->titleTag('title', (string) $payload['title']),
            $this->metaTag('description', 'name', 'description', (string) $payload['description']),
            $this->linkTag('canonical', (string) $payload['canonicalUrl']),
            $this->metaTag('robots', 'name', 'robots', (string) $payload['robots']),
            $this->metaTag('og:site_name', 'property', 'og:site_name', (string) $openGraph['siteName']),
            $this->metaTag('og:type', 'property', 'og:type', (string) $openGraph['type']),
            $this->metaTag('og:locale', 'property', 'og:locale', (string) $openGraph['locale']),
            $this->metaTag('og:url', 'property', 'og:url', (string) $payload['canonicalUrl']),
            $this->metaTag('og:title', 'property', 'og:title', (string) $payload['title']),
            $this->metaTag('og:description', 'property', 'og:description', (string) $payload['description']),
            $this->metaTag('og:image', 'property', 'og:image', (string) $openGraph['image']),
            $this->metaTag('twitter:card', 'name', 'twitter:card', 'summary_large_image'),
            $this->metaTag('twitter:title', 'name', 'twitter:title', (string) $payload['title']),
            $this->metaTag('twitter:description', 'name', 'twitter:description', (string) $payload['description']),
            $this->metaTag('twitter:image', 'name', 'twitter:image', (string) $openGraph['image']),
        ];

        if ($openGraph['imageWidth'] !== null && $openGraph['imageHeight'] !== null) {
            $tags[] = $this->metaTag('og:image:width', 'property', 'og:image:width', (string) $openGraph['imageWidth']);
            $tags[] = $this->metaTag('og:image:height', 'property', 'og:image:height', (string) $openGraph['imageHeight']);
        }

        if (is_array($payload['product'] ?? null)) {
            $product = $payload['product'];
            $tags[] = $this->metaTag('product:price:amount', 'property', 'product:price:amount', (string) $product['price']);
            $tags[] = $this->metaTag('product:price:currency', 'property', 'product:price:currency', (string) $product['currency']);
            $tags[] = $this->metaTag('product:availability', 'property', 'product:availability', (string) $product['availability']);
        }

        foreach ((array) $payload['jsonLd'] as $index => $graph) {
            $json = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $tags[] = '<script data-inertia="json-ld-'.$index.'" type="application/ld+json">'.$json.'</script>';
        }

        return $tags;
    }

    /** @param array<int, array<string, mixed>> $graphs
     * @param  array<string, mixed>|null  $product
     * @return array<string, mixed>
     */
    private function payload(
        string $title,
        string $description,
        string $canonical,
        string $type,
        string $image,
        ?int $imageWidth,
        ?int $imageHeight,
        string $robots,
        array $graphs,
        ?array $product = null,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'canonicalUrl' => $canonical,
            'robots' => $robots,
            'openGraph' => [
                'siteName' => (string) config('app.name', 'ProDeals.lk'),
                'type' => $type,
                'locale' => (string) config('marketplace.seo.open_graph_locale', 'en_LK'),
                'image' => $image,
                'imageWidth' => $imageWidth,
                'imageHeight' => $imageHeight,
            ],
            'product' => $product,
            'jsonLd' => $graphs,
        ];
    }

    private function listingDescription(Listing $listing): string
    {
        if (filled($listing->meta_description)) {
            return $this->plainText((string) $listing->meta_description);
        }

        if (filled($listing->short_description)) {
            return (string) $listing->short_description;
        }

        $plainText = $this->plainText((string) $listing->description);

        return Str::limit($plainText !== '' ? $plainText : $listing->title, 160);
    }

    private function plainText(string $value): string
    {
        return html_entity_decode(trim((string) preg_replace('/\s+/', ' ', strip_tags($value))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array{string, int|null, int|null} */
    private function listingImage(?ListingMedia $cover): array
    {
        if ($cover === null) {
            return [$this->staticMedia->url('prodeals-social-card.png'), 1200, 630];
        }

        $hasOpenGraphVariant = is_array($cover->variants) && isset($cover->variants['open_graph']);
        $imageUrl = $cover->urlForVariant('open_graph');

        return [
            Str::startsWith($imageUrl, ['http://', 'https://']) ? $imageUrl : url($imageUrl),
            $hasOpenGraphVariant ? 1200 : ($cover->variant_version === null ? null : 1200),
            $hasOpenGraphVariant ? 630 : ($cover->variant_version === null ? null : 900),
        ];
    }

    private function hasNonIndexableCatalogQuery(Request $request): bool
    {
        if (! in_array((string) optional($request->route())->getName(), ['listings.index', 'categories.show', 'brands.show', 'collections.show'], true)) {
            return false;
        }

        return collect($request->query())->except('page')->filter(fn (mixed $value): bool => filled($value))->isNotEmpty();
    }

    private function canonicalForRequest(Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();
        $page = max(1, (int) $request->query('page', 1));

        if ($routeName === 'listings.index' && $this->hasNonIndexableCatalogQuery($request)) {
            if ($request->filled('category')) {
                return route('categories.show', $request->string('category')->toString());
            }

            if ($request->filled('brand')) {
                return route('brands.show', $request->string('brand')->toString());
            }

            return route('listings.index');
        }

        return $page > 1 ? $request->url().'?page='.$page : $request->url();
    }

    /** @return array<int, array<string, mixed>> */
    private function contentBreadcrumbs(Request $request): array
    {
        $routeName = (string) optional($request->route())->getName();

        if (! Str::startsWith($routeName, ['about', 'contact', 'help', 'faq', 'buying', 'selling', 'brands.index', 'policies.', 'legal.', 'collections.'])) {
            return [];
        }

        $label = $routeName === 'collections.show'
            ? Str::of((string) $request->route('collection'))->replace('-', ' ')->title()->toString()
            : Str::of($routeName)->afterLast('.')->replace('-', ' ')->title()->toString();

        return [$this->structuredData->breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $label, 'url' => $request->url()],
        ])];
    }

    private function titleTag(string $key, string $value): string
    {
        return '<title data-inertia="'.e($key).'">'.e($value).'</title>';
    }

    private function metaTag(string $key, string $attribute, string $name, string $content): string
    {
        return '<meta data-inertia="'.e($key).'" '.$attribute.'="'.e($name).'" content="'.e($content).'">';
    }

    private function linkTag(string $key, string $url): string
    {
        return '<link data-inertia="'.e($key).'" rel="canonical" href="'.e($url).'">';
    }
}
