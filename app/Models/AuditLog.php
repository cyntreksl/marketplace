<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'reason'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
