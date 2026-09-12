<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HumanPageViewService
{
    private const string CRAWLER_PATTERN = '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|facebot|headless|lighthouse|pagespeed|preview/i';

    private const string SESSION_KEY = 'storefront.viewed_listings';

    public function __construct(private readonly ListingRepository $listings) {}

    public function isTrackable(Request $request): bool
    {
        $purpose = implode(' ', array_filter([
            $request->header('Purpose'),
            $request->header('Sec-Purpose'),
            $request->header('X-Purpose'),
        ]));
        $userAgent = $request->userAgent();

        return ! str_contains(Str::lower($purpose), 'prefetch')
            && ! str_contains(Str::lower($purpose), 'prerender')
            && is_string($userAgent)
            && $userAgent !== ''
            && preg_match(self::CRAWLER_PATTERN, $userAgent) !== 1;
    }

    public function trackListing(Request $request, int $listingId): bool
    {
        if (! $this->isTrackable($request)) {
            return false;
        }

        $seenListingIds = $request->session()->get(self::SESSION_KEY, []);
        $seenListingIds = is_array($seenListingIds) ? array_map('intval', $seenListingIds) : [];

        if (in_array($listingId, $seenListingIds, true)) {
            return false;
        }

        $request->session()->put(self::SESSION_KEY, [...$seenListingIds, $listingId]);
        $this->listings->incrementViewCount($listingId);

        return true;
    }
}
