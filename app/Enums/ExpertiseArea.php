<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertiseArea: string
{
    case Assessment = 'assessment';
    case Accessibility = 'accessibility';
    case CorporateLearning = 'corporate_learning';
    case CurriculumDevelopment = 'curriculum_development';
    case EducationalTechnology = 'educational_technology';
    case InstructionalDesign = 'instructional_design';
    case LanguageLearning = 'language_learning';
    case LearningScience = 'learning_science';
    case OnlineLearning = 'online_learning';
}
