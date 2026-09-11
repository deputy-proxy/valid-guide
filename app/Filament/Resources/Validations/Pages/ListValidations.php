<?php

declare(strict_types=1);

namespace App\Filament\Resources\Validations\Pages;

use App\Filament\Resources\Validations\ValidationResource;
use Filament\Resources\Pages\ListRecords;

final class ListValidations extends ListRecords
{
    protected static string $resource = ValidationResource::class;
}
