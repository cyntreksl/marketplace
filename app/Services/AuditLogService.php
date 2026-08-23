<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?User $actor, string $action, Model $auditable, ?array $before = null, ?array $after = null, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }
}
