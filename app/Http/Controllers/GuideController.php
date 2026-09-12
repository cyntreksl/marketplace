<?php

namespace App\Http\Controllers;

use App\Services\GuideService;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function __construct(private readonly GuideService $guides) {}

    public function index(): Response
    {
        return Inertia::render('storefront/guides/index', $this->guides->indexData());
    }

    public function show(string $slug): Response
    {
        return Inertia::render('storefront/guides/show', $this->guides->showData($slug));
    }
}
