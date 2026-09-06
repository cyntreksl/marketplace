<?php

namespace App\Services;

use App\Contracts\Repositories\SellerStoreRepository;
use App\Models\SellerProfile;
use Illuminate\Support\Str;

class SellerStoreService
{
    public function __construct(
        private readonly SellerStoreRepository $sellers,
        private readonly SellerSummaryService $summaries,
        private readonly StorefrontService $storefront,
        private readonly SeoHeadService $seo,
    ) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function publicData(string $slug, array $filters, int $page): array
    {
        $seller = $this->sellers->findPublic($slug);
        $summary = $this->summaries->forSeller($seller);
        $filtered = collect($filters)->except('sort')->contains(fn ($value): bool => filled($value)) || ($filters['sort'] ?? 'newest') !== 'newest';
        $url = route('stores.show', $slug);
        $canonical = $page > 1 && ! $filtered ? $url.'?page='.$page : $url;
        $description = filled($seller->about) ? Str::limit($seller->about, 160) : 'Browse products from '.$seller->store_name.' on '.config('app.name').'.';
        $seo = $this->seo->catalogPayload($seller->store_name.' | '.config('app.name'), $description, $canonical, [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $seller->store_name, 'url' => $url],
        ], ! $filtered && $summary['productCount'] > 0);
        if ($summary['coverUrl'] || $summary['logoUrl']) {
            $seo['openGraph']['image'] = $summary['coverUrl'] ?? $summary['logoUrl'];
            $seo['openGraph']['imageWidth'] = null;
            $seo['openGraph']['imageHeight'] = null;
        }
        $seo['jsonLd'][] = [
            '@context' => 'https://schema.org', '@type' => 'CollectionPage',
            'name' => $seller->store_name, 'description' => $description, 'url' => $canonical,
        ];
        $data = $this->storefront->browseData([...$filters, 'seller_id' => $seller->id]);

        return [...$data, 'filters' => $filters, 'seller' => $summary, 'seo' => $seo, 'head' => $this->seo->tags($seo)];
    }

    public function ownedSeller(int $userId): SellerProfile
    {
        return $this->sellers->findOwned($userId);
    }

    /** @return array<string, mixed> */
    public function settingsData(int $userId): array
    {
        return ['seller' => $this->summaries->forSeller($this->ownedSeller($userId))];
    }
}
