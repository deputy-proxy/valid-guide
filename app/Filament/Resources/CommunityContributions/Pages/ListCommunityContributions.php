<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommunityContributions\Pages;

use App\Filament\Resources\CommunityContributions\CommunityContributionResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunityContributions extends ListRecords
{
    protected static string $resource = CommunityContributionResource::class;
}
