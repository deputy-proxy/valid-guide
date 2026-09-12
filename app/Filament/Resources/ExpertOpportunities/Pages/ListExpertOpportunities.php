<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertOpportunities\Pages;

use App\Filament\Resources\ExpertOpportunities\ExpertOpportunityResource;
use Filament\Resources\Pages\ListRecords;

final class ListExpertOpportunities extends ListRecords
{
    protected static string $resource = ExpertOpportunityResource::class;
}
