<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function record(
        string $event,
        Model $auditable,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
