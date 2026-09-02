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
        'images/storefront/hero-home-appliances.webp',
        'images/storefront/hero-marketplace.jpg',
        'images/storefront/home-lifestyle.jpg',
        'images/storefront/technology.jpg',
    ];

    public function url(string $path): string
    {
        $disk = (string) config('filesystems.media', 'public');

        if ($disk === 'public') {
            return asset($path);
        }

        return Storage::disk($disk)->url($this->objectPath($path));
    }

    public function objectPath(string $path): string
    {
        return 'site/'.ltrim($path, '/');
    }
}
