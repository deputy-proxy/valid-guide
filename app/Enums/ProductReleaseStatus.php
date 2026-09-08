<?php

namespace App\Enums;

enum ProductReleaseStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
}
