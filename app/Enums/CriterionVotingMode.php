<?php

declare(strict_types=1);

namespace App\Enums;

enum CriterionVotingMode: string
{
    case Individual = 'individual';
    case Majority = 'majority';

    public function isCollective(): bool
    {
        return $this === self::Majority;
    }
}
