<?php

declare(strict_types=1);

namespace App\Enums;

enum EvaluationMaterialType: string
{
    case File = 'file';
    case Url = 'url';
    case Access = 'access';
    case Note = 'note';
}
