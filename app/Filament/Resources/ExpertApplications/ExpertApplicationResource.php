<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertApplications;

use App\Enums\ExpertOpportunityParticipationStatus;
use App\Models\ExpertOpportunityParticipation;
use App\Models\User;
use App\Services\ExpertOpportunityParticipationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use Throwable;

final class ExpertApplicationResource extends Resource
{
    protected static ?string $model = ExpertOpportunityParticipation::class;
    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Expert Applications';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user instanceof User && $user->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('opportunity.title')->label('Opportunity')->searchable(),
            TextColumn::make('auditorProfile.auditor.name')->label('Expert')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('conflict_outcome')->label('Conflict')->badge()->placeholder('Pending'),
            TextColumn::make('applied_at')->dateTime()->sortable(),
        ])->recordActions([
            Action::make('clearConflict')->label('Clear conflict')->color('success')->visible(fn (ExpertOpportunityParticipation $record): bool => $record->conflict_determined_at === null)->action(fn (ExpertOpportunityParticipation $record) => self::run(fn () => app(ExpertOpportunityParticipationService::class)->determineConflict($record, self::user(), 'cleared'), 'Conflict cleared')),
            Action::make('select')->color('success')->visible(fn (ExpertOpportunityParticipation $record): bool => $record->status === ExpertOpportunityParticipationStatus::Applied && $record->conflict_outcome === 'cleared')->action(fn (ExpertOpportunityParticipation $record) => self::run(fn () => app(ExpertOpportunityParticipationService::class)->select($record, self::user()), 'Expert selected')),
            Action::make('reject')->color('danger')->visible(fn (ExpertOpportunityParticipation $record): bool => $record->status === ExpertOpportunityParticipationStatus::Applied)->form([Textarea::make('reason')->required()->minLength(3)->maxLength(2000)])->action(fn (ExpertOpportunityParticipation $record, array $data) => self::run(fn () => app(ExpertOpportunityParticipationService::class)->reject($record, self::user(), (string) $data['reason']), 'Application rejected')),
            Action::make('complete')->color('success')->visible(fn (ExpertOpportunityParticipation $record): bool => $record->status === ExpertOpportunityParticipationStatus::Accepted)->action(fn (ExpertOpportunityParticipation $record) => self::run(fn () => app(ExpertOpportunityParticipationService::class)->complete($record, self::user()), 'Engagement completed')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListExpertApplications::route('/')];
    }

    private static function run(callable $callback, string $success): void
    {
        try { $callback(); Notification::make()->success()->title($success)->send(); }
        catch (\App\Services\DomainStateTransitionException $exception) { Notification::make()->danger()->title('Action blocked')->body($exception->getMessage())->send(); }
        catch (Throwable $exception) { report($exception); Notification::make()->danger()->title('Action failed')->send(); }
    }

    private static function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        return $user;
    }
}
