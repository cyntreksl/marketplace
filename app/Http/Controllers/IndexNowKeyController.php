<?php

namespace App\Http\Controllers;

use App\Services\IndexNowService;
use Illuminate\Http\Response;

class IndexNowKeyController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        return response(IndexNowService::KEY, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
