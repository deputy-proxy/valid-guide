<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\AuditorAssignment;
use App\Models\User;
use App\Services\AuditorAssignmentAccess;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

final class Assignment extends Page
{
    protected string $view = 'filament.auditor.pages.assignment';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'assignments/{assignment}';

    protected static ?string $title = 'Assignment';

    public AuditorAssignment $assignment;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(AuditorAssignmentAccess::class)->isClearedAuditor($user);
    }

    public function mount(string $assignment): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this assignment.');
        }

        $this->assignment = app(AuditorAssignmentAccess::class)->findFor($user, $assignment);
    }

    public function statusLabel(): string
    {
        return str($this->assignment->status)->replace('_', ' ')->headline()->toString();
    }

    public function readinessLabel(): string
    {
        return match ($this->assignment->status) {
            'offered' => 'Awaiting acceptance',
            'accepted' => 'Ready for Auditor work',
            'cleared' => 'Cleared',
            'completed' => 'Completed',
            'declined', 'disqualified' => 'Blocked',
            default => 'Status requires attention',
        };
    }

    public function canContinue(): bool
    {
        return in_array($this->assignment->status, ['accepted', 'cleared'], true);
    }
}
