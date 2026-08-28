<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\AdminCategoryBrowseRequest;
use App\Http\Requests\AdminCategorySearchRequest;
use App\Http\Resources\AdminCategoryContextResource;
use App\Http\Resources\AdminCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminCategoryBrowseController extends Controller
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function children(AdminCategoryBrowseRequest $request): AnonymousResourceCollection
    {
        $parentId = $request->validated('parent_id');

        return AdminCategoryResource::collection(
            $this->catalog->adminCategoryChildren($parentId === null ? null : (int) $parentId),
        );
    }

    public function search(AdminCategorySearchRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $excludedSubtree = isset($validated['exclude_subtree_id'])
            ? $this->catalog->categoryWithTrashed((int) $validated['exclude_subtree_id'])
            : null;

        return AdminCategoryResource::collection($this->catalog->searchAdminCategories(
            search: $validated['query'] ?? null,
            status: $validated['status'] ?? 'all',
            parentOptions: $request->boolean('parent_options'),
            excludedSubtree: $excludedSubtree,
        ));
    }

    public function context(Request $request, int $category): JsonResponse
    {
        $model = $this->catalog->categoryWithTrashed($category);
        Gate::authorize('view', $model);

        return response()->json([
            'data' => (new AdminCategoryContextResource(
                $this->catalog->adminCategoryContext($model),
            ))->resolve($request),
        ]);
    }
}
