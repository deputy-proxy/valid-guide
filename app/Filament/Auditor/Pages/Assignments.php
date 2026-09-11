<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\AuditorAssignment;
use App\Models\User;
use App\Services\AuditorAssignmentAccess;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

final class Assignments extends Page
{
    protected string $view = 'filament.auditor.pages.assignments';

    protected static ?string $slug = 'assignments';

    protected static ?string $navigationLabel = 'My Assignments';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $title = 'My Assignments';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(AuditorAssignmentAccess::class)->isClearedAuditor($user);
    }

    /**
     * @return Collection<int, AuditorAssignment>
     */
    public function getAssignments(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return app(AuditorAssignmentAccess::class)->queryFor($user)->get();
    }

    public function statusLabel(AuditorAssignment $assignment): string
    {
        return str($assignment->status)->replace('_', ' ')->headline()->toString();
    }

    public function readinessLabel(AuditorAssignment $assignment): string
    {
        return match ($assignment->status) {
            'offered' => 'Awaiting acceptance',
            'accepted' => 'Ready for Auditor work',
            'cleared' => 'Cleared',
            'completed' => 'Completed',
            'declined', 'disqualified' => 'Blocked',
            default => 'Status requires attention',
        };
    }
}
