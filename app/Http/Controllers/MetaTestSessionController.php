<?php

namespace App\Http\Controllers;

use App\Services\MetaTestSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MetaTestSessionController extends Controller
{
    public function __invoke(Request $request, MetaTestSessionService $testSession): RedirectResponse
    {
        try {
            $testSession->activate($request, $request->string('payload')->toString());
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return to_route('home');
    }
}
