<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommunityReports;

use App\Enums\CommunityReportStatus;
use App\Filament\Resources\CommunityReports\Pages\ListCommunityReports;
use App\Models\CommunityReport;
use App\Models\User;
use App\Services\CommunityContributionGovernance;
use App\Services\DomainStateTransitionException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class CommunityReportResource extends Resource
{
    protected static ?string $model = CommunityReport::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Community Reports';

    public static function canAccess(): bool
    {
        return self::user()->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('details')->disabled()->rows(5),
            Textarea::make('resolution_note')->disabled()->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contribution.title')->label('Contribution')->searchable(),
                TextColumn::make('reporter.name')->label('Reporter')->searchable(),
                TextColumn::make('reason')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(CommunityReportStatus::cases())),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->color('success')
                    ->visible(fn (CommunityReport $record): bool => $record->status === CommunityReportStatus::Open)
                    ->form([Textarea::make('note')->required()->maxLength(2000)])
                    ->action(fn (CommunityReport $record, array $data) => self::run(fn () => app(CommunityContributionGovernance::class)->resolveReport($record, self::user(), (string) $data['note']), 'Report resolved')),
                Action::make('dismiss')
                    ->color('gray')
                    ->visible(fn (CommunityReport $record): bool => $record->status === CommunityReportStatus::Open)
                    ->form([Textarea::make('note')->required()->maxLength(2000)])
                    ->action(fn (CommunityReport $record, array $data) => self::run(fn () => app(CommunityContributionGovernance::class)->resolveReport($record, self::user(), (string) $data['note'], true), 'Report dismissed')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCommunityReports::route('/')];
    }

    private static function enumOptions(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            if (! $case instanceof BackedEnum) {
                throw new \LogicException('Community report status cases must be backed enums.');
            }
            $options[(string) $case->value] = str((string) $case->value)->replace('_', ' ')->title()->toString();
        }

        return $options;
    }

    private static function run(callable $callback, string $success): void
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

    private static function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
