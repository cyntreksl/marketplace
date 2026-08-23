<?php

namespace App\Models;

use Database\Factories\GoogleProductTaxonomyVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleProductTaxonomyVersion extends Model
{
    /** @use HasFactory<GoogleProductTaxonomyVersionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['version', 'locale', 'source_filename', 'checksum', 'node_count', 'is_active', 'imported_by', 'activated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'activated_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by')->withTrashed();
    }

    /** @return HasMany<GoogleProductTaxonomyNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(GoogleProductTaxonomyNode::class);
    }
}
