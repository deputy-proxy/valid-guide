<?php

declare(strict_types=1);

namespace App\Livewire\Creator\Reports;

use App\Enums\ClarificationRequestType;
use App\Enums\CreatorActionStatus;
use App\Enums\DisputeGround;
use App\Enums\ImprovementOpportunityStatus;
use App\Models\Evaluation;
use App\Models\ImprovementOpportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\ClarificationWorkflow;
use App\Services\CreatorActionPlanner;
use App\Services\CreatorActionWorkflow;
use App\Services\CreatorReportAccess;
use App\Services\DisputeWorkflow;
use App\Services\DomainStateTransitionException;
use App\Services\ImprovementOpportunityWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

final class ShowReport extends Component
{
    #[Locked]
    public int $evaluationId;

    public ?int $selectedVersionId = null;

    public string $clarificationType = 'report';

    public string $clarificationMessage = '';

    /** @var array<int, string> */
    public array $disputeGrounds = [];

    public string $disputeStatement = '';

    public string $completionEvidence = '';

    /** @var array<string, mixed> */
    public array $reportData = [];

    /** @var array<int, array<string, mixed>> */
    public array $opportunities = [];

    public function mount(int|string $evaluationId): void
    {
        $this->evaluationId = (int) $evaluationId;
        $this->loadReport();
        $this->generateActionPlan();
        $this->loadReport();
    }

    public function render(): View
    {
        return view('livewire.creator.reports.show');
    }

    public function selectVersion(int $versionId): void
    {
        $versions = $this->reportData['report']['versions'] ?? [];
        $availableVersionIds = [];

        if (is_array($versions)) {
            foreach ($versions as $version) {
                if (is_array($version) && isset($version['id']) && is_numeric($version['id'])) {
                    $availableVersionIds[] = (int) $version['id'];
                }
            }
        }

        if (! in_array($versionId, $availableVersionIds, true)) {
            $this->addError('version', 'The selected report version is not available.');

            return;
        }

        $this->resetErrorBag('version');
        $this->selectedVersionId = $versionId;
    }

