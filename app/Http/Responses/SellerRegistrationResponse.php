<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class SellerRegistrationResponse implements RegisterResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        if ($request->input('registration_type') === 'seller') {
            return to_route('seller.listings.create');
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
