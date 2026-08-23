<?php

namespace App\Models;

use Database\Factories\MarketplaceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'group', 'updated_by'])]
class MarketplaceSetting extends Model
{
    /** @use HasFactory<MarketplaceSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
