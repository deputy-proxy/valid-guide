<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommunityReports\Pages;

use App\Filament\Resources\CommunityReports\CommunityReportResource;
use Filament\Resources\Pages\ListRecords;

final class ListCommunityReports extends ListRecords
{
    protected static string $resource = CommunityReportResource::class;
}
