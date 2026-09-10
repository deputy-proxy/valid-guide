<?php

declare(strict_types=1);

namespace App\Filament\Resources\EvaluationRequests\Pages;

use App\Filament\Resources\EvaluationRequests\EvaluationRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListEvaluationRequests extends ListRecords
{
    protected static string $resource = EvaluationRequestResource::class;
}
