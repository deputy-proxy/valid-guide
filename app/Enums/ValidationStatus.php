<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Superseded = 'superseded';
}
