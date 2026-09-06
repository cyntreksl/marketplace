<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class StaticMediaService
{
    /** @var array<int, string> */
    public const ASSETS = [
        'apple-touch-icon.png',
        'favicon.ico',
        'favicon.png',
        'favicon.svg',
        'prodeals-email-logo.png',
        'prodeals-logo.svg',
        'prodeals-social-card.png',
        'images/storefront/home-deals-banner.png',
        'images/storefront/hero-home-appliances.webp',
        'images/storefront/hero-marketplace.jpg',
        'images/storefront/home-lifestyle.jpg',
        'images/storefront/technology.jpg',
    ];

    public function url(string $path, bool $versioned = false): string
    {
        $disk = (string) config('filesystems.media', 'public');
        $url = $disk === 'public'
            ? asset($path)
            : Storage::disk($disk)->url($this->objectPath($path));

        return $versioned ? $url.'?v='.hash_file('sha256', public_path($path)) : $url;
    }

    public function objectPath(string $path): string
    {
        return 'site/'.ltrim($path, '/');
    }
}
