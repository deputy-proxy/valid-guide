<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditorAssignments\Pages;

use App\Filament\Resources\AuditorAssignments\AuditorAssignmentResource;
use Filament\Resources\Pages\ListRecords;

final class ListAuditorAssignments extends ListRecords
{
    protected static string $resource = AuditorAssignmentResource::class;
}
