<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use Filament\Resources\Pages\ListRecords;

final class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;
}
