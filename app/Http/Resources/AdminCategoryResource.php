<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isArchived = $this->resource->deleted_at !== null;

        return [
            'id' => (int) $this->resource->getKey(),
            'parent_id' => $this->resource->parent_id === null ? null : (int) $this->resource->parent_id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'path' => (string) ($this->resource->getAttribute('category_path') ?: $this->resource->name),
            'google_product_category_id' => $this->resource->google_product_category_id === null
                ? null
                : (int) $this->resource->google_product_category_id,
            'image_url' => $this->resource->imageUrl(),
            'banner_image_url' => $this->resource->bannerImageUrl(),
            'commission_percentage' => (string) $this->resource->commission_percentage,
            'return_window_days' => (int) $this->resource->return_window_days,
            'cod_enabled' => (bool) $this->resource->cod_enabled,
            'is_active' => (bool) $this->resource->is_active,
            'is_taxonomy_available' => $this->resource->is_taxonomy_available,
            'is_storefront_available' => $this->resource->isStorefrontAvailable(),
            'is_selectable' => (bool) $this->resource->is_selectable,
            'deleted_at' => $this->resource->deleted_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
            'has_children' => (int) $this->resource->getAttribute('all_children_count') > 0,
            'capabilities' => [
                'can_update' => ! $isArchived,
                'can_manage_artwork' => ! $isArchived,
                'can_update_activation' => ! $isArchived,
                'can_archive' => ! $isArchived,
                'can_restore' => $isArchived && ($request->user()?->hasRole('super_admin') ?? false),
            ],
        ];
    }
}
