<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disputes\Pages;

use App\Filament\Resources\Disputes\DisputeResource;
use Filament\Resources\Pages\ListRecords;

final class ListDisputes extends ListRecords
{
    protected static string $resource = DisputeResource::class;
}
