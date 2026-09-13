<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketplaceTransactions\Pages;

use App\Filament\Resources\MarketplaceTransactions\MarketplaceTransactionResource;
use Filament\Resources\Pages\ListRecords;

final class ListMarketplaceTransactions extends ListRecords
{
    protected static string $resource = MarketplaceTransactionResource::class;
}
