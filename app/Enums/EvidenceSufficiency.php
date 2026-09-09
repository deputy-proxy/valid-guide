<?php

declare(strict_types=1);

namespace App\Enums;

enum EvidenceSufficiency: string
{
    case Sufficient = 'sufficient';
    case Insufficient = 'insufficient';
    case Unresolved = 'unresolved';
}
