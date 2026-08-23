<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class VendorRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/vendor-register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }
}
