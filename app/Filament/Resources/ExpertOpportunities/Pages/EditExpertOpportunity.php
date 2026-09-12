<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertOpportunities\Pages;

use App\Filament\Resources\ExpertOpportunities\ExpertOpportunityResource;
use Filament\Resources\Pages\EditRecord;

final class EditExpertOpportunity extends EditRecord
{
    protected static string $resource = ExpertOpportunityResource::class;
}
