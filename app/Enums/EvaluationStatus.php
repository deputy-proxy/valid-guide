<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InternalReview = 'internal_review';
    case ReadyForDecision = 'ready_for_decision';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';
}
