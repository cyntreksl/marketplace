<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Services\SeoHeadService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SeoHeadService $seo) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'head' => $this->seo->default($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'is_seller' => $request->user()?->roles()
                    ->whereIn('name', [Role::IndividualSeller, Role::BusinessSeller])
                    ->exists() ?? false,
            ],
            'marketplace' => config('marketplace'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
