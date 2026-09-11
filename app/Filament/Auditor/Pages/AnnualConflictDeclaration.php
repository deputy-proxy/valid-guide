<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\User;
use App\Services\AuditorAnnualConflictDeclarationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

final class AnnualConflictDeclaration extends Page
{
    protected string $view = 'filament.auditor.pages.annual-conflict-declaration';

    protected static ?string $slug = 'annual-conflict-declaration';

    protected static ?string $navigationLabel = 'Annual COI Declaration';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $title = 'Annual Conflict-of-Interest Declaration';

    public string $disclosure = '';

    public function mount(): void
    {
        $declaration = $this->currentDeclaration();
        $this->disclosure = $declaration?->disclosure ?? '';
    }

    public function currentDeclaration(): ?AuditorAnnualConflictDeclaration
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this declaration.');
        }

        return AuditorAnnualConflictDeclaration::query()
            ->where('auditor_id', $user->getKey())
            ->where('year', now()->year)
            ->first();
    }

    public function statusLabel(): string
    {
        $declaration = $this->currentDeclaration();

        if ($declaration === null) {
            return 'Missing';
        }

        if ($declaration->determined_at === null) {
            return 'Pending determination';
        }

        return match ($declaration->outcome) {
            'cleared' => 'Cleared',
            'disqualified' => 'Disqualified',
            default => 'Determined',
        };
    }

    public function canEdit(): bool
    {

        return $this->currentDeclaration()?->determined_at === null;
    }

    public function submitDeclaration(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to submit this declaration.');
        }

        if (! $this->canEdit()) {
            throw new AuthorizationException('A determined annual conflict declaration cannot be changed.');
        }

        app(AuditorAnnualConflictDeclarationService::class)->submit($user, $this->disclosure);

        Notification::make()
            ->title('Annual declaration submitted')
            ->success()
            ->send();
    }
}
