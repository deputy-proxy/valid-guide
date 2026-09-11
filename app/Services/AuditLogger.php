<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\WorkflowEventRecorded;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     * @param  array<string,mixed>|null  $metadata
     */
    public static function record(
        string $event,
        Model $auditable,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?User $actor = null,
    ): AuditLog {
        $auditLog = AuditLog::query()->create([
            'actor_id' => $actor?->getKey() ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        event(new WorkflowEventRecorded($event, $auditable));

        return $auditLog;
    }
}
