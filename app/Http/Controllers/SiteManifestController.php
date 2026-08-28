<?php

namespace App\Http\Controllers;

use App\Services\StaticMediaService;
use Illuminate\Http\JsonResponse;

class SiteManifestController extends Controller
{
    public function __invoke(StaticMediaService $staticMedia): JsonResponse
    {
        return response()->json([
            'name' => 'ProDeals.lk',
            'short_name' => 'ProDeals.lk',
            'description' => 'Better deals. Closer to home.',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#F7F8FC',
            'theme_color' => '#102A5C',
            'icons' => [[
                'src' => $staticMedia->url('apple-touch-icon.png'),
                'sizes' => '180x180',
                'type' => 'image/png',
            ]],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
