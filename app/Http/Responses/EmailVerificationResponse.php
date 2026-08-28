<?php

namespace App\Http\Responses;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationResponse implements VerifyEmailResponse
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        if ($user->roles()->whereIn('name', [Role::IndividualSeller, Role::BusinessSeller])->exists()) {
            return to_route('seller.listings.index', ['verified' => 1]);
        }

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
