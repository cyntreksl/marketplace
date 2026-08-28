<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SellerRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/seller-register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }
}
