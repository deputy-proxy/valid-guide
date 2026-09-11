<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Enums\CriterionAssessment;
use App\Models\AuditorEvaluation;
use App\Models\CriterionResult;
use App\Models\User;
use App\Services\AuditorEvaluationSubmission;
use App\Services\AuditorEvaluationWorkspace;
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

    public string $saveState = 'saved';

    public function mount(string $assignment): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this evaluation.');
        }

        $this->auditorEvaluation = app(AuditorEvaluationWorkspace::class)->findFor($user, $assignment);
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

        try {
            app(AuditorEvaluationSubmission::class)->submit($this->auditorEvaluation);
            $this->auditorEvaluation->refresh();
            $this->hydrateDrafts();

            Notification::make()
                ->title('Evaluation submitted')
                ->success()
                ->send();
        } catch (DomainStateTransitionException $exception) {
            $this->saveState = 'failed';

            Notification::make()
                ->title('Evaluation could not be submitted')
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

    /** @return array<string,string> */
    public function assessmentOptions(): array
    {
        return collect(CriterionAssessment::cases())
            ->mapWithKeys(fn (CriterionAssessment $assessment): array => [
                $assessment->value => str($assessment->value)->replace('_', ' ')->headline()->toString(),
            ])
            ->all();
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

        $this->drafts[$criterionId] = [
            'assessment' => $assessment?->value ?? '',
            'score' => $result === null || $result->score === null ? '' : (string) $result->score,
            'rationale' => $result === null ? '' : $result->rationale,
            'confidence' => $result === null || $result->confidence === null ? '' : (string) $result->confidence,
        ];
    }
}
