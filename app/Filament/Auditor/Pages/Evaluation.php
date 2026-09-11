<?php

namespace App\Filament\Auditor\Pages;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorEvaluation;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditorEvaluationSubmission;
use App\Services\AuditorEvaluationWorkspace;
use App\Services\AuditorEvidenceManagement;
use App\Services\AuditorFindingManagement;
use App\Services\DomainStateTransitionException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class Evaluation extends Page
{
    protected string $view = 'filament.auditor.pages.evaluation';

    public AuditorEvaluation $auditorEvaluation;

    /** @var array<int, array<string, mixed>> */
    public array $criteria = [];

    /** @var array<int, array<string, mixed>> */
    public array $evidenceDraft = [];

    /** @var array<int, array<string, mixed>> */
    public array $findingDraft = [];

    public string $evidenceSufficiency = '';

    public string $audiencePromiseCoherence = '';

    public string $saveState = 'saved';

    public function mount(string $assignment): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this evaluation.');
        }

        $this->auditorEvaluation = app(AuditorEvaluationWorkspace::class)->findFor($user, $assignment);
        $this->evidenceSufficiency = $this->auditorEvaluation->evidence_sufficiency->value;
        $this->audiencePromiseCoherence = $this->auditorEvaluation->audience_promise_coherence->value;
        $this->hydrateDrafts();
    }

    public function saveCriterion(int $criterionId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorEvaluationWorkspace::class)->saveDraft($user, $this->auditorEvaluation, $criterionId, $this->criteria[$criterionId] ?? []);
            $this->auditorEvaluation->refresh();
            $this->hydrateDrafts();
            $this->saveState = 'saved';
            Notification::make()->title('Criterion saved')->success()->send();
        } catch (ValidationException|AuthorizationException|DomainStateTransitionException $exception) {
            $this->saveState = 'error';
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function saveDecisionGates(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorEvaluationWorkspace::class)->saveDecisionGates(
                $user,
                $this->auditorEvaluation,
                $this->evidenceSufficiency,
                $this->audiencePromiseCoherence,
            );
            $this->auditorEvaluation->refresh();
            $this->saveState = 'saved';
            Notification::make()->title('Decision gates saved')->success()->send();
        } catch (ValidationException|AuthorizationException|DomainStateTransitionException $exception) {
            $this->saveState = 'error';
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function addEvidence(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorEvidenceManagement::class)->create($user, $this->auditorEvaluation, $this->evidenceDraft);
            $this->evidenceDraft = [];
            $this->auditorEvaluation->refresh();
            $this->saveState = 'saved';
            Notification::make()->title('Evidence added')->success()->send();
        } catch (ValidationException|AuthorizationException|DomainStateTransitionException $exception) {
            $this->saveState = 'error';
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function deleteEvidence(int $evidenceId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorEvidenceManagement::class)->delete($user, Evidence::query()->findOrFail($evidenceId));
            $this->auditorEvaluation->refresh();
            Notification::make()->title('Evidence removed')->success()->send();
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function addFinding(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorFindingManagement::class)->create($user, $this->auditorEvaluation, $this->findingDraft);
            $this->findingDraft = [];
            $this->auditorEvaluation->refresh();
            $this->saveState = 'saved';
            Notification::make()->title('Finding added')->success()->send();
        } catch (ValidationException|AuthorizationException|DomainStateTransitionException $exception) {
            $this->saveState = 'error';
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function deleteFinding(int $findingId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to modify this evaluation.');
        }

        try {
            app(AuditorFindingManagement::class)->delete($user, Finding::query()->findOrFail($findingId));
            $this->auditorEvaluation->refresh();
            Notification::make()->title('Finding removed')->success()->send();
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function submit(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to submit this evaluation.');
        }

        try {
            foreach ($this->criteria as $criterionId => $draft) {
                app(AuditorEvaluationWorkspace::class)->saveDraft($user, $this->auditorEvaluation, (int) $criterionId, $draft);
            }

            app(AuditorEvaluationWorkspace::class)->saveDecisionGates(
                $user,
                $this->auditorEvaluation,
                $this->evidenceSufficiency,
                $this->audiencePromiseCoherence,
            );
            app(AuditorEvaluationSubmission::class)->submit($user, $this->auditorEvaluation);
            $this->auditorEvaluation->refresh();
            $this->hydrateDrafts();
            Notification::make()->title('Evaluation submitted')->success()->send();
        } catch (ValidationException|AuthorizationException|DomainStateTransitionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function isLocked(): bool
    {
        return $this->auditorEvaluation->locked_at !== null || $this->auditorEvaluation->status === 'submitted';
    }

    /** @return array<string, string> */
    public function evidenceTypes(): array
    {
        return [
            'document' => 'Document',
            'link' => 'Link',
            'observation' => 'Observation',
            'interview' => 'Interview',
            'sample' => 'Sample',
        ];
    }

    /** @return array<string, string> */
    public function evidenceProvenance(): array
    {
        return [
            'observed' => 'Observed',
            'creator_supplied' => 'Creator supplied',
            'third_party' => 'Third party',
        ];
    }

    /** @return array<string, string> */
    public function findingTypes(): array
    {
        return [
            'strength' => 'Strength',
            'weakness' => 'Weakness',
            'risk' => 'Risk',
            'recommendation' => 'Recommendation',
            'factual_clarification' => 'Factual clarification',
        ];
    }

    /** @return array<string, string> */
    public function findingSeverities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    /** @return array<string, string> */
    public function evidenceSufficiencyOptions(): array
    {
        return collect(EvidenceSufficiency::cases())->mapWithKeys(fn (EvidenceSufficiency $value): array => [$value->value => str($value->value)->headline()->toString()])->all();
    }

    /** @return array<string, string> */
    public function audiencePromiseCoherenceOptions(): array
    {
        return collect(AudiencePromiseCoherence::cases())->mapWithKeys(fn (AudiencePromiseCoherence $value): array => [$value->value => str($value->value)->headline()->toString()])->all();
    }

    private function hydrateDrafts(): void
    {
        $this->criteria = [];

        foreach (app(AuditorEvaluationWorkspace::class)->criteria($this->auditorEvaluation) as $criterionResult) {
            $this->criteria[$criterionResult->criterion_id] = [
                'assessment' => $criterionResult->assessment?->value ?? '',
                'score' => $criterionResult->score,
                'confidence' => $criterionResult->confidence,
                'rationale' => $criterionResult->rationale,
            ];
        }
    }
}
