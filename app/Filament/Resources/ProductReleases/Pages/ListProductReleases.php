<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReleases\Pages;

use App\Filament\Resources\ProductReleases\ProductReleaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductReleases extends ListRecords
{
    protected static string $resource = ProductReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
