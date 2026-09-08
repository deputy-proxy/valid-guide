<?php

namespace App\Enums;

enum StandardVersionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Effective = 'effective';
    case Retired = 'retired';
}
