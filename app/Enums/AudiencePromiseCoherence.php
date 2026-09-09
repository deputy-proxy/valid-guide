<?php

declare(strict_types=1);

namespace App\Enums;

enum AudiencePromiseCoherence: string
{
    case Coherent = 'coherent';
    case Incoherent = 'incoherent';
    case Unresolved = 'unresolved';
}
