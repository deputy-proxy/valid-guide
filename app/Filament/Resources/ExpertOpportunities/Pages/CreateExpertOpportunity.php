<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertOpportunities\Pages;

use App\Filament\Resources\ExpertOpportunities\ExpertOpportunityResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

final class CreateExpertOpportunity extends CreateRecord
{
    protected static string $resource = ExpertOpportunityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $data['created_by'] = $user->getKey();
        $data['status'] = 'draft';
        return $data;
    }
}
