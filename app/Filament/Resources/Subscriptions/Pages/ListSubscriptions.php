<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\SubscriptionManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return $this->isPlatformAdmin()
            ? []
            : [Actions\CreateAction::make()];
    }

    protected function getTableRecordActions(): array
    {
        $actions = parent::getTableRecordActions();

        if (! $this->isPlatformAdmin()) {
            return $actions;
        }

        $actions[] = Actions\Action::make('activate')
            ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::Pending)
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->activate($record, $this->user()), 'Subscription activated'));
        $actions[] = Actions\Action::make('renew')
            ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::Active)
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->renew($record, $this->user()), 'Subscription renewed'));
        $actions[] = Actions\Action::make('failPayment')
            ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active], true))
            ->requiresConfirmation()
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->failPayment($record, $this->user(), 'Provider reported a failed billing attempt.'), 'Payment marked as failed'));
        $actions[] = Actions\Action::make('recover')
            ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::PaymentFailed)
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->recover($record, $this->user()), 'Subscription recovered'));
        $actions[] = Actions\Action::make('refund')
            ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], true))
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->refund($record, $this->user(), 'Refund issued by platform administration.'), 'Subscription refunded'));
        $actions[] = Actions\Action::make('expire')
            ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], true))
            ->color('warning')
            ->action(fn (Subscription $record) => $this->run(fn () => app(SubscriptionManagement::class)->expire($record, $this->user()), 'Subscription expired'));

        return $actions;
    }

    private function isPlatformAdmin(): bool
    {
        return $this->user()->isPlatformAdmin();
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function run(callable $callback, string $success): void
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
}
