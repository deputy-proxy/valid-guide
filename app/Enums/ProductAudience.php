<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductAudience: string
{
    case Beginners = 'beginners';
    case Students = 'students';
    case Professionals = 'professionals';
    case Managers = 'managers';
    case Educators = 'educators';
    case Creators = 'creators';
    case Entrepreneurs = 'entrepreneurs';
    case CareerChangers = 'career_changers';
}
