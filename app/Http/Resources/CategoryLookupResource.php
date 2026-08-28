<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryLookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'name' => $this->resource->name,
            'path' => (string) ($this->resource->getAttribute('taxonomy_path') ?: $this->resource->name),
            'slug' => $this->resource->slug,
            'is_selectable' => (bool) $this->resource->is_selectable,
            'has_children' => (int) $this->resource->getAttribute('active_children_count') > 0,
            'commission_percentage' => $this->resource->commission_percentage,
        ];
    }
}
