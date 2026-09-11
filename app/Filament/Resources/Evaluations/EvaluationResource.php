<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations;

use App\Enums\EvaluationStatus;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Pages\ViewEvaluation;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
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

final class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Evaluations';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Evaluation')->searchable()->sortable(),
                TextColumn::make('product.title')->label('Product')->searchable()->sortable(),
                TextColumn::make('productRelease.release_identifier')->label('Release')->searchable(),
                TextColumn::make('standardVersion.version')->label('Standard')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EvaluationStatus $state): string => str($state->value)->replace('_', ' ')->headline()->toString()),
                TextColumn::make('decision')->badge()->placeholder('Pending'),
                TextColumn::make('overall_score')->sortable()->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('decide')
                    ->label('Record decision')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Evaluation $record): bool => $record->status === EvaluationStatus::ReadyForDecision)
                    ->action(function (Evaluation $record): void {
                        try {
                            app(EvaluationDecisionService::class)->decide($record, self::authenticatedUser());
                            Notification::make()->success()->title('Evaluation decision recorded')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Decision blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Decision could not be recorded')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'product',
            'productRelease',
            'standardVersion',
            'assignments.auditor',
            'assignments.conflictDeclarations',
            'auditorEvaluations.assignment.auditor',
            'decisions',
            'report.currentVersion',
            'validation.badge',
            'validation.publicVerificationRecord',
            'clarificationRequests.submittedBy',
            'clarificationRequests.resolvedBy',
            'disputes',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'view' => ViewEvaluation::route('/{record}'),
        ];
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return collect(EvaluationStatus::cases())
            ->mapWithKeys(fn (EvaluationStatus $status): array => [
                $status->value => str($status->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
