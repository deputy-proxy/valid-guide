<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evaluations\Pages;

use App\Enums\EvaluationStatus;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use App\Services\ValidationIssuance;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class ViewEvaluation extends ViewRecord
{
    protected static string $resource = EvaluationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Evaluation')
                ->schema([
                    TextEntry::make('id')->label('Evaluation'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (EvaluationStatus $state): string => str($state->value)->replace('_', ' ')->headline()->toString()),
                    TextEntry::make('product.title')->label('Product'),
                    TextEntry::make('productRelease.release_identifier')->label('Product Release'),
                    TextEntry::make('standardVersion.version')->label('Frozen Standard Version'),
                    TextEntry::make('overall_score')->label('Overall score')->placeholder('—'),
                    TextEntry::make('decision')->label('Decision')->placeholder('Pending'),
                    TextEntry::make('decision_rationale')->label('Decision rationale')->columnSpanFull()->placeholder('Pending'),
                ])->columns(2),
            Section::make('Auditor operations')
                ->schema([
                    TextEntry::make('assignments_count')->label('Assignments')->state(fn (Evaluation $record): string => (string) $record->assignments->count()),
                    TextEntry::make('submitted_auditors')->label('Submitted Auditor evaluations')->state(fn (Evaluation $record): string => (string) $record->auditorEvaluations->where('status', 'submitted')->count()),
                    TextEntry::make('ready_state')->label('Readiness')->state(fn (Evaluation $record): string => $record->status === EvaluationStatus::ReadyForDecision ? 'Ready for decision' : 'See decision gate and Auditor status'),
                ])->columns(3),
            Section::make('Trust and publication')
                ->schema([
                    TextEntry::make('validation.status')->label('Validation')->badge()->placeholder('Not issued'),
                    TextEntry::make('validation.verification_identifier')->label('Verification ID')->placeholder('—'),
                    TextEntry::make('validation.publicVerificationRecord.published_at')->label('Public verification published')->dateTime()->placeholder('—'),
                    TextEntry::make('report.currentVersion.version_number')->label('Current report version')->placeholder('—'),
                    TextEntry::make('report.delivered_at')->label('Report delivered')->dateTime()->placeholder('—'),
                ])->columns(2),
            Section::make('Governance requests')
                ->schema([
                    TextEntry::make('clarifications_count')->label('Clarifications')->state(fn (Evaluation $record): string => (string) $record->clarificationRequests->count()),
                    TextEntry::make('open_clarifications')->label('Open clarifications')->state(fn (Evaluation $record): string => (string) $record->clarificationRequests->where('status.value', 'open')->count()),
                    TextEntry::make('disputes_count')->label('Formal disputes')->state(fn (Evaluation $record): string => (string) $record->disputes->count()),
                ])->columns(3),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('decide')
                ->label('Record Evaluation Decision')
                ->icon('heroicon-o-scale')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === EvaluationStatus::ReadyForDecision)
                ->action(function (): void {
                    $this->runAction(function (Evaluation $evaluation, User $user): void {
                        app(EvaluationDecisionService::class)->decide($evaluation, $user);
                    }, 'Evaluation decision recorded', 'Decision blocked');
                }),
            Action::make('issueValidation')
                ->label('Issue Validation')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === EvaluationStatus::Completed && $this->getRecord()->decision === 'validated' && $this->getRecord()->validation === null)
                ->action(function (): void {
                    $this->runAction(function (Evaluation $evaluation, User $user): void {
                        app(ValidationIssuance::class)->issue($evaluation, $user);
                    }, 'Validation issued', 'Validation could not be issued');
                }),
        ];
    }

    /** @param callable(Evaluation, User): void $callback */
    private function runAction(callable $callback, string $successTitle, string $failureTitle): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $callback($this->getRecord(), $user);
            $this->refreshFormData(['status', 'decision', 'overall_score', 'decision_rationale', 'validation']);
            Notification::make()->success()->title($successTitle)->send();
        } catch (DomainStateTransitionException $exception) {
            Notification::make()->danger()->title($failureTitle)->body($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title($failureTitle)->send();
        }
    }
}
