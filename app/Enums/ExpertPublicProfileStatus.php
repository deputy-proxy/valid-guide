<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertPublicProfileStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Suspended = 'suspended';
    case Removed = 'removed';
}
