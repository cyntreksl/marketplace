<?php

namespace App\Http\Middleware;

use App\Services\SeoHeadService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSearchRobotHeaders
{
    public function __construct(private readonly SeoHeadService $seo) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'sitemap.') || $routeName === 'robots') {
            return $response;
        }

        if ($response->getStatusCode() >= 400 || str_starts_with($this->seo->robotsPolicy($request), 'noindex')) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
