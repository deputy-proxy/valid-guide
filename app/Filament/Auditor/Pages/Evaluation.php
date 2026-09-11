<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionAssessment;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorEvaluation;
use App\Models\CriterionResult;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditorEvaluationFinalization;
use App\Services\AuditorEvaluationSubmission;
use App\Services\AuditorEvaluationWorkspace;
use App\Services\AuditorEvidenceManagement;
use App\Services\AuditorFindingManagement;
use App\Services\DomainStateTransitionException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class Evaluation extends Page
{
    protected string $view = 'filament.auditor.pages.evaluation';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'assignments/{assignment}/evaluation';

    protected static ?string $title = 'Evaluation workspace';

    public AuditorEvaluation $auditorEvaluation;

    /** @var array<int,array{assessment:string,score:string,rationale:string,confidence:string}> */
    public array $drafts = [];

    /** @var array{criterion_id:string,type:string,title:string,description:string,source_url:string,provenance:string} */
    public array $evidenceDraft = [
        'criterion_id' => '',
        'type' => 'link',
        'title' => '',
        'description' => '',
        'source_url' => '',
        'provenance' => 'observed',
    ];

    /** @var array{criterion_id:string,type:string,severity:string,title:string,description:string} */
    public array $findingDraft = [
        'criterion_id' => '',
        'type' => 'weakness',
        'severity' => 'medium',
        'title' => '',
        'description' => '',
    ];

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

        $draft = $this->drafts[$criterionId] ?? null;

        if ($draft === null) {
            throw ValidationException::withMessages([
                'drafts' => 'The criterion draft could not be found. Reload the workspace and try again.',
            ]);
        }

        $this->saveState = 'saving';

        try {
            app(AuditorEvaluationWorkspace::class)->saveDraft(
                $user,
                $this->auditorEvaluation,
                $criterionId,
                $draft['assessment'],
                $draft['score'] === '' ? null : (float) $draft['score'],
                $draft['rationale'],
                $draft['confidence'] === '' ? null : (float) $draft['confidence'],
            );

            $this->auditorEvaluation->refresh();
            $this->hydrateDraft($criterionId);
            $this->saveState = 'saved';
        } catch (AuthorizationException|ValidationException|DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Unable to save criterion')
                ->body($exception->getMessage())
                ->danger()
                ->send();
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

            Notification::make()
                ->title('Decision gates saved')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException|DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Unable to save decision gates')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addEvidence(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to add evidence.');
        }

        try {
            app(AuditorEvidenceManagement::class)->create($user, $this->auditorEvaluation, $this->evidenceDraft);
            $this->auditorEvaluation->refresh();
            $this->evidenceDraft = [
                'criterion_id' => '',
                'type' => 'link',
                'title' => '',
                'description' => '',
                'source_url' => '',
                'provenance' => 'observed',
            ];
            $this->saveState = 'saved';

            Notification::make()
                ->title('Evidence reference saved')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException|DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Unable to save evidence')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteEvidence(int $evidenceId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to delete evidence.');
        }

        $evidence = Evidence::query()->findOrFail($evidenceId);

        try {
            app(AuditorEvidenceManagement::class)->delete($user, $evidence);
            $this->auditorEvaluation->refresh();
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            Notification::make()
                ->title('Unable to delete evidence')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addFinding(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to add findings.');
        }

        try {
            app(AuditorFindingManagement::class)->create($user, $this->auditorEvaluation, $this->findingDraft);
            $this->auditorEvaluation->refresh();
            $this->findingDraft = [
                'criterion_id' => '',
                'type' => 'weakness',
                'severity' => 'medium',
                'title' => '',
                'description' => '',
            ];
            $this->saveState = 'saved';

            Notification::make()
                ->title('Finding saved')
                ->success()
                ->send();
        } catch (AuthorizationException|ValidationException|DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Unable to save finding')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteFinding(int $findingId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to delete findings.');
        }

        $finding = Finding::query()->findOrFail($findingId);

        try {
            app(AuditorFindingManagement::class)->delete($user, $finding);
            $this->auditorEvaluation->refresh();
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            Notification::make()
                ->title('Unable to delete finding')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function submit(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to submit this evaluation.');
        }

        foreach ($this->drafts as $criterionId => $draft) {
            if ($this->resultFor((int) $criterionId)?->submitted_at !== null) {
                continue;
            }

            $this->saveCriterion((int) $criterionId);

            if ($this->saveState === 'failed') {
                return;
            }
        }

        $this->saveDecisionGates();
        if ($this->saveState === 'failed') {
            return;
        }

        try {
            app(AuditorEvaluationSubmission::class)->submit($this->auditorEvaluation);
            $this->auditorEvaluation->refresh();

            app(AuditorEvaluationFinalization::class)->finalize($this->auditorEvaluation, $user);
            $this->auditorEvaluation->refresh();
            $this->hydrateDrafts();

            Notification::make()
                ->title('Evaluation submitted')
                ->body('Your Auditor work is locked and the Evaluation has been handed to the decision workflow.')
                ->success()
                ->send();
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Evaluation could not be finalized')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function isLocked(): bool
    {
        return $this->auditorEvaluation->locked_at !== null
            || $this->auditorEvaluation->status !== 'draft';
    }

    public function statusLabel(): string
    {
        return $this->isLocked() ? 'Submitted and locked' : 'Draft';
    }

    public function evaluationStatusLabel(): string
    {
        return match ($this->auditorEvaluation->evaluation->status->value) {
            'in_progress' => 'Awaiting remaining Auditor submissions',
            'internal_review' => 'Internal review',
            'ready_for_decision' => 'Ready for Evaluation Decision',
            'completed' => 'Evaluation Decision recorded',
            'withdrawn' => 'Evaluation withdrawn',
            default => 'Evaluation status unavailable',
        };
    }

    /** @return array<string,string> */
    public function assessmentOptions(): array
    {
        return collect(CriterionAssessment::cases())
            ->mapWithKeys(fn (CriterionAssessment $assessment): array => [
                $assessment->value => str($assessment->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    /** @return array<string,string> */
    public function evidenceSufficiencyOptions(): array
    {
        return collect(EvidenceSufficiency::cases())
            ->mapWithKeys(fn (EvidenceSufficiency $value): array => [
                $value->value => str($value->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    /** @return array<string,string> */
    public function audiencePromiseCoherenceOptions(): array
    {
        return collect(AudiencePromiseCoherence::cases())
            ->mapWithKeys(fn (AudiencePromiseCoherence $value): array => [
                $value->value => str($value->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
    }

    /** @return array<string,string> */
    public function evidenceTypeOptions(): array
    {
        return [
            'observation' => 'Observation',
            'document' => 'Document',
            'link' => 'Link',
            'reference' => 'Reference',
        ];
    }

    /** @return array<string,string> */
    public function evidenceProvenanceOptions(): array
    {
        return [
            'observed' => 'Observed evidence',
            'creator_supplied' => 'Creator-supplied evidence',
            'external' => 'External evidence',
            'professional_judgement' => 'Professional judgement',
        ];
    }

    /** @return array<string,string> */
    public function findingTypeOptions(): array
    {
        return [
            'strength' => 'Strength',
            'weakness' => 'Weakness',
            'risk' => 'Risk',
            'recommendation' => 'Recommendation',
            'factual_clarification' => 'Factual clarification',
        ];
    }

    /** @return array<string,string> */
    public function findingSeverityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    public function resultFor(int $criterionId): ?CriterionResult
    {
        return $this->auditorEvaluation->criterionResults->firstWhere('criterion_id', $criterionId);
    }

    /** @return array{min:int,max:int}|null */
    public function scoreRangeFor(int $criterionId): ?array
    {
        $draft = $this->drafts[$criterionId] ?? null;
        if ($draft === null || $draft['assessment'] === '') {
            return null;
        }

        $assessment = CriterionAssessment::tryFrom($draft['assessment']);
        if ($assessment === null) {
            return null;
        }

        return $this->auditorEvaluation->evaluation->standardVersion->scoreAnchorFor($assessment);
    }

    public function assignmentUrl(): string
    {
        return Assignment::getUrl(['assignment' => $this->auditorEvaluation->auditor_assignment_id]);
    }

    private function hydrateDrafts(): void
    {
        foreach (app(AuditorEvaluationWorkspace::class)->criteria($this->auditorEvaluation) as $criterion) {
            $this->hydrateDraft($criterion->getKey());
        }
    }

    private function hydrateDraft(int $criterionId): void
    {
        $result = $this->resultFor($criterionId);
        $assessment = $result === null ? null : CriterionAssessment::tryFrom((string) $result->getRawOriginal('assessment'));
        $assessmentValue = $assessment instanceof CriterionAssessment ? $assessment->value : '';

        $this->drafts[$criterionId] = [
            'assessment' => $assessmentValue,
            'score' => $result === null || $result->score === null ? '' : (string) $result->score,
            'rationale' => $result === null ? '' : $result->rationale,
            'confidence' => $result === null || $result->confidence === null ? '' : (string) $result->confidence,
        ];
    }
}
