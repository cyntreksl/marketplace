<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\CategoryLookupRequest;
use App\Http\Requests\CategorySuggestionRequest;
use App\Http\Resources\CategoryLookupResource;
use App\Services\CategorySuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryLookupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly CategorySuggestionService $suggestions,
    ) {}

    public function __invoke(CategoryLookupRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return CategoryLookupResource::collection($this->catalog->lookupCategories(
            search: $validated['search'] ?? null,
            parentId: isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        ));
    }

    public function suggest(CategorySuggestionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = (int) ($validated['limit'] ?? config('services.openai.category_suggestions.max_results', 5));

        return response()->json([
            'data' => $this->suggestions->suggest(
                title: (string) $validated['title'],
                limit: $limit,
                currentParentId: isset($validated['current_parent_id']) ? (int) $validated['current_parent_id'] : null,
                topPath: $validated['top_path'] ?? null,
            )->values(),
        ]);
    }
}
