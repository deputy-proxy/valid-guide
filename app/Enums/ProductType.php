<?php

namespace App\Enums;

enum ProductType: string
{
    case Course = 'course';
    case CohortCourse = 'cohort_course';
    case Guide = 'guide';
    case Ebook = 'ebook';
    case Workshop = 'workshop';
    case Program = 'program';
    case Membership = 'membership';
    case Other = 'other';
}
