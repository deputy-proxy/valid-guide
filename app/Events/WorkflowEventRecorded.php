<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

final readonly class WorkflowEventRecorded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $event,
        public Model $auditable,
    ) {}
}
