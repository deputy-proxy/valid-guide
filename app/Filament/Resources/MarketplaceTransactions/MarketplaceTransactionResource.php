<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketplaceTransactions;

use App\Enums\MarketplaceTransactionStatus;
use App\Filament\Resources\MarketplaceTransactions\Pages\ListMarketplaceTransactions;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\MarketplaceTransactionService;
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

final class MarketplaceTransactionResource extends Resource
{
    protected static ?string $model = MarketplaceTransaction::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Marketplace Transactions';

    public static function canAccess(): bool
    {
        return self::user()->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marketplaceService.title')->label('Service')->searchable(),
                TextColumn::make('auditorProfile.auditor.name')->label('Expert')->searchable(),
                TextColumn::make('buyer.name')->searchable(),
                TextColumn::make('organization.name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount_minor')->numeric()->sortable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(MarketplaceTransactionStatus::cases())),
            ])
            ->recordActions([
                Action::make('refund')
                    ->color('danger')
                    ->visible(fn (MarketplaceTransaction $record): bool => in_array($record->status, [
                        MarketplaceTransactionStatus::Paid,
                        MarketplaceTransactionStatus::InProgress,
                        MarketplaceTransactionStatus::Cancelled,
                    ], true))
                    ->requiresConfirmation()
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (MarketplaceTransaction $record, array $data) => self::run(fn () => app(MarketplaceTransactionService::class)->refund($record, self::user(), (string) $data['reason']), 'Marketplace transaction refunded')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMarketplaceTransactions::route('/')];
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
