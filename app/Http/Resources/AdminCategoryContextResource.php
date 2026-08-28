<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AdminCategoryContextResource extends JsonResource
{
    /**
     * @var array{
     *     selected: Category,
     *     trail: Collection<int, Category>,
     *     columns: array<int, array{parent_id: int|null, categories: Collection<int, Category>}>
     * }
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'selected' => (new AdminCategoryResource($this->resource['selected']))->resolve($request),
            'trail' => AdminCategoryResource::collection($this->resource['trail'])->resolve($request),
            'columns' => collect($this->resource['columns'])
                ->map(fn (array $column): array => [
                    'parent_id' => $column['parent_id'],
                    'categories' => AdminCategoryResource::collection($column['categories'])->resolve($request),
                ])
                ->values()
                ->all(),
        ];
    }
}
