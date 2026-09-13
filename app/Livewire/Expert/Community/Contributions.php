<?php

declare(strict_types=1);

namespace App\Livewire\Expert\Community;

use App\Models\CommunityContribution;
use App\Models\User;
use App\Services\CommunityContributionGovernance;
use App\Services\DomainStateTransitionException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Contributions extends Component
{
    public string $title = '';
    public string $body = '';
    public ?int $editingId = null;
    public ?string $error = null;
    public ?string $message = null;

    public function save(): void
    {
        $this->resetFeedback();

        try {
            $governance = app(CommunityContributionGovernance::class);
            if ($this->editingId === null) {
                $contribution = $governance->createDraft($this->user(), $this->title, $this->body);
            } else {
                $contribution = CommunityContribution::query()->findOrFail($this->editingId);
                $contribution = $governance->updateDraft($contribution, $this->user(), $this->title, $this->body);
            }
            $this->editingId = $contribution->getKey();
            $this->title = $contribution->title;
            $this->body = $contribution->body;
            $this->message = 'Draft saved.';
        } catch (DomainStateTransitionException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function submit(int $id): void
    {
        $this->resetFeedback();

        try {
            $contribution = CommunityContribution::query()->findOrFail($id);
            app(CommunityContributionGovernance::class)->submit($contribution, $this->user());
            $this->message = 'Contribution submitted for review.';
            $this->reset(['editingId', 'title', 'body']);
        } catch (DomainStateTransitionException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function edit(int $id): void
    {
        $this->resetFeedback();
        $contribution = CommunityContribution::query()->findOrFail($id);
        abort_unless($contribution->auditorProfile?->auditor_id === $this->user()->getKey(), 403);
        abort_unless(in_array($contribution->status->value, ['draft', 'rejected'], true), 403);
        $this->editingId = $contribution->getKey();
        $this->title = $contribution->title;
        $this->body = $contribution->body;
    }

    public function render(): View
    {
        $profile = $this->user()->auditorProfile;
        $contributions = $profile === null
            ? collect()
            : $profile->communityContributions()->latest()->get();

        return view('livewire.expert.community.contributions', compact('contributions'));
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function resetFeedback(): void
    {
        $this->error = null;
        $this->message = null;
    }
}
