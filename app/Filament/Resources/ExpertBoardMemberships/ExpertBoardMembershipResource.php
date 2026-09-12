<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertBoardMemberships;

use App\Enums\ExpertBoardMembershipStatus;
use App\Filament\Resources\ExpertBoardMemberships\Pages\ListExpertBoardMemberships;
use App\Models\ExpertBoardMembership;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertBoardGovernance;
use BackedEnum;
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

final class ExpertBoardMembershipResource extends Resource
{
    protected static ?string $model = ExpertBoardMembership::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Expert Board';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('auditorProfile.auditor.name')->label('Expert')->searchable()->sortable(),
                TextColumn::make('auditorProfile.status')->label('Auditor profile')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('auditorProfile.competencies_count')->label('Competencies')->counts('auditorProfile.competencies'),
                TextColumn::make('applied_at')->dateTime()->sortable(),
                TextColumn::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ExpertBoardMembershipStatus::Pending->value => 'Pending',
                    ExpertBoardMembershipStatus::Approved->value => 'Approved',
                    ExpertBoardMembershipStatus::Rejected->value => 'Rejected',
                    ExpertBoardMembershipStatus::Suspended->value => 'Suspended',
                    ExpertBoardMembershipStatus::Removed->value => 'Removed',
                ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ExpertBoardMembership $record): bool => $record->status === ExpertBoardMembershipStatus::Pending)
                    ->action(function (ExpertBoardMembership $record): void {
                        try {
                            app(ExpertBoardGovernance::class)->approve($record, self::authenticatedUser());
                            Notification::make()->success()->title('Expert Board membership approved')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Approval blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Approval failed')->send();
                        }
                    }),
                self::reasonAction('reject', 'Reject', 'Reject application', 'danger', [ExpertBoardMembershipStatus::Pending]),
                self::reasonAction('suspend', 'Suspend', 'Suspend membership', 'warning', [ExpertBoardMembershipStatus::Approved]),
                Action::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (ExpertBoardMembership $record): bool => $record->status === ExpertBoardMembershipStatus::Suspended)
                    ->action(function (ExpertBoardMembership $record): void {
                        try {
                            app(ExpertBoardGovernance::class)->reinstate($record, self::authenticatedUser());
                            Notification::make()->success()->title('Expert Board membership reinstated')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Reinstatement blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Reinstatement failed')->send();
                        }
                    }),
                self::reasonAction('remove', 'Remove', 'Remove from Expert Board', 'danger', [ExpertBoardMembershipStatus::Approved, ExpertBoardMembershipStatus::Suspended]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'auditorProfile.auditor',
            'auditorProfile.competencies',
            'reviewedBy',
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListExpertBoardMemberships::route('/')];
    }

    /** @param list<ExpertBoardMembershipStatus> $statuses */
    private static function reasonAction(
        string $name,
        string $label,
        string $heading,
        string $color,
        array $statuses,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($name === 'suspend' ? 'heroicon-o-pause-circle' : 'heroicon-o-x-circle')
            ->color($color)
            ->visible(fn (ExpertBoardMembership $record): bool => in_array($record->status, $statuses, true))
            ->form([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(3)
                    ->maxLength(2000),
            ])
            ->modalHeading($heading)
            ->action(function (ExpertBoardMembership $record, array $data) use ($name): void {
                try {
                    $governance = app(ExpertBoardGovernance::class);
                    $actor = self::authenticatedUser();
                    $reason = (string) $data['reason'];

                    match ($name) {
                        'reject' => $governance->reject($record, $actor, $reason),
                        'suspend' => $governance->suspend($record, $actor, $reason),
                        'remove' => $governance->remove($record, $actor, $reason),
                        default => throw new DomainStateTransitionException('Unsupported Expert Board action.'),
                    };

                    Notification::make()->success()->title("Expert Board membership {$name}d")->send();
                } catch (DomainStateTransitionException $exception) {
                    Notification::make()->danger()->title(ucfirst($name).' blocked')->body($exception->getMessage())->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title(ucfirst($name).' failed')->send();
                }
            });
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
