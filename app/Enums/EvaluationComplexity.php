<?php

declare(strict_types=1);

namespace App\Enums;

enum EvaluationComplexity: string
{
    case Simple = 'simple';
    case Standard = 'standard';
    case Complex = 'complex';
    case Exceptional = 'exceptional';
}
