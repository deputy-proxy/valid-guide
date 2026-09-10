<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductReleaseStatus: string
{
    case Draft = 'draft';
    case Current = 'current';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
}
