<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;

final class MarketplaceCommercialConflictException extends DomainStateTransitionException
{
    public function __construct(
        public readonly Evaluation $evaluation,
        public readonly User $auditor,
        public readonly User $determinedBy,
    ) {
        parent::__construct('The Auditor cannot be assigned because they have a marketplace commercial relationship with the product organization.');
    }
}
