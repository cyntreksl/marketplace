<?php

namespace App\Http\Middleware;

use App\Services\MetaParameterBuilderService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureMetaClickId
{
    public function __construct(private readonly MetaParameterBuilderService $parameterBuilder) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->parameterBuilder->process($request);
        $response = $next($request);

        foreach ($context->responseCookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
