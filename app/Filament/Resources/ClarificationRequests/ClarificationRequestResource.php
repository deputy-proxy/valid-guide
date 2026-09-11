<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClarificationRequests;

use App\Enums\ClarificationRequestStatus;
use App\Filament\Resources\ClarificationRequests\Pages\ListClarificationRequests;
use App\Models\ClarificationRequest;
use App\Models\User;
use App\Services\ClarificationWorkflow;
use App\Services\DomainStateTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;
use BackedEnum;

final class ClarificationRequestResource extends Resource
{
    protected static ?string $model = ClarificationRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Clarifications';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Request')->sortable(),
                TextColumn::make('evaluation.id')->label('Evaluation')->sortable(),
                TextColumn::make('organization.name')->label('Organization')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ClarificationRequestStatus $state): string => str($state->value)->replace('_', ' ')->headline()->toString()),
                TextColumn::make('submittedBy.name')->label('Submitted by'),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                Action::make('answer')
                    ->label('Answer')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn (ClarificationRequest $record): bool => $record->status === ClarificationRequestStatus::Open)
                    ->form([Textarea::make('response')->label('Response')->required()->rows(6)])
                    ->action(function (ClarificationRequest $record, array $data): void {
                        try {
                            app(ClarificationWorkflow::class)->answer($record, self::authenticatedUser(), (string) $data['response']);
                            Notification::make()->success()->title('Clarification answered')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Answer blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Answer failed')->send();
                        }
                    }),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ClarificationRequest $record): bool => $record->status === ClarificationRequestStatus::Answered)
                    ->action(function (ClarificationRequest $record): void {
                        try {
                            app(ClarificationWorkflow::class)->close($record, self::authenticatedUser());
                            Notification::make()->success()->title('Clarification closed')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Close blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Close failed')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluation', 'organization', 'submittedBy', 'resolvedBy']);
    }

    public static function getPages(): array
    {
        return ['index' => ListClarificationRequests::route('/')];
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(ClarificationRequestStatus::cases())
            ->mapWithKeys(fn (ClarificationRequestStatus $status): array => [$status->value => str($status->value)->replace('_', ' ')->headline()->toString()])
            ->all();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
