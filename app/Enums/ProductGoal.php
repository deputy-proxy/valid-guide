<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductGoal: string
{
    case SkillDevelopment = 'skill_development';
    case CareerTransition = 'career_transition';
    case ExamPreparation = 'exam_preparation';
    case ProfessionalDevelopment = 'professional_development';
    case TeachingSupport = 'teaching_support';
    case BusinessApplication = 'business_application';
    case PortfolioBuilding = 'portfolio_building';
    case CertificationPreparation = 'certification_preparation';
}
