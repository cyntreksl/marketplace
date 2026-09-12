<?php

namespace App\Http\Controllers;

use App\Services\SeoRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeoRedirectController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SeoRedirectService $redirects): RedirectResponse
    {
        $destination = $redirects->resolve('/'.$request->path());

        abort_if($destination === null, 404);

        return redirect($destination, 301);
    }
}
