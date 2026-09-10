<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProductManagement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|UnitEnum|null $navigationGroup = 'Creator';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255),
            Select::make('product_type')->required()->options(self::productTypeOptions()),
            TextInput::make('subject_area')->maxLength(255),
            Textarea::make('description')->required()->rows(5),
            TextInput::make('canonical_url')->required()->url()->maxLength(2048),
            TextInput::make('url')->url()->maxLength(2048),
            TextInput::make('reference_price')->numeric()->minValue(0),
            TextInput::make('reference_currency')->maxLength(3)->minLength(3),
            Textarea::make('target_audience')->required()->rows(3),
            Repeater::make('claimed_outcomes')
                ->required()
                ->minItems(1)
                ->simple(TextInput::make('outcome')->required()->maxLength(1000)),
            TextInput::make('language')->required()->maxLength(16),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('product_type')
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => str($state->value)->replace('_', ' ')->title()->toString()),
                TextColumn::make('language')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => str($state->value)->title()->toString()),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('product_type')->options(self::productTypeOptions()),
                SelectFilter::make('status')->options([
                    ProductStatus::Active->value => 'Active',
                    ProductStatus::Archived->value => 'Archived',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->status === ProductStatus::Active && self::canArchiveProduct($record))
                    ->action(function (Product $record): void {
                        $user = self::authenticatedUser();
                        app(ProductManagement::class)->archive($user, $record);
                        Notification::make()->success()->title('Product archived')->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = self::authenticatedUser();
        $organization = app(OrganizationContext::class)->current($user);

        return parent::getEloquentQuery()->where('organization_id', $organization->getKey());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function productTypeOptions(): array
    {
        return collect(ProductType::cases())
            ->mapWithKeys(fn (ProductType $type): array => [
                $type->value => str($type->value)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }

    private static function canArchiveProduct(Product $product): bool
    {
        return self::authenticatedUser()->can('archive', $product);
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
