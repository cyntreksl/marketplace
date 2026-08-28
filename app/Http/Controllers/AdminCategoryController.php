<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\ArchiveResourceRequest;
use App\Http\Requests\RemoveCategoryImageRequest;
use App\Http\Requests\StoreCategoryImageRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryActivationRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AdminCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    public function index(Request $request, CatalogRepository $catalog): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('admin/catalog/categories', ['categories' => $catalog->categories($request->only(['search', 'archived']))]);
    }

    public function store(StoreCategoryRequest $request, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $catalog->createCategory($request->user(), Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $catalog->updateCategory($request->user(), $category, Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function storeImage(StoreCategoryImageRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        $catalog->replaceCategoryImage($request->user(), $category, $image, $request->validated('reason'));

        return to_route('admin.categories.index')->with('status', 'Category image updated.');
    }

    public function destroyImage(RemoveCategoryImageRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $catalog->removeCategoryImage($request->user(), $category, $request->validated('reason'));

        return to_route('admin.categories.index')->with('status', 'Category image removed.');
    }

    public function updateActivation(UpdateCategoryActivationRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $catalog->updateCategoryActivation(
            $request->user(),
            $category,
            $request->boolean('is_active'),
            $request->validated('reason'),
        );

        return to_route('admin.categories.index')->with('status', 'Category availability updated.');
    }

    public function destroy(ArchiveResourceRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('delete', $category);
        $catalog->archive($request->user(), $category, $request->validated('reason'));

        return to_route('admin.categories.index')->with('status', 'Category archived.');
    }

    public function restore(ArchiveResourceRequest $request, int $category, CatalogRepository $repository, AdminCatalogService $catalog): RedirectResponse
    {
        $model = $repository->categoryWithTrashed($category);
        Gate::authorize('restore', $model);
        $catalog->restore($request->user(), $model, $request->validated('reason'));

        return to_route('admin.categories.index')->with('status', 'Category restored.');
    }
}
