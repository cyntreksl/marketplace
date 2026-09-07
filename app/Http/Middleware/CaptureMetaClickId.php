<?php

namespace App\Http\Middleware;

use App\Services\MetaClickIdService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureMetaClickId
{
    public function __construct(private readonly MetaClickIdService $clickIds) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $this->clickIds->capture($request);
        $response = $next($request);

        if ($cookie !== null) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
