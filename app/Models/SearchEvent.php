<?php

namespace App\Models;

use Database\Factories\SearchEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $visitor_id
 * @property string $term
 * @property string $normalized_term
 * @property int $result_count
 * @property string $context
 * @property array<string, mixed>|null $filters
 * @property Carbon $searched_at
 */
#[Fillable(['user_id', 'visitor_id', 'term', 'normalized_term', 'result_count', 'context', 'filters', 'searched_at'])]
class SearchEvent extends Model
{
    /** @use HasFactory<SearchEventFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'searched_at' => 'datetime',
        ];
    }
}
