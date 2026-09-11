<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClarificationRequests\Pages;

use App\Filament\Resources\ClarificationRequests\ClarificationRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListClarificationRequests extends ListRecords
{
    protected static string $resource = ClarificationRequestResource::class;
}
