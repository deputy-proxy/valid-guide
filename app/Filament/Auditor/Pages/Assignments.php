<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\AuditorAssignment;
use App\Models\User;
use App\Services\AuditorAssignmentAccess;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

final class Assignments extends Page
{
    protected string $view = 'filament.auditor.pages.assignments';

    protected static ?string $slug = 'assignments';

    protected static ?string $navigationLabel = 'My Assignments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $title = 'My Assignments';

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

    public function evaluationScope(AuditorAssignment $assignment): string
    {
        $notes = $assignment->evaluation->request->intake_notes;

        if (! is_string($notes) || $notes === '') {
            return 'Not specified';
        }

        $decoded = json_decode($notes, true);

        return is_array($decoded) && is_string($decoded['scope'] ?? null)
            ? $decoded['scope']
            : 'Not specified';
    }
}
