<?php

declare(strict_types=1);

namespace App\Filament\Resources\Validations;

use App\Enums\ValidationStatus;
use App\Filament\Resources\Validations\Pages\ListValidations;
use App\Models\User;
use App\Models\Validation;
use App\Services\DomainStateTransitionException;
use App\Services\ValidationStateTransition;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;
use BackedEnum;

final class ValidationResource extends Resource
{
    protected static ?string $model = Validation::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Validations';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('verification_identifier')->label('Verification ID')->searchable()->sortable(),
                TextColumn::make('evaluation.id')->label('Evaluation')->sortable(),
                TextColumn::make('productRelease.release_identifier')->label('Release')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ValidationStatus $state): string => str($state->value)->replace('_', ' ')->headline()->toString()),
                TextColumn::make('issued_at')->dateTime()->sortable(),
                TextColumn::make('publicVerificationRecord.published_at')->label('Published')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->label('Suspend')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Validation $record): bool => $record->status === ValidationStatus::Active)
                    ->action(fn (Validation $record): bool => self::transition($record, ValidationStatus::Suspended)),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Validation $record): bool => $record->status === ValidationStatus::Suspended)
                    ->action(fn (Validation $record): bool => self::transition($record, ValidationStatus::Active)),
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Validation $record): bool => in_array($record->status, [ValidationStatus::Active, ValidationStatus::Suspended], true))
                    ->action(fn (Validation $record): bool => self::transition($record, ValidationStatus::Revoked)),
                Action::make('supersede')
                    ->label('Supersede')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Validation $record): bool => in_array($record->status, [ValidationStatus::Active, ValidationStatus::Suspended], true))
                    ->action(fn (Validation $record): bool => self::transition($record, ValidationStatus::Superseded)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'evaluation',
            'productRelease',
            'publicVerificationRecord',
            'badge',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListValidations::route('/'),
        ];
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(ValidationStatus::cases())
            ->mapWithKeys(fn (ValidationStatus $status): array => [$status->value => str($status->value)->replace('_', ' ')->headline()->toString()])
            ->all();
    }

    private static function transition(Validation $record, ValidationStatus $status): bool
    {
        $user = self::authenticatedUser();

        try {
            app(ValidationStateTransition::class)->transition($record, $status, $user, 'Platform administrator action');
            Notification::make()->success()->title('Validation status updated')->send();
            return true;
        } catch (DomainStateTransitionException $exception) {
            Notification::make()->danger()->title('Validation status change blocked')->body($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('Validation status change failed')->send();
        }

        return false;
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
