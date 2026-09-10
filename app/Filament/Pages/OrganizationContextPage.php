<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\OrganizationContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

final class OrganizationContextPage extends Page
{
    protected string $view = 'filament.pages.organization-context';

    protected static ?string $navigationGroup = 'Creator';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Organization';

    public function mount(): void
    {
        app(OrganizationContext::class)->current(self::authenticatedUser());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switchOrganization')
                ->label('Switch organization')
                ->schema([
                    Select::make('organization_id')
                        ->label('Organization')
                        ->options(fn (): array => self::organizations())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $organizationId = $data['organization_id'] ?? null;
                    abort_unless(is_int($organizationId) || is_string($organizationId), 422);

                    app(OrganizationContext::class)->select(self::authenticatedUser(), $organizationId);

                    Notification::make()
                        ->success()
                        ->title('Organization context changed')
                        ->send();
                }),
        ];
    }

    /** @return array<int, string> */
    private static function organizations(): array
    {
        return self::authenticatedUser()->organizations()
            ->orderBy('organizations.name')
            ->pluck('organizations.name', 'organizations.id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
