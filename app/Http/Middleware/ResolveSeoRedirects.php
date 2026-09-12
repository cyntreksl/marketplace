<?php

namespace App\Http\Middleware;

use App\Services\SeoRedirectService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveSeoRedirects
{
    public function __construct(private readonly SeoRedirectService $redirects) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (ModelNotFoundException|NotFoundHttpException $exception) {
            return $this->redirectResponse($request) ?? throw $exception;
        }

        if ($response->getStatusCode() !== Response::HTTP_NOT_FOUND) {
            return $response;
        }

        return $this->redirectResponse($request) ?? $response;
    }

    private function redirectResponse(Request $request): ?RedirectResponse
    {
        $destination = $this->redirects->resolve('/'.$request->path());

        if ($destination === null) {
            return null;
        }

        if ($request->getQueryString() !== null) {
            $destination .= '?'.$request->getQueryString();
        }

        return redirect($destination, Response::HTTP_MOVED_PERMANENTLY);
    }
}
