<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\SellerStoreRepository;
use App\Models\Listing;
use DateTimeInterface;

class SitemapService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
        private readonly SellerStoreRepository $sellers,
    ) {}

    public function index(): string
    {
        $children = [
            ['url' => route('sitemap.stores'), 'lastmod' => $this->sellers->sitemapStores()->max('updated_at')],
            ['url' => route('sitemap.static'), 'lastmod' => null],
            ['url' => route('sitemap.categories'), 'lastmod' => $this->catalog->sitemapCategories()->max('updated_at')],
            ['url' => route('sitemap.brands'), 'lastmod' => $this->catalog->sitemapBrands()->max('updated_at')],
        ];
        $perPage = $this->productChunkSize();
        $pageCount = (int) ceil($this->listings->sitemapProductCount() / $perPage);

        for ($page = 1; $page <= $pageCount; $page++) {
            $products = $this->listings->sitemapProducts($page, $perPage);
            $children[] = [
                'url' => route('sitemap.products', ['page' => $page]),
                'lastmod' => $products->max('updated_at'),
            ];
        }

        return $this->document('sitemapindex', collect($children)->map(fn (array $child): string => $this->sitemapEntry($child['url'], $child['lastmod']))->implode(''));
    }

    public function staticPages(): string
    {
        $routes = [
            'home', 'about', 'contact', 'help', 'faq', 'buying', 'selling', 'brands.index',
            'policies.shipping', 'policies.returns', 'policies.sellers', 'policies.prohibited',
            'legal.terms', 'legal.privacy', 'legal.cookies',
        ];

        if ($this->listings->sitemapProductCount() > 0) {
            $routes[] = 'listings.index';
        }

        if ($this->listings->indexableAuctionCount() > 0) {
            $routes[] = 'auctions.index';
        }

        $entries = collect($routes)->map(fn (string $name): array => ['url' => route($name), 'lastmod' => null]);

        foreach ($this->listings->indexableCollectionSlugs() as $collection) {
            $entries->push(['url' => route('collections.show', $collection), 'lastmod' => null]);
        }

        return $this->urlSet($entries);
    }

    public function categories(): string
    {
        return $this->urlSet($this->catalog->sitemapCategories()->map(fn ($category): array => [
            'url' => route('categories.show', $category->slug),
            'lastmod' => $category->updated_at,
        ]));
    }

    public function brands(): string
    {
        return $this->urlSet($this->catalog->sitemapBrands()->map(fn ($brand): array => [
            'url' => route('brands.show', $brand->slug),
            'lastmod' => $brand->updated_at,
        ]));
    }

    public function stores(): string
    {
        return $this->urlSet($this->sellers->sitemapStores()->map(fn ($seller): array => [
            'url' => route('stores.show', $seller->slug), 'lastmod' => $seller->updated_at,
        ]));
    }

    public function products(int $page): ?string
    {
        $products = $this->listings->sitemapProducts($page, $this->productChunkSize());

        if ($products->isEmpty()) {
            return null;
        }

        return $this->urlSet($products->map(function (Listing $listing): array {
            $images = [];

            foreach ($listing->media as $media) {
                if ($media->type !== 'image') {
                    continue;
                }

                $images[] = [
                    'url' => $media->urlForVariant('card_2x'),
                    'title' => $listing->title,
                ];
            }

            return [
                'url' => route('listings.show', $listing->slug),
                'lastmod' => $listing->updated_at,
                'images' => $images,
            ];
        }));
    }

    /** @param iterable<int, array{url: string, lastmod: mixed, images?: array<int, array{url: string, title: string}>}> $entries */
    private function urlSet(iterable $entries): string
    {
        return $this->document('urlset', collect($entries)->map(fn (array $entry): string => $this->urlEntry(
            $entry['url'],
            $entry['lastmod'],
            $entry['images'] ?? [],
        ))->implode(''));
    }

    private function document(string $root, string $contents): string
    {
        $imageNamespace = $root === 'urlset' ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.$imageNamespace.'>'.$contents.'</'.$root.'>';
    }

    private function sitemapEntry(string $url, mixed $lastmod): string
    {
        return '<sitemap><loc>'.$this->escape($url).'</loc>'.$this->lastModified($lastmod).'</sitemap>';
    }

    /** @param array<int, array{url: string, title: string}> $images */
    private function urlEntry(string $url, mixed $lastmod, array $images = []): string
    {
        $imageEntries = collect($images)->map(fn (array $image): string => '<image:image>'
            .'<image:loc>'.$this->escape($image['url']).'</image:loc>'
            .'<image:title>'.$this->escape($image['title']).'</image:title>'
            .'</image:image>')->implode('');

        return '<url><loc>'.$this->escape($url).'</loc>'.$this->lastModified($lastmod).$imageEntries.'</url>';
    }

    private function lastModified(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            return '';
        }

        return '<lastmod>'.$value->format('c').'</lastmod>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function productChunkSize(): int
    {
        return max(1, min(50000, (int) config('marketplace.seo.sitemap_product_chunk_size', 10000)));
    }
}
