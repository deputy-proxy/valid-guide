<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketplaceServices;

use App\Enums\MarketplaceServiceStatus;
use App\Filament\Resources\MarketplaceServices\Pages\ListMarketplaceServices;
use App\Models\MarketplaceService;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\MarketplaceServiceManagement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class MarketplaceServiceResource extends Resource
{
    protected static ?string $model = MarketplaceService::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Marketplace Services';

    public static function canAccess(): bool
    {
        return self::user()->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('title')->disabled(),
            Textarea::make('description')->disabled()->rows(8),
            Textarea::make('status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('auditorProfile.auditor.name')->label('Expert')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('price_minor')->label('Price')->numeric()->sortable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('transactions_count')->counts('transactions')->label('Transactions'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(MarketplaceServiceStatus::cases())),
            ])
            ->recordActions([
                Action::make('pause')
                    ->color('warning')
                    ->visible(fn (MarketplaceService $record): bool => $record->status === MarketplaceServiceStatus::Published)
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (MarketplaceService $record, array $data) => self::run(fn () => app(MarketplaceServiceManagement::class)->pause(self::user(), $record), 'Marketplace service paused')),
                Action::make('archive')
                    ->color('danger')
                    ->visible(fn (MarketplaceService $record): bool => $record->status !== MarketplaceServiceStatus::Archived)
                    ->requiresConfirmation()
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (MarketplaceService $record, array $data) => self::run(fn () => app(MarketplaceServiceManagement::class)->archive(self::user(), $record), 'Marketplace service archived')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMarketplaceServices::route('/')];
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[(string) $case->value] = str((string) $case->value)->replace('_', ' ')->title()->toString();
        }

        return $options;
    }

    private static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->success()->title($success)->send();
        } catch (DomainStateTransitionException $exception) {
            Notification::make()->danger()->title('Action blocked')->body($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('Action failed')->send();
        }
    }

    private static function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
