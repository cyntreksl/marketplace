<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\GuideRepository;
use App\Http\Requests\StoreGuideRequest;
use App\Http\Requests\UpdateGuideRequest;
use App\Models\Guide;
use App\Services\GuideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminGuideController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, GuideService $guides): Response
    {
        Gate::authorize('viewAny', Guide::class);

        $data = $guides->adminIndexData($request->only(['search', 'status']));
        $data['canRestore'] = $request->user()?->hasRole('super_admin') ?? false;

        return Inertia::render('admin/seo/guides/index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(GuideService $guides): Response
    {
        Gate::authorize('create', Guide::class);

        return Inertia::render('admin/seo/guides/form', $guides->adminFormData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGuideRequest $request, GuideService $guides): RedirectResponse
    {
        $guide = $guides->create($request->validated());

        return to_route('admin.guides.edit', $guide)->with('status', 'Guide created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Guide $guide, GuideService $guides): Response
    {
        Gate::authorize('update', $guide);

        return Inertia::render('admin/seo/guides/form', $guides->adminFormData($guide));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuideRequest $request, Guide $guide, GuideService $guides): RedirectResponse
    {
        $guides->update($guide, $request->validated());

        return to_route('admin.guides.edit', $guide)->with('status', 'Guide updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Guide $guide, GuideService $guides): RedirectResponse
    {
        Gate::authorize('delete', $guide);
        $guides->delete($guide);

        return to_route('admin.guides.index')->with('status', 'Guide archived.');
    }

    public function restore(int $guide, GuideRepository $repository, GuideService $guides): RedirectResponse
    {
        $model = $repository->withTrashed($guide);
        Gate::authorize('restore', $model);
        $guides->restore($model);

        return to_route('admin.guides.index')->with('status', 'Guide restored as '.$model->status.'.');
    }
}
