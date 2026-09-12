<?php

namespace App\Models;

use Database\Factories\SeoRedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['source_path', 'destination_path', 'is_active', 'hit_count', 'last_hit_at'])]
class SeoRedirect extends Model
{
    /** @use HasFactory<SeoRedirectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
