<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReleases;

use App\Enums\ProductReleaseStatus;
use App\Filament\Resources\ProductReleases\Pages\CreateProductRelease;
use App\Filament\Resources\ProductReleases\Pages\EditProductRelease;
use App\Filament\Resources\ProductReleases\Pages\ListProductReleases;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\OrganizationContext;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class ProductReleaseResource extends Resource
{
    protected static ?string $model = ProductRelease::class;

    protected static string|UnitEnum|null $navigationGroup = 'Creator';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Product Releases';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('Product')
                ->required()
                ->searchable()
                ->options(fn (): array => self::productOptions())
                ->disabled(fn (string $operation): bool => $operation === 'edit'),
            TextInput::make('edition')->maxLength(255),
            TextInput::make('version')->maxLength(255),
            TextInput::make('release_identifier')->required()->maxLength(255),
            TextInput::make('product_url_snapshot')->url()->maxLength(2048),
            TextInput::make('title_snapshot')->required()->maxLength(255),
            Textarea::make('material_change_notes')->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.title')->label('Product')->searchable()->sortable(),
                TextColumn::make('release_identifier')->label('Release')->searchable()->sortable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductReleaseStatus $state): string => str($state->value)->title()->toString()),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ProductReleaseStatus::Draft->value => 'Draft',
                    ProductReleaseStatus::Current->value => 'Current',
                    ProductReleaseStatus::Superseded->value => 'Superseded',
                    ProductReleaseStatus::Withdrawn->value => 'Withdrawn',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ProductRelease $record): bool => $record->status === ProductReleaseStatus::Draft),
                Action::make('publish')
                    ->label('Make current')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->requiresConfirmation()
                    ->visible(fn (ProductRelease $record): bool => $record->status === ProductReleaseStatus::Draft && self::canReleaseAction($record, 'publish'))
                    ->action(fn (ProductRelease $record): ProductRelease => self::transition($record, ProductReleaseStatus::Current)),
                Action::make('supersede')
                    ->label('Supersede')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->requiresConfirmation()
                    ->visible(fn (ProductRelease $record): bool => $record->status === ProductReleaseStatus::Current && self::canReleaseAction($record, 'supersede'))
                    ->action(fn (ProductRelease $record): ProductRelease => self::transition($record, ProductReleaseStatus::Superseded)),
                Action::make('withdraw')
                    ->label('Withdraw')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProductRelease $record): bool => $record->status === ProductReleaseStatus::Current && self::canReleaseAction($record, 'withdraw'))
                    ->action(fn (ProductRelease $record): ProductRelease => self::transition($record, ProductReleaseStatus::Withdrawn)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = self::authenticatedUser();
        $organization = app(OrganizationContext::class)->current($user);

        return parent::getEloquentQuery()
            ->whereHas('product', fn (Builder $query): Builder => $query->where('organization_id', $organization->getKey()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReleases::route('/'),
            'create' => CreateProductRelease::route('/create'),
            'edit' => EditProductRelease::route('/{record}/edit'),
        ];
    }

    /** @return array<int|string, string> */
    private static function productOptions(): array
    {
        $organization = app(OrganizationContext::class)->current(self::authenticatedUser());

        return Product::query()
            ->where('organization_id', $organization->getKey())
            ->orderBy('title')
            ->pluck('title', 'id')
            ->map(fn (mixed $title): string => (string) $title)
            ->all();
    }

    private static function canReleaseAction(ProductRelease $release, string $ability): bool
    {
        return self::authenticatedUser()->can($ability, $release);
    }

    private static function transition(ProductRelease $release, ProductReleaseStatus $status): ProductRelease
    {
        $actor = self::authenticatedUser();
        $transition = app(\App\Services\ProductReleaseStateTransition::class);
        $updated = $transition->transition($release, $status, $actor);

        Notification::make()
            ->success()
            ->title('Product release updated')
            ->send();

        return $updated;
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
