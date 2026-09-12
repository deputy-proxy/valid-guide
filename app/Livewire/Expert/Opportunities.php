<?php

declare(strict_types=1);

namespace App\Livewire\Expert;

use App\Models\ExpertOpportunity;
use App\Models\ExpertOpportunityParticipation;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertOpportunityParticipationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Opportunities extends Component
{
    public string $disclosure = '';

    public ?int $applyingTo = null;

    public ?string $error = null;

    public function apply(int $opportunityId): void
    {
        $this->error = null;
        try {
            $opportunity = ExpertOpportunity::query()->findOrFail($opportunityId);
            app(ExpertOpportunityParticipationService::class)->apply($opportunity, $this->user(), $this->disclosure);
            $this->disclosure = '';
            $this->applyingTo = null;
        } catch (DomainStateTransitionException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function withdraw(int $participationId): void
    {
        $this->error = null;
        try {
            $participation = ExpertOpportunityParticipation::query()->findOrFail($participationId);
            app(ExpertOpportunityParticipationService::class)->withdraw($participation, $this->user());
        } catch (DomainStateTransitionException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function accept(int $participationId): void
    {
        $this->error = null;
        try {
            $participation = ExpertOpportunityParticipation::query()->findOrFail($participationId);
            app(ExpertOpportunityParticipationService::class)->accept($participation, $this->user());
        } catch (DomainStateTransitionException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function render(): View
    {
        $user = $this->user();
        $profile = $user->auditorProfile;
        if ($profile === null) {
            return view('livewire.expert.opportunities', ['opportunities' => collect()]);
        }

        $opportunities = ExpertOpportunity::query()
            ->where('status', 'published')
            ->with(['participations' => fn ($query) => $query->where('auditor_profile_id', $profile->getKey())])
            ->latest('published_at')
            ->get();

        return view('livewire.expert.opportunities', compact('opportunities'));
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
