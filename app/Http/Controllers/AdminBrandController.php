<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\ArchiveResourceRequest;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\AdminCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminBrandController extends Controller
{
    public function index(Request $request, CatalogRepository $catalog): Response
    {
        Gate::authorize('viewAny', Brand::class);

        return Inertia::render('admin/catalog/brands', ['brands' => $catalog->brands($request->only(['search', 'archived']))]);
    }

    public function store(StoreBrandRequest $request, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $catalog->createBrand($request->user(), Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.brands.index')->with('status', 'Brand created.');
    }

    public function update(UpdateBrandRequest $request, Brand $brand, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $catalog->updateBrand($request->user(), $brand, Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.brands.index')->with('status', 'Brand updated.');
    }

    public function destroy(ArchiveResourceRequest $request, Brand $brand, AdminCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('delete', $brand);
        $catalog->archive($request->user(), $brand, $request->validated('reason'));

        return to_route('admin.brands.index')->with('status', 'Brand archived.');
    }

    public function restore(ArchiveResourceRequest $request, int $brand, CatalogRepository $repository, AdminCatalogService $catalog): RedirectResponse
    {
        $model = $repository->brandWithTrashed($brand);
        Gate::authorize('restore', $model);
        $catalog->restore($request->user(), $model, $request->validated('reason'));

        return to_route('admin.brands.index')->with('status', 'Brand restored.');
    }
}
