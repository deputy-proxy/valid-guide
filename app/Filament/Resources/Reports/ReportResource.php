<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ReportDelivery;
use App\Services\ReportVersioning;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Reports';

    public static function canAccess(): bool
    {
        return self::authenticatedUser()->isPlatformAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Report')->sortable(),
                TextColumn::make('evaluation.id')->label('Evaluation')->sortable(),
                TextColumn::make('currentVersion.version_number')->label('Current version')->sortable(),
                TextColumn::make('creator_visible_at')->label('Creator visible')->dateTime(),
                TextColumn::make('public_visible_at')->label('Public visible')->dateTime(),
                TextColumn::make('delivered_at')->dateTime()->sortable(),
                TextColumn::make('versions_count')->label('Versions')->counts('versions'),
            ])
            ->recordActions([
                Action::make('revise')
                    ->label('Create revision')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (Report $record): bool => $record->delivered_at === null && $record->currentVersion !== null)
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('content_structure')
                            ->label('Content structure JSON')
                            ->required()
                            ->rows(10)
                            ->default(fn (Report $record): string => json_encode($record->currentVersion->content_structure ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
                        Textarea::make('abstract')->label('Abstract')->rows(4),
                        Textarea::make('change_reason')->label('Change reason')->required()->rows(3),
                    ])
                    ->action(function (Report $record, array $data): void {
                        try {
                            $content = json_decode((string) $data['content_structure'], true, 512, JSON_THROW_ON_ERROR);
                            if (! is_array($content)) {
                                throw new DomainStateTransitionException('Report content must be a JSON object or array.');
                            }

                            app(ReportVersioning::class)->createRevision(
                                $record,
                                self::authenticatedUser(),
                                $content,
                                $data['abstract'] === null ? null : (string) $data['abstract'],
                                (string) $data['change_reason'],
                            );
                            Notification::make()->success()->title('Report revision created')->send();
                        } catch (DomainStateTransitionException|\JsonException $exception) {
                            Notification::make()->danger()->title('Report revision blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Report revision failed')->send();
                        }
                    }),
                Action::make('deliver')
                    ->label('Deliver report')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->delivered_at === null)
                    ->action(function (Report $record): void {
                        try {
                            app(ReportDelivery::class)->deliver($record, self::authenticatedUser());
                            Notification::make()->success()->title('Report delivery recorded')->send();
                        } catch (DomainStateTransitionException $exception) {
                            Notification::make()->danger()->title('Report delivery blocked')->body($exception->getMessage())->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->danger()->title('Report delivery failed')->send();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['evaluation', 'currentVersion']);
    }

    public static function getPages(): array
    {
        return ['index' => ListReports::route('/')];
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
