<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertApplications;

use App\Models\ExpertOpportunityParticipation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class ExpertApplicationResource extends Resource
{
    protected static ?string $model = ExpertOpportunityParticipation::class;
    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Expert Applications';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user instanceof User && $user->isPlatformAdmin();
    }

    public static function table(Table $table): Table { return $table; }
}
