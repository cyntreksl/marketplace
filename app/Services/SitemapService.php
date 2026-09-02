<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use DateTimeInterface;

class SitemapService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
    ) {}

    public function index(): string
    {
        $children = [
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
            'home', 'about', 'contact', 'help', 'faq', 'buying', 'selling', 'brands.index', 'listings.index',
            'policies.shipping', 'policies.returns', 'policies.sellers', 'policies.prohibited',
            'legal.terms', 'legal.privacy', 'legal.cookies',
        ];

        return $this->urlSet(collect($routes)->map(fn (string $name): array => ['url' => route($name), 'lastmod' => null]));
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

    public function products(int $page): ?string
    {
        $products = $this->listings->sitemapProducts($page, $this->productChunkSize());

        if ($products->isEmpty()) {
            return null;
        }

        return $this->urlSet($products->map(fn ($listing): array => [
            'url' => route('listings.show', $listing->slug),
            'lastmod' => $listing->updated_at,
        ]));
    }

    /** @param iterable<int, array{url: string, lastmod: mixed}> $entries */
    private function urlSet(iterable $entries): string
    {
        return $this->document('urlset', collect($entries)->map(fn (array $entry): string => $this->urlEntry($entry['url'], $entry['lastmod']))->implode(''));
    }

    private function document(string $root, string $contents): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$contents.'</'.$root.'>';
    }

    private function sitemapEntry(string $url, mixed $lastmod): string
    {
        return '<sitemap><loc>'.$this->escape($url).'</loc>'.$this->lastModified($lastmod).'</sitemap>';
    }

    private function urlEntry(string $url, mixed $lastmod): string
    {
        return '<url><loc>'.$this->escape($url).'</loc>'.$this->lastModified($lastmod).'</url>';
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
