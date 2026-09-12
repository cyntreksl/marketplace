<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\SeoRedirectRepository;
use App\Http\Requests\StoreSeoRedirectRequest;
use App\Http\Requests\UpdateSeoRedirectRequest;
use App\Models\SeoRedirect;
use App\Services\SeoRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminSeoRedirectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SeoRedirectRepository $redirects): Response
    {
        Gate::authorize('viewAny', SeoRedirect::class);

        return Inertia::render('admin/seo/redirects/index', [
            'redirects' => $redirects->paginate($request->only(['search', 'active'])),
            'filters' => $request->only(['search', 'active']),
            'canRestore' => $request->user()?->hasRole('super_admin') ?? false,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSeoRedirectRequest $request, SeoRedirectService $redirects): RedirectResponse
    {
        $redirects->create($request->validated());

        return to_route('admin.seo-redirects.index')->with('status', 'SEO redirect created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSeoRedirectRequest $request, SeoRedirect $seoRedirect, SeoRedirectService $redirects): RedirectResponse
    {
        $redirects->update($seoRedirect, $request->validated());

        return to_route('admin.seo-redirects.index')->with('status', 'SEO redirect updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SeoRedirect $seoRedirect, SeoRedirectService $redirects): RedirectResponse
    {
        Gate::authorize('delete', $seoRedirect);
        $redirects->delete($seoRedirect);

        return to_route('admin.seo-redirects.index')->with('status', 'SEO redirect archived.');
    }

    public function restore(int $seoRedirect, SeoRedirectRepository $repository, SeoRedirectService $redirects): RedirectResponse
    {
        $redirect = $repository->withTrashed($seoRedirect);
        Gate::authorize('restore', $redirect);
        $redirects->restore($redirect);

        return to_route('admin.seo-redirects.index')->with('status', 'SEO redirect restored.');
    }
}
