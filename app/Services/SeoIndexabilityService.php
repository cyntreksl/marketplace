<?php

namespace App\Services;

use Illuminate\Http\Request;

class SeoIndexabilityService
{
    public const REQUEST_ATTRIBUTE = 'seo.indexable';

    /** @var array<int, string> */
    private const INDEXABLE_ROUTES = [
        'home',
        'about',
        'contact',
        'help',
        'faq',
        'buying',
        'selling',
        'brands.index',
        'brands.show',
        'categories.show',
        'listings.index',
        'listings.show',
        'collections.show',
        'auctions.index',
        'stores.show',
        'policies.shipping',
        'policies.returns',
        'policies.sellers',
        'policies.prohibited',
        'legal.terms',
        'legal.privacy',
        'legal.cookies',
    ];

    public function robots(Request $request): string
    {
        return $this->isIndexable($request)
            ? 'index,follow,max-image-preview:large'
            : 'noindex,follow,max-image-preview:large';
    }

    public function mark(Request $request, bool $indexable): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $indexable);
    }

    public function isIndexable(Request $request): bool
    {
        $marked = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (is_bool($marked)) {
            return $marked;
        }

        $routeName = (string) optional($request->route())->getName();

        return in_array($routeName, self::INDEXABLE_ROUTES, true)
            && ! $this->hasNonIndexableCatalogQuery($request);
    }

    private function hasNonIndexableCatalogQuery(Request $request): bool
    {
        $catalogRoutes = ['listings.index', 'categories.show', 'brands.show', 'collections.show', 'auctions.index', 'stores.show'];

        if (! in_array((string) optional($request->route())->getName(), $catalogRoutes, true)) {
            return false;
        }

        return collect($request->query())
            ->except('page')
            ->filter(fn (mixed $value): bool => filled($value))
            ->isNotEmpty();
    }
}
