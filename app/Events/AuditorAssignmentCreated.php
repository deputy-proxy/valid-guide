<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AuditorAssignment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class AuditorAssignmentCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public AuditorAssignment $assignment)
    {
    }
}
