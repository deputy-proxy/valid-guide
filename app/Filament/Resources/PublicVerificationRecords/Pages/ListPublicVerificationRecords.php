<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicVerificationRecords\Pages;

use App\Filament\Resources\PublicVerificationRecords\PublicVerificationRecordResource;
use Filament\Resources\Pages\ListRecords;

final class ListPublicVerificationRecords extends ListRecords
{
    protected static string $resource = PublicVerificationRecordResource::class;
}
