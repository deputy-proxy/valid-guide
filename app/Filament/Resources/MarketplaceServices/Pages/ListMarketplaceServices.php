<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketplaceServices\Pages;

use App\Filament\Resources\MarketplaceServices\MarketplaceServiceResource;
use Filament\Resources\Pages\ListRecords;

final class ListMarketplaceServices extends ListRecords
{
    protected static string $resource = MarketplaceServiceResource::class;
}
