<?php

namespace App\Services;

use App\Models\Listing;
use App\Rules\ValidGtin;
use App\Support\SeoText;
use Illuminate\Support\Str;

class ListingSeoScoreService
{
    /** @return array{score: int, maximum: int, label: string, checks: array<int, array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null}>} */
    public function score(Listing $listing): array
    {
        $checks = [
            $this->titleCheck($listing),
            $this->descriptionCheck($listing),
            $this->snippetCheck($listing),
            $this->taxonomyCheck($listing),
            $this->identifierCheck($listing),
            $this->imageCheck($listing),
            $this->commerceCheck($listing),
            $this->detailCheck($listing),
        ];
        $score = collect($checks)->sum('points');

        return [
            'score' => $score,
            'maximum' => 100,
            'label' => match (true) {
                $score >= 80 => 'Strong',
                $score >= 60 => 'Needs Improvement',
                default => 'Weak',
            },
            'checks' => $checks,
        ];
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function titleCheck(Listing $listing): array
    {
        $length = Str::length(trim((string) $listing->title));
        $points = $length > 0 ? 10 : 0;
        $points += $length >= 20 && $length <= 70 ? 5 : 0;

        return $this->check('title', 'Product title', $points, 15, 'Use a specific product title between 20 and 70 characters.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function descriptionCheck(Listing $listing): array
    {
        $length = Str::length(SeoText::plain((string) $listing->description));
        $points = match (true) {
            $length >= 250 => 20,
            $length >= 80 => 10,
            default => 0,
        };

        return $this->check('description', 'Description depth', $points, 20, 'Add at least 250 characters of original, useful product detail.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function snippetCheck(Listing $listing): array
    {
        $titleLength = Str::length(trim((string) $listing->meta_title));
        $descriptionLength = Str::length(trim((string) $listing->meta_description));
        $points = ($titleLength >= 30 && $titleLength <= 60 ? 5 : 0)
            + ($descriptionLength >= 70 && $descriptionLength <= 160 ? 5 : 0);

        return $this->check('metadata', 'Search snippet', $points, 10, 'Add a 30–60 character meta title and a 70–160 character meta description.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function taxonomyCheck(Listing $listing): array
    {
        $category = $listing->category;
        $points = $category?->isStorefrontAvailable() ? 5 : 0;
        $points += $category?->google_product_category_id !== null ? 5 : 0;

        return $this->check('taxonomy', 'Category and Google taxonomy', $points, 10, 'Choose a storefront category mapped to the Google product taxonomy.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function identifierCheck(Listing $listing): array
    {
        $points = $listing->brand !== null && ! $listing->brand->trashed() ? 5 : 0;
        $identifiers = collect([$listing, ...$listing->variants->all()]);

        if ($identifiers->contains(fn ($item): bool => ValidGtin::isValid($item->gtin))) {
            $points += 10;
        } elseif ($identifiers->contains(fn ($item): bool => filled($item->mpn))) {
            $points += 7;
        } elseif (filled($listing->model)) {
            $points += 3;
        }

        return $this->check('identifiers', 'Brand and identifiers', $points, 15, 'Use an approved brand and add a genuine GTIN, MPN, or model identifier.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function imageCheck(Listing $listing): array
    {
        $readyImages = $listing->media->filter(fn ($media): bool => $media->disk === 'r2'
            && $media->processing_status === 'ready'
            && is_array($media->variants)
            && isset($media->variants['card_2x']));
        $points = $readyImages->isNotEmpty() ? 10 : 0;
        $points += $readyImages->count() >= 3 ? 5 : 0;

        return $this->check('images', 'Processed R2 images', $points, 15, 'Provide a processed R2 cover plus at least two gallery images.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function commerceCheck(Listing $listing): array
    {
        if ($listing->listing_type === 'auction') {
            $auction = $listing->auction;
            $points = $auction !== null && (float) $auction->starting_price > 0 ? 5 : 0;
            $points += $auction !== null
                && in_array($auction->status, ['scheduled', 'live'], true)
                && $auction->starts_at->lt($auction->ends_at) ? 5 : 0;

            return $this->check('commerce', 'Auction data', $points, 10, 'Set valid auction pricing, status, and start/end dates.');
        }

        $points = $listing->buyNowPrice() !== null && (float) $listing->buyNowPrice() > 0 ? 5 : 0;
        $points += ($listing->reserved_quantity <= $listing->stock_quantity
            || $listing->allow_backorders) ? 5 : 0;

        return $this->check('commerce', 'Product price and stock', $points, 10, 'Provide a positive price and accurate stock or backorder availability.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function detailCheck(Listing $listing): array
    {
        $points = filled($listing->warranty) || collect($listing->specifications)->filter()->isNotEmpty() ? 5 : 0;

        return $this->check('details', 'Specifications or warranty', $points, 5, 'Add specifications or warranty information buyers can verify.');
    }

    /** @return array{key: string, label: string, points: int, maximum: int, passed: bool, recommendation: string|null} */
    private function check(string $key, string $label, int $points, int $maximum, string $recommendation): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'points' => $points,
            'maximum' => $maximum,
            'passed' => $points === $maximum,
            'recommendation' => $points === $maximum ? null : $recommendation,
        ];
    }
}
