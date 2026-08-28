<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoHeadService
{
    public function __construct(private readonly StaticMediaService $staticMedia) {}

    /** @return array<int, string> */
    public function default(Request $request): array
    {
        $name = (string) config('app.name', 'ProDeals.lk');
        $title = $name.' — Better deals. Closer to home.';
        $description = "Discover more with {$name}, Sri Lanka's marketplace for everyday finds and better deals.";
        $image = $this->staticMedia->url('prodeals-social-card.png');

        return $this->tags(
            title: $title,
            description: $description,
            canonical: $request->url(),
            type: 'website',
            image: $image,
            imageWidth: 1200,
            imageHeight: 630,
        );
    }

    /** @return array<int, string> */
    public function listing(Listing $listing): array
    {
        $name = (string) config('app.name', 'ProDeals.lk');
        $title = "{$listing->title} - {$name}";
        $description = $this->listingDescription($listing);
        $cover = $listing->media->first();
        [$image, $width, $height] = $this->listingImage($cover);

        return $this->tags(
            title: $title,
            description: $description,
            canonical: route('listings.show', $listing->slug),
            type: 'product',
            image: $image,
            imageWidth: $width,
            imageHeight: $height,
        );
    }

    private function listingDescription(Listing $listing): string
    {
        $plainText = html_entity_decode(
            trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $listing->description))),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return Str::limit($plainText !== '' ? $plainText : $listing->title, 160);
    }

    /** @return array{string, int|null, int|null} */
    private function listingImage(?ListingMedia $cover): array
    {
        if ($cover === null) {
            return [$this->staticMedia->url('prodeals-social-card.png'), 1200, 630];
        }

        $hasOpenGraphVariant = is_array($cover->variants)
            && isset($cover->variants['open_graph']);
        $imageUrl = $cover->urlForVariant('open_graph');

        return [
            Str::startsWith($imageUrl, ['http://', 'https://']) ? $imageUrl : url($imageUrl),
            $hasOpenGraphVariant ? 1200 : ($cover->variant_version === null ? null : 1200),
            $hasOpenGraphVariant ? 630 : ($cover->variant_version === null ? null : 900),
        ];
    }

    /** @return array<int, string> */
    private function tags(
        string $title,
        string $description,
        string $canonical,
        string $type,
        string $image,
        ?int $imageWidth,
        ?int $imageHeight,
    ): array {
        $title = e($title);
        $description = e($description);
        $canonical = e($canonical);
        $type = e($type);
        $image = e($image);
        $siteName = e((string) config('app.name', 'ProDeals.lk'));
        $dimensions = [];

        if ($imageWidth !== null && $imageHeight !== null) {
            $dimensions = [
                '<meta data-inertia="og:image:width" property="og:image:width" content="'.$imageWidth.'">',
                '<meta data-inertia="og:image:height" property="og:image:height" content="'.$imageHeight.'">',
            ];
        }

        return [
            '<title data-inertia="title">'.$title.'</title>',
            '<meta data-inertia="description" name="description" content="'.$description.'">',
            '<link data-inertia="canonical" rel="canonical" href="'.$canonical.'">',
            '<meta data-inertia="og:site_name" property="og:site_name" content="'.$siteName.'">',
            '<meta data-inertia="og:type" property="og:type" content="'.$type.'">',
            '<meta data-inertia="og:title" property="og:title" content="'.$title.'">',
            '<meta data-inertia="og:description" property="og:description" content="'.$description.'">',
            '<meta data-inertia="og:image" property="og:image" content="'.$image.'">',
            ...$dimensions,
            '<meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">',
            '<meta data-inertia="twitter:title" name="twitter:title" content="'.$title.'">',
            '<meta data-inertia="twitter:description" name="twitter:description" content="'.$description.'">',
            '<meta data-inertia="twitter:image" name="twitter:image" content="'.$image.'">',
        ];
    }
}
