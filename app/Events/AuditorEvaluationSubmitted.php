<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AuditorEvaluation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class AuditorEvaluationSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(public AuditorEvaluation $auditorEvaluation) {}
}
