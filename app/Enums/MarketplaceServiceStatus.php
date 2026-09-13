<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceServiceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Archived = 'archived';
}
