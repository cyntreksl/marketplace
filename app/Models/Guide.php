<?php

namespace App\Models;

use Database\Factories\GuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'slug', 'excerpt', 'quick_answer', 'sections', 'buying_checklist', 'hero_image_disk', 'hero_image_path', 'hero_image_alt', 'seo_title', 'seo_description', 'primary_query', 'supporting_queries', 'trends_researched_at', 'status', 'published_at'])]
class Guide extends Model
{
    /** @use HasFactory<GuideFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'buying_checklist' => 'array',
            'supporting_queries' => 'array',
            'trends_researched_at' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function heroImageUrl(): ?string
    {
        if (! filled($this->hero_image_path)) {
            return null;
        }

        $disk = $this->hero_image_disk ?: (string) config('filesystems.media', 'r2');

        return Storage::disk($disk)->url((string) $this->hero_image_path);
    }
}
