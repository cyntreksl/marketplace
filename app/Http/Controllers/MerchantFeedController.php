<?php

namespace App\Http\Controllers;

use App\Services\MerchantFeedService;
use Illuminate\Http\Response;

class MerchantFeedController extends Controller
{
    public function __construct(private readonly MerchantFeedService $feed) {}

    public function __invoke(): Response
    {
        return response($this->feed->generate(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
