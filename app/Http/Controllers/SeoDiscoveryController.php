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

    public function stores(): Response
    {
        return $this->xml($this->sitemaps->stores());
    }

    public function products(int $page): Response
    {
        $xml = $this->sitemaps->products($page);

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    public function robots(): Response
    {
        $robots = <<<'ROBOTS'
User-agent: GPTBot
Disallow: /

User-agent: Google-Extended
Disallow: /

User-agent: ClaudeBot
Disallow: /

User-agent: anthropic-ai
Disallow: /

User-agent: CCBot
Disallow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Claude-User
Allow: /

User-agent: *
Allow: /

ROBOTS;

        return response($robots.'Sitemap: '.route('sitemap.index')."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
