<?php

declare(strict_types=1);

namespace App\Enums;

enum ClarificationRequestType: string
{
    case Methodology = 'methodology';
    case Report = 'report';
    case Process = 'process';
    case Evidence = 'evidence';
}
