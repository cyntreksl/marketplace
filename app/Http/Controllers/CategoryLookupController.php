<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\CategoryLookupRequest;
use App\Http\Resources\CategoryLookupResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryLookupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function __invoke(CategoryLookupRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return CategoryLookupResource::collection($this->catalog->lookupCategories(
            search: $validated['search'] ?? null,
            parentId: isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        ));
    }
}
