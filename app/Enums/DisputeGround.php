<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeGround: string
{
    case ProceduralError = 'procedural_error';
    case MaterialFactualError = 'material_factual_error';
    case ConflictOfInterest = 'conflict_of_interest';
    case FlawedMethodologyApplication = 'flawed_methodology_application';
}
