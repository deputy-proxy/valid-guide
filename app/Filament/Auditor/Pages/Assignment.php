<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\AuditorAssignment;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\AuditorAssignmentAccess;
use App\Services\AuditorConflictDeclarationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

final class Assignment extends Page
{
    protected string $view = 'filament.auditor.pages.assignment';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'assignments/{assignment}';

    protected static ?string $title = 'Assignment';

    public AuditorAssignment $assignment;

    public string $conflictDisclosure = '';

    public function mount(string $assignment): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this assignment.');
        }

        $this->assignment = app(AuditorAssignmentAccess::class)->findFor($user, $assignment);
        $this->conflictDisclosure = $this->conflictDeclaration()->disclosure ?? '';
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

    public function evaluationScope(): string
    {
        $notes = $this->assignment->evaluation->request->intake_notes;

        if (! is_string($notes) || $notes === '') {
            return 'Not specified';
        }

        $decoded = json_decode($notes, true);

        return is_array($decoded) && is_string($decoded['scope'] ?? null)
            ? $decoded['scope']
            : 'Not specified';
    }

    public function conflictDeclaration(): ?ConflictDeclaration
    {
        return $this->assignment->conflictDeclarations()
            ->where('declaration_type', 'assignment')
            ->latest('id')
            ->first();
    }

    public function conflictStatusLabel(): string
    {
        $declaration = $this->conflictDeclaration();

        if ($declaration === null) {
            return 'Declaration required';
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

    public function canEditConflictDeclaration(): bool
    {
        return $this->conflictDeclaration()?->determined_at === null;
    }

    public function submitConflictDeclaration(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to submit this declaration.');
        }

        if (! $this->canEditConflictDeclaration()) {
            throw new AuthorizationException('A determined assignment conflict declaration cannot be changed.');
        }

        app(AuditorConflictDeclarationService::class)->submit(
            $this->assignment,
            $user,
            $this->conflictDisclosure,
        );

        Notification::make()
            ->title('Conflict declaration submitted')
            ->success()
            ->send();
    }

    public function canContinue(): bool
    {
        return app(AuditorAssignmentAccess::class)->hasSubstantiveWorkAccess($this->assignment);
    }
}
