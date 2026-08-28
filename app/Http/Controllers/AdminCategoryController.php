<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\ArchiveResourceRequest;
use App\Http\Requests\RemoveCategoryImageRequest;
use App\Http\Requests\StoreCategoryImageRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryActivationRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\AdminCategoryContextResource;
use App\Http\Resources\AdminCategoryResource;
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

        $selectedContext = null;
        if ($request->integer('category') > 0) {
            $selectedCategory = $catalog->categoryWithTrashed($request->integer('category'));
            Gate::authorize('view', $selectedCategory);
            $selectedContext = $catalog->adminCategoryContext($selectedCategory);
        }

        $rootCategories = $selectedContext['columns'][0]['categories']
            ?? $catalog->adminCategoryChildren(null);

        return Inertia::render('admin/catalog/categories', [
            'rootCategories' => AdminCategoryResource::collection($rootCategories)->resolve($request),
            'selectedContext' => $selectedContext === null
                ? null
                : (new AdminCategoryContextResource($selectedContext))->resolve($request),
            'categoryCount' => $catalog->adminCategoryCount(),
        ]);
    }

    public function store(StoreCategoryRequest $request, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $category = $catalog->createCategory($request->user(), Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $data = $request->validated();
        $catalog->updateCategory($request->user(), $category, Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category updated.');
    }

    public function storeImage(StoreCategoryImageRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        $catalog->replaceCategoryImage($request->user(), $category, $image, $request->validated('reason'));

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category image updated.');
    }

    public function destroyImage(RemoveCategoryImageRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $catalog->removeCategoryImage($request->user(), $category, $request->validated('reason'));

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category image removed.');
    }

    public function updateActivation(UpdateCategoryActivationRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        $catalog->updateCategoryActivation(
            $request->user(),
            $category,
            $request->boolean('is_active'),
            $request->validated('reason'),
        );

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category availability updated.');
    }

    public function destroy(ArchiveResourceRequest $request, Category $category, AdminCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('delete', $category);
        $catalog->archive($request->user(), $category, $request->validated('reason'));

        return to_route('admin.categories.index', ['category' => $category->id])->with('status', 'Category archived.');
    }

    public function restore(ArchiveResourceRequest $request, int $category, CatalogRepository $repository, AdminCatalogService $catalog): RedirectResponse
    {
        $model = $repository->categoryWithTrashed($category);
        Gate::authorize('restore', $model);
        $catalog->restore($request->user(), $model, $request->validated('reason'));

        return to_route('admin.categories.index', ['category' => $model->id])->with('status', 'Category restored.');
    }
}
