<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;

class PriorProductParticipationException extends DomainStateTransitionException
{
    public function __construct(
        public readonly Evaluation $evaluation,
        public readonly User $auditor,
        public readonly User $determinedBy,
    ) {
        parent::__construct('The Auditor cannot be assigned because they previously participated in this product.');
    }
}
