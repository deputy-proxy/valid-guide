<?php

declare(strict_types=1);

namespace App\Enums;

enum CriterionAssessment: string
{
    case Exceeds = 'exceeds';
    case Meets = 'meets';
    case PartiallyMeets = 'partially_meets';
    case DoesNotMeet = 'does_not_meet';
    case InsufficientEvidence = 'insufficient_evidence';
    case NotApplicable = 'not_applicable';

    public function isScored(): bool
    {
        return match ($this) {
            self::Exceeds,
            self::Meets,
            self::PartiallyMeets,
            self::DoesNotMeet => true,
            self::InsufficientEvidence,
            self::NotApplicable => false,
        };
    }
}
