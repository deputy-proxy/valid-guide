<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicVerificationRecords;

use App\Filament\Resources\PublicVerificationRecords\Pages\ListPublicVerificationRecords;
use App\Models\PublicVerificationRecord;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\PublicVerificationPublication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class PublicVerificationRecordResource extends Resource
{
    protected static ?string $model = PublicVerificationRecord::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Public Verification';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_slug')->label('Public slug')->searchable()->sortable(),
                TextColumn::make('validation.verification_identifier')->label('Verification ID')->searchable(),
                TextColumn::make('validation.status')->label('Validation status')->badge(),
                IconColumn::make('directory_visible')->label('Directory')->boolean(),
                IconColumn::make('full_report_visible')->label('Full report')->boolean(),
                TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('toggleDirectory')
                    ->label(fn (PublicVerificationRecord $record): string => $record->directory_visible ? 'Hide from directory' : 'Show in directory')
                    ->requiresConfirmation()
                    ->action(function (PublicVerificationRecord $record): void {
                        try {
                            app(PublicVerificationPublication::class)->setVisibility(
                                $record,
                                ! $record->directory_visible,
                                $record->full_report_visible,
                                self::authenticatedUser(),
                            );
                            Notification::make()->success()->title('Directory visibility updated')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Visibility change blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Visibility change failed')->send();
                        }
                    }),
                Action::make('toggleFullReport')
                    ->label(fn (PublicVerificationRecord $record): string => $record->full_report_visible ? 'Hide full report' : 'Show full report')
                    ->requiresConfirmation()
                    ->action(function (PublicVerificationRecord $record): void {
                        try {
                            app(PublicVerificationPublication::class)->setVisibility(
                                $record,
                                $record->directory_visible,
                                ! $record->full_report_visible,
                                self::authenticatedUser(),
                            );
                            Notification::make()->success()->title('Full report visibility updated')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Visibility change blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Visibility change failed')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('validation');
    }

    public static function getPages(): array
    {
        return ['index' => ListPublicVerificationRecords::route('/')];
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
