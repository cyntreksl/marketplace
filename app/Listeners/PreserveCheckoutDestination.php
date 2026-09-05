<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class PreserveCheckoutDestination
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $this->request->hasSession()) {
            return;
        }
        $intended = $this->request->session()->get('url.intended');
        if (is_string($intended) && parse_url($intended, PHP_URL_PATH) === '/checkout') {
            $this->request->session()->put('checkout_intended', true);
        }
    }
}
