<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Audit Logs';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime()->sortable(),
                TextColumn::make('event')->searchable()->sortable(),
                TextColumn::make('actor.name')->label('Actor')->searchable()->sortable(),
                TextColumn::make('auditable_type')->label('Subject')->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('auditable_id')->label('Subject ID')->searchable(),
                TextColumn::make('after')->label('After')->formatStateUsing(fn (mixed $state): string => self::json($state))->wrap(),
            ])
            ->filters([
                SelectFilter::make('event')->options(fn (): array => AuditLog::query()->distinct()->orderBy('event')->pluck('event', 'event')->all()),
                SelectFilter::make('actor_id')->label('Actor')->relationship('actor', 'name')->searchable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('actor');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }

    private static function json(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '—' : $encoded;
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
