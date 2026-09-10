<?php

declare(strict_types=1);

namespace App\Filament\Resources\EvaluationRequests;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Resources\EvaluationRequests\Pages\ListEvaluationRequests;
use App\Filament\Resources\EvaluationRequests\Pages\ViewEvaluationRequest;
use App\Models\EvaluationRequest;
use App\Models\Payment;
use App\Models\Refund;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Request')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::requestStatusLabel($state)),
                TextColumn::make('order.status')
                    ->label('Order')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('order.payments.status')
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
                SelectFilter::make('status')->options(self::requestStatusOptions()),
                SelectFilter::make('order_status')
                    ->label('Order status')
                    ->options(self::enumOptions(OrderStatus::cases())),
                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options(self::enumOptions(PaymentStatus::cases())),
                SelectFilter::make('refund_status')
                    ->label('Refund status')
                    ->options(self::enumOptions(RefundStatus::cases())),
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
                    ->action(fn (EvaluationRequest $record): void => self::refund($record)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'organization',
            'product',
            'order.payments',
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

    /** @return array<string, string> */
    private static function requestStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'awaiting_payment' => 'Awaiting payment',
            'paid' => 'Paid',
            'intake' => 'Intake',
            'awaiting_creator' => 'Awaiting creator',
            'ready' => 'Ready',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
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

    private static function requestStatusLabel(mixed $state): string
    {
        if ($state instanceof \App\Enums\EvaluationRequestStatus) {
            return self::statusLabel($state);
        }

        return self::statusLabel($state);
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
