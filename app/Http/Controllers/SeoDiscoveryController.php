<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

class SeoDiscoveryController extends Controller
{
    public function __construct(private readonly SitemapService $sitemaps) {}

    public function sitemap(): Response
    {
        return $this->xml($this->sitemaps->index());
    }

    public function staticPages(): Response
    {
        return $this->xml($this->sitemaps->staticPages());
    }

    public function categories(): Response
    {
        return $this->xml($this->sitemaps->categories());
    }

    public function brands(): Response
    {
        return $this->xml($this->sitemaps->brands());
    }

    public function products(int $page): Response
    {
        $xml = $this->sitemaps->products($page);

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".route('sitemap.index')."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
