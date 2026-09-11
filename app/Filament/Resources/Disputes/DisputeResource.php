<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disputes;

use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Models\Dispute;
use App\Models\User;
use App\Services\DisputeWorkflow;
use App\Services\DomainStateTransitionException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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

final class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Formal Disputes';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Dispute')->sortable(),
                TextColumn::make('evaluation.id')->label('Evaluation')->sortable(),
                TextColumn::make('organization.name')->label('Organization')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (DisputeStatus $state): string => str($state->value)->replace('_', ' ')->headline()->toString()),
                TextColumn::make('grounds')->badge(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                Action::make('assignReviewer')
                    ->label('Assign reviewer')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Dispute $record): bool => in_array($record->status, [DisputeStatus::Submitted, DisputeStatus::UnderReview], true))
                    ->form([
                        Select::make('reviewer_id')
                            ->label('Reviewer')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Dispute $record, array $data): void {
                        try {
                            $reviewer = User::query()->findOrFail((string) $data['reviewer_id']);
                            app(DisputeWorkflow::class)->assignReviewer($record, $reviewer, self::authenticatedUser());
                            Notification::make()->success()->title('Dispute reviewer assigned')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Reviewer assignment blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Reviewer assignment failed')->send();
                        }
                    }),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Dispute $record): bool => $record->status === DisputeStatus::UnderReview)
                    ->form([
                        Select::make('outcome')
                            ->options(self::outcomeOptions())
                            ->required(),
                        Textarea::make('rationale')->label('Resolution rationale')->required()->rows(5),
                    ])
                    ->action(function (Dispute $record, array $data): void {
                        try {
                            $outcome = DisputeOutcome::from((string) $data['outcome']);
                            app(DisputeWorkflow::class)->resolve($record, self::authenticatedUser(), $outcome, (string) $data['rationale']);
                            Notification::make()->success()->title('Dispute resolved')->send();
                        } catch (DomainStateTransitionException|\ValueError $exception) {
                            Notification::make()->danger()->title('Dispute resolution blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Dispute resolution failed')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluation', 'organization', 'submittedBy', 'resolvedBy', 'reviewers.reviewer']);
    }

    public static function getPages(): array
    {
        return ['index' => ListDisputes::route('/')];
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(DisputeStatus::cases())
            ->mapWithKeys(fn (DisputeStatus $status): array => [$status->value => str($status->value)->replace('_', ' ')->headline()->toString()])
            ->all();
    }

    /** @return array<string,string> */
    private static function outcomeOptions(): array
    {
        return collect(DisputeOutcome::cases())
            ->mapWithKeys(fn (DisputeOutcome $outcome): array => [$outcome->value => str($outcome->value)->replace('_', ' ')->headline()->toString()])
            ->all();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
