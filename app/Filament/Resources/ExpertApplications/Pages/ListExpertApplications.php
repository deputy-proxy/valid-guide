<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertApplications\Pages;

use App\Filament\Resources\ExpertApplications\ExpertApplicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListExpertApplications extends ListRecords
{
    protected static string $resource = ExpertApplicationResource::class;
}