    public function transitionAction(int $actionId, string $status): void
    {
        $this->resetErrorBag('action');

        try {
            $actionStatus = CreatorActionStatus::tryFrom($status);
            if ($actionStatus === null) {
                throw new DomainStateTransitionException('The creator action status is invalid.');
            }

            $action = $this->evaluation()
                ->creatorActions()
                ->whereKey($actionId)
                ->firstOrFail();

            app(CreatorActionWorkflow::class)->transition(
                $action,
                $this->authenticatedUser(),
                $actionStatus,
            );

            $this->loadReport();
            session()->flash('success', 'Creator action updated.');
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('action', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('action', 'The creator action could not be updated.');
        }
    }

    public function transitionOpportunity(int $opportunityId, string $status): void
    {
        $this->resetErrorBag('opportunity');

        try {
            $opportunityStatus = ImprovementOpportunityStatus::tryFrom($status);
            if ($opportunityStatus === null) {
                throw new DomainStateTransitionException('The improvement opportunity status is invalid.');
            }

            $opportunity = $this->evaluation()
                ->improvementOpportunities()
                ->whereKey($opportunityId)
                ->firstOrFail();

            app(ImprovementOpportunityWorkflow::class)->transition(
                $opportunity,
                $this->authenticatedUser(),
                $opportunityStatus,
            );

            $this->loadOpportunities();
            session()->flash('success', 'Improvement opportunity updated.');
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('opportunity', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('opportunity', 'The improvement opportunity could not be updated.');
        }
    }

    public function completeOpportunity(int $opportunityId): void
    {
        $this->resetErrorBag('opportunity');

        try {
            $opportunity = $this->evaluation()
                ->improvementOpportunities()
                ->whereKey($opportunityId)
                ->firstOrFail();

            app(ImprovementOpportunityWorkflow::class)->complete(
                $opportunity,
                $this->authenticatedUser(),
                $this->completionEvidence,
            );

            $this->completionEvidence = '';
            $this->loadOpportunities();
            session()->flash('success', 'Improvement opportunity completed.');
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('opportunity', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('opportunity', 'The improvement opportunity could not be completed.');
        }
    }

    public function submitClarification(): void
    {
        $this->resetErrorBag();

        try {
            $type = ClarificationRequestType::tryFrom($this->clarificationType);
            if ($type === null) {
                throw new DomainStateTransitionException('The clarification type is invalid.');
            }

            $evaluation = $this->evaluation();
            app(ClarificationWorkflow::class)->submit(
                $evaluation,
                $this->creatorOrganization($evaluation),
                $this->authenticatedUser(),
                $type,
                $this->clarificationMessage,
            );

            $this->clarificationMessage = '';
            $this->loadReport();
            session()->flash('success', 'Clarification request submitted.');
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('clarification', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('clarification', 'The clarification request could not be submitted.');
        }
    }

    public function submitDispute(): void
    {
        $this->resetErrorBag();

        try {
            $this->validate([
                'disputeGrounds' => ['required', 'array', 'min:1'],
                'disputeStatement' => ['required', 'string', 'max:10000'],
            ]);

            $grounds = [];
            foreach ($this->disputeGrounds as $ground) {
                $disputeGround = DisputeGround::tryFrom($ground);
                if ($disputeGround === null) {
                    throw new DomainStateTransitionException('One or more dispute grounds are invalid.');
                }

                $grounds[] = $disputeGround;
            }

            $evaluation = $this->evaluation();
            app(DisputeWorkflow::class)->submit(
                $evaluation,
                $this->creatorOrganization($evaluation),
                $this->authenticatedUser(),
                $grounds,
                $this->disputeStatement,
            );

            $this->disputeGrounds = [];
            $this->disputeStatement = '';
            $this->loadReport();
            session()->flash('success', 'Formal dispute submitted.');
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('dispute', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('dispute', 'The formal dispute could not be submitted.');
        }
    }

    /** @return array<string, string> */
    public function clarificationTypeOptions(): array
    {
        return collect(ClarificationRequestType::cases())
            ->mapWithKeys(fn (ClarificationRequestType $type): array => [
                $type->value => str($type->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public function disputeGroundOptions(): array
    {
        return collect(DisputeGround::cases())
            ->mapWithKeys(fn (DisputeGround $ground): array => [
                $ground->value => str($ground->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    private function generateActionPlan(): void
    {
        $evaluation = $this->evaluation();
        app(CreatorActionPlanner::class)->generate(
            $evaluation,
            $this->creatorOrganization($evaluation),
            $this->authenticatedUser(),
        );
    }

    private function loadReport(): void
    {
        $this->reportData = app(CreatorReportAccess::class)->show(
            $this->authenticatedUser(),
            $this->evaluationId,
        );

        if ($this->selectedVersionId === null) {
            $currentId = $this->reportData['report']['current_version_id'] ?? null;
            $this->selectedVersionId = is_numeric($currentId) ? (int) $currentId : null;
        }

        $this->loadOpportunities();
    }

    private function loadOpportunities(): void
    {
        $this->opportunities = $this->evaluation()
            ->improvementOpportunities()
            ->with(['finding', 'improvementGuidance', 'assignee', 'creatorAction'])
            ->latest('id')
            ->get()
            ->map(fn (ImprovementOpportunity $opportunity): array => [
                'id' => $opportunity->getKey(),
                'title' => $opportunity->title,
                'target_outcome' => $opportunity->target_outcome,
                'evidence_required' => $opportunity->evidence_required,
                'completion_evidence' => $opportunity->completion_evidence,
                'priority' => $opportunity->priority->value,
                'status' => $opportunity->status->value,
                'due_at' => $opportunity->due_at?->toISOString(),
                'completed_at' => $opportunity->completed_at?->toISOString(),
                'assignee_name' => $opportunity->assignee?->name,
                'finding_title' => $opportunity->finding?->title,
                'guidance_id' => $opportunity->improvement_guidance_id,
                'action_id' => $opportunity->creatorAction?->getKey(),
            ])
            ->values()
            ->all();
    }

    private function evaluation(): Evaluation
    {
        $evaluation = Evaluation::query()
            ->with('request.organization')
            ->findOrFail($this->evaluationId);

        $this->creatorOrganization($evaluation);

        return $evaluation;
    }

    private function creatorOrganization(Evaluation $evaluation): Organization
    {
        $request = $evaluation->request;
        $organization = $request?->organization;

        if ($organization === null) {
            throw new AuthorizationException('The evaluation does not have an accessible creator organization.');
        }

        return $organization;
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Authentication is required.');
        }

        return $user;
    }
}
