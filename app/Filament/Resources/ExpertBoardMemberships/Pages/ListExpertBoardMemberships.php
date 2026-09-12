<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertBoardMemberships\Pages;

use App\Filament\Resources\ExpertBoardMemberships\ExpertBoardMembershipResource;
use Filament\Resources\Pages\ListRecords;

final class ListExpertBoardMemberships extends ListRecords
{
    protected static string $resource = ExpertBoardMembershipResource::class;
}
