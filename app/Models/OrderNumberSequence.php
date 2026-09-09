<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderNumberSequence extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
