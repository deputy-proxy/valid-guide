<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        $user = Auth::user();

        return $user instanceof User && $user->isPlatformAdmin()
            ? []
            : [Actions\CreateAction::make()];
    }
}
