<?php

namespace App\Models;

use Database\Factories\ProductQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'asked_by', 'question', 'answer', 'answered_by', 'answered_at'])]
class ProductQuestion extends Model
{
    /** @use HasFactory<ProductQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by')->withTrashed();
    }
}
