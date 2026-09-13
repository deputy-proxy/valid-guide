<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityContributionStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
    case Removed = 'removed';
}
