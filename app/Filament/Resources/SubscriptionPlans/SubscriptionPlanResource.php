<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubscriptionPlans;

use App\Enums\SubscriptionPlanStatus;
use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Models\SubscriptionPlan;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Admin';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Subscription Plans';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(64),
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description')->rows(4),
            Select::make('status')->required()->options(self::statusOptions()),
            TextInput::make('price_minor')->required()->numeric()->minValue(0),
            TextInput::make('currency')->required()->minLength(3)->maxLength(3),
            Select::make('billing_interval')->required()->options([
                'month' => 'Month',
                'year' => 'Year',
                'week' => 'Week',
                'day' => 'Day',
            ]),
            TextInput::make('billing_interval_count')->required()->numeric()->minValue(1)->default(1),
            Repeater::make('entitlements')
                ->relationship()
                ->schema([
                    TextInput::make('code')->required()->maxLength(64),
                    TextInput::make('name')->required()->maxLength(255),
                    Textarea::make('description')->rows(2),
                    TextInput::make('quantity')->numeric()->minValue(0),
                    TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
                ])
                ->columns(2)
                ->collapsed(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('price_minor')->numeric()->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('billing_interval_count')->label('Interval'),
                TextColumn::make('entitlements_count')->counts('entitlements')->label('Entitlements'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(SubscriptionPlanStatus::cases())
            ->mapWithKeys(fn (SubscriptionPlanStatus $status): array => [
                $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }
}
