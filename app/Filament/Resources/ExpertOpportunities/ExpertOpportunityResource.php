<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpertOpportunities;

use App\Enums\ExpertiseArea;
use App\Enums\ExpertOpportunityStatus;
use App\Enums\ExpertOpportunityType;
use App\Enums\ProductType;
use App\Filament\Resources\ExpertOpportunities\Pages\CreateExpertOpportunity;
use App\Filament\Resources\ExpertOpportunities\Pages\EditExpertOpportunity;
use App\Filament\Resources\ExpertOpportunities\Pages\ListExpertOpportunities;
use App\Models\ExpertOpportunity;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertOpportunityGovernance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use Throwable;

final class ExpertOpportunityResource extends Resource
{
    protected static ?string $model = ExpertOpportunity::class;
    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Expert Opportunities';

    public static function canAccess(): bool
    {
        return self::user()->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('description')->required()->rows(5),
            Select::make('type')->required()->options(self::enumOptions(ExpertOpportunityType::cases())),
            Select::make('expertise_areas')->multiple()->options(self::enumOptions(ExpertiseArea::cases(), true)),
            Select::make('product_types')->multiple()->options(self::enumOptions(ProductType::cases(), true)),
            TextInput::make('workload')->maxLength(255),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at'),
            DateTimePicker::make('application_deadline'),
            Toggle::make('eligibility_constraints.methodology_literate')->label('Requires methodology literacy')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('application_deadline')->dateTime()->sortable(),
                TextColumn::make('participations_count')->counts('participations')->label('Applications'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::enumOptions(ExpertOpportunityStatus::cases())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (ExpertOpportunity $record): bool => $record->status === ExpertOpportunityStatus::Draft),
                Action::make('publish')
                    ->color('success')
                    ->visible(fn (ExpertOpportunity $record): bool => $record->status === ExpertOpportunityStatus::Draft)
                    ->action(fn (ExpertOpportunity $record) => self::run(fn () => app(ExpertOpportunityGovernance::class)->publish($record, self::user()), 'Opportunity published')),
                Action::make('close')
                    ->color('warning')
                    ->visible(fn (ExpertOpportunity $record): bool => $record->status === ExpertOpportunityStatus::Published)
                    ->action(fn (ExpertOpportunity $record) => self::run(fn () => app(ExpertOpportunityGovernance::class)->close($record, self::user()), 'Opportunity closed')),
                Action::make('cancel')
                    ->color('danger')
                    ->visible(fn (ExpertOpportunity $record): bool => in_array($record->status, [ExpertOpportunityStatus::Draft, ExpertOpportunityStatus::Published, ExpertOpportunityStatus::Closed], true))
                    ->requiresConfirmation()
                    ->action(fn (ExpertOpportunity $record) => self::run(fn () => app(ExpertOpportunityGovernance::class)->cancel($record, self::user()), 'Opportunity cancelled')),
                Action::make('complete')
                    ->color('success')
                    ->visible(fn (ExpertOpportunity $record): bool => $record->status === ExpertOpportunityStatus::Closed)
                    ->action(fn (ExpertOpportunity $record) => self::run(fn () => app(ExpertOpportunityGovernance::class)->complete($record, self::user()), 'Opportunity completed')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpertOpportunities::route('/'),
            'create' => CreateExpertOpportunity::route('/create'),
            'edit' => EditExpertOpportunity::route('/{record}/edit'),
        ];
    }

    /** @param list<BackedEnum> $cases */
    private static function enumOptions(array $cases, bool $replaceUnderscores = false): array
    {
        return collect($cases)->mapWithKeys(function (BackedEnum $case) use ($replaceUnderscores): array {
            $label = str($case->value);
            if ($replaceUnderscores) {
                $label = $label->replace('_', ' ');
            }

            return [$case->value => $label->title()->toString()];
        })->all();
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
