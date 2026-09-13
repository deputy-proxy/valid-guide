<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\OrganizationContext;
use App\Services\SubscriptionManagement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|UnitEnum|null $navigationGroup = 'Creator';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Subscriptions';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('viewAny', Subscription::class);
    }

    public static function canCreate(): bool
    {
        $user = self::authenticatedUser();

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->can('create', [Subscription::class, app(OrganizationContext::class)->current($user)]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan_name_snapshot')->label('Plan')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => str($state->value)->replace('_', ' ')->title()->toString()),
                TextColumn::make('price_minor_snapshot')
                    ->label('Price')
                    ->formatStateUsing(fn (mixed $state, Subscription $record): string => self::formatMoney($state, $record->currency_snapshot)),
                TextColumn::make('current_period_end')->label('Period ends')->dateTime()->sortable(),
                TextColumn::make('latest_billing_status')
                    ->label('Latest billing')
                    ->state(fn (Subscription $record): string => self::latestBillingStatus($record)),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], true))
                    ->action(fn (Subscription $record) => self::run(fn () => app(SubscriptionManagement::class)->cancel($record, self::authenticatedUser()), 'Subscription cancelled')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = self::authenticatedUser();

        if ($user->isPlatformAdmin()) {
            return parent::getEloquentQuery()->with('billingRecords');
        }

        $organization = app(OrganizationContext::class)->current($user);

        return parent::getEloquentQuery()
            ->where('organization_id', $organization->getKey())
            ->with('billingRecords');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(SubscriptionStatus::cases())
            ->mapWithKeys(fn (SubscriptionStatus $status): array => [
                $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }

    private static function latestBillingStatus(Subscription $record): string
    {
        $billing = $record->latestBillingRecord();

        return $billing?->status->value === null
            ? 'None'
            : str($billing->status->value)->replace('_', ' ')->title()->toString();
    }

    private static function formatMoney(mixed $amount, string $currency): string
    {
        return is_numeric($amount)
            ? number_format(((int) $amount) / 100, 2).' '.strtoupper($currency)
            : '—';
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

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
