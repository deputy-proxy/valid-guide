<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommunityContributions;

use App\Enums\CommunityContributionStatus;
use App\Filament\Resources\CommunityContributions\Pages\ListCommunityContributions;
use App\Models\CommunityContribution;
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

final class CommunityContributionResource extends Resource
{
    protected static ?string $model = CommunityContribution::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Community Contributions';

    public static function canAccess(): bool
    {
        return self::user()->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('title')->disabled(),
            Textarea::make('body')->disabled()->rows(12),
            Textarea::make('moderation_reason')->disabled()->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('auditorProfile.auditor.name')->label('Author')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('reports_count')->counts('reports')->label('Reports'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(CommunityContributionStatus::cases())),
            ])
            ->recordActions([
                Action::make('publish')
                    ->color('success')
                    ->visible(fn (CommunityContribution $record): bool => $record->status === CommunityContributionStatus::PendingReview)
                    ->action(fn (CommunityContribution $record) => self::run(fn () => app(CommunityContributionGovernance::class)->publish($record, self::user()), 'Contribution published')),
                Action::make('reject')
                    ->color('warning')
                    ->visible(fn (CommunityContribution $record): bool => $record->status === CommunityContributionStatus::PendingReview)
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (CommunityContribution $record, array $data) => self::run(fn () => app(CommunityContributionGovernance::class)->reject($record, self::user(), (string) $data['reason']), 'Contribution rejected')),
                Action::make('hide')
                    ->color('warning')
                    ->visible(fn (CommunityContribution $record): bool => $record->status === CommunityContributionStatus::Published)
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (CommunityContribution $record, array $data) => self::run(fn () => app(CommunityContributionGovernance::class)->hide($record, self::user(), (string) $data['reason']), 'Contribution hidden')),
                Action::make('remove')
                    ->color('danger')
                    ->visible(fn (CommunityContribution $record): bool => in_array($record->status, [CommunityContributionStatus::Published, CommunityContributionStatus::Hidden], true))
                    ->requiresConfirmation()
                    ->form([Textarea::make('reason')->required()->maxLength(2000)])
                    ->action(fn (CommunityContribution $record, array $data) => self::run(fn () => app(CommunityContributionGovernance::class)->remove($record, self::user(), (string) $data['reason']), 'Contribution removed')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCommunityContributions::route('/')];
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            if ($case instanceof BackedEnum) {
                $options[(string) $case->value] = str((string) $case->value)->replace('_', ' ')->title()->toString();

                continue;
            }

            throw new \LogicException('Community contribution status cases must be backed enums.');
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
