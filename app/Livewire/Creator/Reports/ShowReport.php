<?php

declare(strict_types=1);

namespace App\Livewire\Creator\Reports;

use App\Enums\ClarificationRequestType;
use App\Enums\DisputeGround;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\ClarificationWorkflow;
use App\Services\CreatorReportAccess;
use App\Services\DisputeWorkflow;
use App\Services\DomainStateTransitionException;
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

    /** @var array<string, mixed> */
    public array $reportData = [];

    public function mount(int|string $evaluationId): void
    {
        $this->evaluationId = (int) $evaluationId;
        $this->loadReport();
    }

    public function render(): View
    {
        return view('livewire.creator.reports.show');
    }

    public function selectVersion(int $versionId): void
    {
        $this->selectedVersionId = $versionId;
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
                $evaluation->request->organization,
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

            $grounds = collect($this->disputeGrounds)
                ->map(fn (string $ground): ?DisputeGround => DisputeGround::tryFrom($ground))
                ->filter()
                ->values()
                ->all();

            if (count($grounds) !== count($this->disputeGrounds)) {
                throw new DomainStateTransitionException('One or more dispute grounds are invalid.');
            }

            $evaluation = $this->evaluation();
            app(DisputeWorkflow::class)->submit(
                $evaluation,
                $evaluation->request->organization,
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

    private function loadReport(): void
    {
        $this->reportData = app(CreatorReportAccess::class)->show(
            $this->authenticatedUser(),
            $this->evaluationId,
        );

        if ($this->selectedVersionId === null) {
            $currentId = $this->reportData['report']['current_version_id'] ?? null;
            $this->selectedVersionId = is_int($currentId) ? $currentId : null;
        }
    }

    private function evaluation(): Evaluation
    {
        return Evaluation::query()
            ->with('request.organization')
            ->findOrFail($this->evaluationId);
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
