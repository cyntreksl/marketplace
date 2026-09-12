<?php

namespace App\Services;

use App\Contracts\Repositories\GuideRepository;
use App\Models\Guide;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GuideService
{
    public function __construct(
        private readonly GuideRepository $guides,
        private readonly GuideImageService $images,
        private readonly SeoRedirectService $redirects,
        private readonly SeoHeadService $seo,
    ) {}

    /** @return array<string, mixed> */
    public function indexData(): array
    {
        $guides = $this->guides->published()->through(fn (Guide $guide): array => $this->guideCard($guide));
        $page = max(1, (int) request()->query('page', 1));
        $canonical = route('guides.index').($page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: 'Buying Guides for Sri Lanka - '.config('app.name'),
            description: 'Practical product buying guides for Sri Lankan shoppers, covering appliances, chargers, cables and everyday essentials.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Buying Guides', 'url' => route('guides.index')],
            ],
            indexable: true,
        );

        return ['guides' => $guides, 'seo' => $seo, 'head' => $this->seo->tags($seo)];
    }

    /** @return array<string, mixed> */
    public function showData(string $slug): array
    {
        $guide = $this->guides->publishedBySlug($slug);
        $relatedListings = $this->guides->relatedListings($guide->categories->modelKeys());
        $seo = $this->seo->guidePayload($guide);

        return [
            'guide' => $this->guideData($guide),
            'relatedListings' => $relatedListings->map(fn (Listing $listing): array => $this->listingCard($listing))->values(),
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function adminIndexData(array $filters): array
    {
        return ['guides' => $this->guides->admin($filters), 'filters' => $filters];
    }

    /** @return array<string, mixed> */
    public function adminFormData(?Guide $guide = null): array
    {
        $guide?->load('categories:id,name,slug');

        return [
            'guide' => $guide === null ? null : $this->guideData($guide),
            'categories' => $this->guides->categoryOptions(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Guide
    {
        return $this->persist(new Guide, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Guide $guide, array $attributes): Guide
    {
        return $this->persist($guide, $attributes);
    }

    public function delete(Guide $guide): void
    {
        $this->guides->delete($guide);
    }

    public function restore(Guide $guide): void
    {
        $this->guides->restore($guide);
    }

    /** @param array<string, mixed> $attributes */
    private function persist(Guide $guide, array $attributes): Guide
    {
        $oldSlug = $guide->exists ? $guide->slug : null;
        $oldDisk = $guide->hero_image_disk;
        $oldPath = $guide->hero_image_path;
        $categoryIds = array_map('intval', Arr::pull($attributes, 'category_ids', []));
        $hero = Arr::pull($attributes, 'hero_image');
        $removeHero = (bool) Arr::pull($attributes, 'remove_hero_image', false);
        $stored = null;

        if ($hero instanceof UploadedFile) {
            $stored = $this->images->store($hero);
            $attributes['hero_image_disk'] = $stored['disk'];
            $attributes['hero_image_path'] = $stored['path'];
        } elseif ($removeHero) {
            $attributes['hero_image_disk'] = null;
            $attributes['hero_image_path'] = null;
        }

        if (($attributes['status'] ?? $guide->status) === 'published' && blank($attributes['published_at'] ?? $guide->published_at)) {
            $attributes['published_at'] = now();
        }

        if (($attributes['status'] ?? $guide->status) === 'draft') {
            $attributes['published_at'] = null;
        }

        try {
            DB::transaction(function () use ($guide, $attributes, $categoryIds, $oldSlug): void {
                $guide->fill($attributes);
                $this->guides->save($guide);
                $this->guides->syncCategories($guide, $categoryIds);
                $this->redirects->recordSlugChange('guides', $oldSlug, $guide->slug);
            });
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->images->delete($stored['disk'], $stored['path']);
            }

            throw $exception;
        }

        if (($stored !== null || $removeHero) && filled($oldPath)) {
            $this->images->delete($oldDisk, $oldPath);
        }

        return $guide->fresh('categories:id,name,slug');
    }

    /** @return array<string, mixed> */
    private function guideCard(Guide $guide): array
    {
        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'excerpt' => $guide->excerpt,
            'heroImageUrl' => $guide->heroImageUrl(),
            'heroImageAlt' => $guide->hero_image_alt,
            'publishedAt' => $guide->published_at?->toIso8601String(),
            'categories' => $guide->categories->map->only(['id', 'name', 'slug'])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function guideData(Guide $guide): array
    {
        return [
            ...$this->guideCard($guide),
            'quickAnswer' => $guide->quick_answer,
            'sections' => $guide->sections ?? [],
            'buyingChecklist' => $guide->buying_checklist ?? [],
            'seoTitle' => $guide->seo_title,
            'seoDescription' => $guide->seo_description,
            'primaryQuery' => $guide->primary_query,
            'supportingQueries' => $guide->supporting_queries ?? [],
            'trendsResearchedAt' => $guide->trends_researched_at?->toDateString(),
            'status' => $guide->status,
            'updatedAt' => $guide->updated_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function listingCard(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'price' => $listing->buyNowPrice(),
            'imageUrl' => $listing->media->first()?->urlForVariant('card'),
            'category' => $listing->category?->only(['name', 'slug']),
        ];
    }
}
