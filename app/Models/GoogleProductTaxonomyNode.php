<?php

namespace App\Models;

use Database\Factories\GoogleProductTaxonomyNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleProductTaxonomyNode extends Model
{
    /** @use HasFactory<GoogleProductTaxonomyNodeFactory> */
    use HasFactory;

    protected $fillable = ['google_product_taxonomy_version_id', 'google_product_category_id', 'parent_id', 'name', 'full_path', 'depth'];

    /** @return BelongsTo<GoogleProductTaxonomyVersion, $this> */
    public function taxonomyVersion(): BelongsTo
    {
        return $this->belongsTo(GoogleProductTaxonomyVersion::class, 'google_product_taxonomy_version_id');
    }

    /** @return BelongsTo<GoogleProductTaxonomyNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<GoogleProductTaxonomyNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
