<?php

declare(strict_types=1);

namespace App\Filament\Resources\EvaluationRequests\Pages;

use App\Filament\Resources\EvaluationRequests\EvaluationRequestResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewEvaluationRequest extends ViewRecord
{
    protected static string $resource = EvaluationRequestResource::class;
}
