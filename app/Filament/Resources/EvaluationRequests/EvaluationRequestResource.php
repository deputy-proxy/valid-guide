<?php

declare(strict_types=1);

namespace App\Filament\Resources\EvaluationRequests;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Resources\EvaluationRequests\Pages\ListEvaluationRequests;
use App\Filament\Resources\EvaluationRequests\Pages\ViewEvaluationRequest;
use App\Models\EvaluationRequest;
use App\Models\User;
use App\Services\CreatorRefundService;
use App\Services\DomainStateTransitionException;
use App\Services\PlatformCommerce;
use App\Services\StripePaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use Throwable;

final class EvaluationRequestResource extends Resource
{
    protected static ?string $model = EvaluationRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Commerce';

    protected static ?string $modelLabel = 'Evaluation Request';

    protected static ?string $pluralModelLabel = 'Evaluation Requests';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')
                ->schema([
                    TextEntry::make('id')->label('Request'),
                    TextEntry::make('organization.name')->label('Organization'),
                    TextEntry::make('product.title')->label('Product'),
                    TextEntry::make('productRelease.release_identifier')->label('Product Release'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                    TextEntry::make('paid_at')->dateTime(),
                ])
                ->columns(2),
            Section::make('Commercial terms')
                ->schema([
                    TextEntry::make('service_package_name_snapshot')->label('Service package'),
                    TextEntry::make('complexity')
                        ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                    TextEntry::make('quoted_amount_minor')
                        ->label('Amount')
                        ->formatStateUsing(fn (mixed $state, EvaluationRequest $record): string => self::formatMoney($state, $record->currency)),
                    TextEntry::make('currency'),
                ])
                ->columns(2),
            Section::make('Payment')
                ->schema([
                    TextEntry::make('order.status')
                        ->label('Order status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                    TextEntry::make('order.provider')->label('Provider'),
                    TextEntry::make('order.provider_reference')->label('Provider reference'),
                    TextEntry::make('order.latestPayment.status')
                        ->label('Payment status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                    TextEntry::make('order.latestPayment.provider_payment_id')->label('Payment reference'),
                    TextEntry::make('order.latestPayment.paid_at')->label('Paid at')->dateTime(),
                ])
                ->columns(2),
            Section::make('Refund')
                ->schema([
                    TextEntry::make('order.refund.status')
                        ->label('Refund status')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                    TextEntry::make('order.refund.amount_minor')
                        ->label('Refund amount')
                        ->formatStateUsing(fn (mixed $state, EvaluationRequest $record): string => self::formatMoney($state, $record->currency)),
                    TextEntry::make('order.refund.provider_refund_id')->label('Refund reference'),
                    TextEntry::make('order.refund.requested_at')->label('Requested at')->dateTime(),
                    TextEntry::make('order.refund.processed_at')->label('Processed at')->dateTime(),
                    TextEntry::make('order.refund.failed_at')->label('Failed at')->dateTime(),
                    TextEntry::make('order.refund.reason')->label('Reason'),
                    TextEntry::make('refund_eligibility')
                        ->label('Refund eligibility')
                        ->state(fn (EvaluationRequest $record): string => app(PlatformCommerce::class)->refundEligible($record) ? 'Eligible' : 'Not eligible')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Eligible' ? 'success' : 'gray'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Request')->searchable()->sortable(),
                TextColumn::make('organization.name')->label('Organization')->searchable()->sortable(),
                TextColumn::make('product.title')->label('Product')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('order.status')
                    ->label('Order')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('order.latestPayment.status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('quoted_amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (mixed $state, EvaluationRequest $record): string => self::formatMoney($state, $record->currency)),
                TextColumn::make('order.refund.status')
                    ->label('Refund')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('paid_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(EvaluationRequestStatus::cases())),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retryPayment')
                    ->label('Retry payment')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (EvaluationRequest $record): bool => app(PlatformCommerce::class)->paymentRetryable($record))
                    ->action(fn (EvaluationRequest $record): RedirectResponse => self::retryPayment($record)),
                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EvaluationRequest $record): bool => app(PlatformCommerce::class)->refundEligible($record))
                    ->action(function (EvaluationRequest $record): void {
                        self::refund($record);
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'organization',
            'product',
            'productRelease',
            'order.latestPayment',
            'order.refund',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluationRequests::route('/'),
            'view' => ViewEvaluationRequest::route('/{record}'),
        ];
    }

    /** @param list<BackedEnum> $cases */
    private static function enumOptions(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = self::statusLabel($case);
        }

        return $options;
    }

    private static function statusLabel(mixed $state): string
    {
        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if (! is_string($state)) {
            return 'Unknown';
        }

        return str($state)->replace('_', ' ')->title()->toString();
    }

    private static function formatMoney(mixed $amount, ?string $currency): string
    {
        if (! is_numeric($amount)) {
            return '—';
        }

        return number_format(((int) $amount) / 100, 2).' '.strtoupper((string) $currency);
    }

    private static function retryPayment(EvaluationRequest $record): RedirectResponse
    {
        try {
            $checkout = app(StripePaymentService::class)->createCheckout($record, self::authenticatedUser());

            return redirect()->away($checkout['url']);
        } catch (AuthorizationException) {
            self::failureNotification('Payment retry is not authorized.');
        } catch (DomainStateTransitionException) {
            self::failureNotification('The payment can no longer be retried in its current state.');
        } catch (Throwable $exception) {
            report($exception);
            self::failureNotification('The payment retry could not be started.');
        }

        return redirect()->back();
    }

    private static function refund(EvaluationRequest $record): void
    {
        try {
            $refund = app(CreatorRefundService::class)->refund($record, self::authenticatedUser());

            if ($refund->status === RefundStatus::Succeeded) {
                Notification::make()->success()->title('Refund completed')->send();

                return;
            }

            if ($refund->status === RefundStatus::Processing) {
                Notification::make()->warning()->title('Refund is processing')->send();

                return;
            }

            Notification::make()->danger()->title('Refund was not completed')->send();
        } catch (AuthorizationException) {
            self::failureNotification('Refund is not authorized.');
        } catch (DomainStateTransitionException) {
            self::failureNotification('The refund could not be completed because the request is no longer eligible.');
        } catch (Throwable $exception) {
            report($exception);
            self::failureNotification('The refund could not be completed.');
        }
    }

    private static function failureNotification(string $title): void
    {
        Notification::make()->danger()->title($title)->send();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
