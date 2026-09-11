<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditorAssignments;

use App\Filament\Resources\AuditorAssignments\Pages\ListAuditorAssignments;
use App\Models\AuditorAssignment;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\AuditorAssignmentCreation;
use App\Services\AuditorAssignmentStateTransition;
use App\Services\DomainStateTransitionException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class AuditorAssignmentResource extends Resource
{
    protected static ?string $model = AuditorAssignment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Auditor Assignments';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('evaluation.id')->label('Evaluation')->sortable(),
                TextColumn::make('auditor.name')->label('Auditor')->searchable()->sortable(),
                TextColumn::make('sequence')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('conflictDeclarations.outcome')->label('COI')->badge(),
                TextColumn::make('due_at')->dateTime()->sortable(),
                TextColumn::make('compensation_amount_minor')->label('Compensation'),
                TextColumn::make('compensation_status')->label('Compensation status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'offered' => 'Offered',
                    'accepted' => 'Accepted',
                    'declined' => 'Declined',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->headerActions([
                Action::make('assignAuditor')
                    ->label('Assign Auditor')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('evaluation_id')
                            ->label('Evaluation')
                            ->options(fn (): array => Evaluation::query()
                                ->whereIn('status', ['pending', 'in_progress'])
                                ->orderByDesc('created_at')
                                ->pluck('id', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Select::make('auditor_id')
                            ->label('Auditor')
                            ->options(fn (): array => User::query()
                                ->whereHas('auditorProfile')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('sequence')->numeric()->minValue(1)->required(),
                        TextInput::make('compensation_amount_minor')->numeric()->minValue(1)->required(),
                        TextInput::make('compensation_currency')->length(3)->default('EUR')->required(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $evaluation = Evaluation::query()->findOrFail((string) $data['evaluation_id']);
                            $auditor = User::query()->findOrFail((string) $data['auditor_id']);
                            app(AuditorAssignmentCreation::class)->create(
                                $evaluation,
                                $auditor,
                                self::authenticatedUser(),
                                (int) $data['sequence'],
                                (int) $data['compensation_amount_minor'],
                                strtoupper((string) $data['compensation_currency']),
                            );
                            Notification::make()->success()->title('Auditor assigned')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Auditor assignment blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Auditor assignment failed')->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AuditorAssignment $record): bool => in_array($record->status, ['offered', 'accepted'], true))
                    ->action(function (AuditorAssignment $record): void {
                        try {
                            app(AuditorAssignmentStateTransition::class)->transition($record, 'cancelled');
                            Notification::make()->success()->title('Auditor assignment cancelled')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Assignment cancellation blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Assignment cancellation failed')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluation', 'auditor', 'conflictDeclarations']);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditorAssignments::route('/')];
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
