<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Enums\EvaluationStatus;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class RoleActionQueue
{
    /** @return Collection<int, ActionQueueItem> */
    public function for(User $user): Collection
    {
        if ($user->isPlatformAdmin()) {
            return $this->forPlatformAdmin();
        }

        if ($user->auditorProfile()->exists()) {
            return $this->forAuditor($user);
        }

        return $this->forCreator($user);
    }

    /** @return Collection<int, ActionQueueItem> */
    public function forCreator(User $user): Collection
    {
        $organizationIds = $user->organizations()
            ->wherePivotIn('role', ['owner', 'admin', 'editor'])
            ->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            return collect();
        }

        $items = collect();

        EvaluationRequest::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('status', EvaluationRequestStatus::AwaitingCreator->value)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(function (EvaluationRequest $request) use ($items): void {
                $items->push(new ActionQueueItem(
                    'evaluation-request:'.$request->getKey(),
                    'creator',
                    'Creator action required',
                    'Additional creator information is required before the evaluation can proceed.',
                    'evaluation_request',
                    (int) $request->getKey(),
                    false,
                    CarbonImmutable::instance($request->updated_at),
                ));
            });

        Report::query()
            ->whereHas('evaluation.request', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->whereNotNull('delivered_at')
            ->whereNull('creator_visible_at')
            ->orderByDesc('delivered_at')
            ->limit(25)
            ->get()
            ->each(function (Report $report) use ($items): void {
                $items->push(new ActionQueueItem(
                    'report:'.$report->getKey(),
                    'report',
                    'Report available',
                    'An evaluation report is ready for review.',
                    'report',
                    (int) $report->getKey(),
                    false,
                    CarbonImmutable::parse($report->delivered_at ?? $report->updated_at ?? now()),
                ));
            });

        ClarificationRequest::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('status', 'answered')
            ->whereNull('resolved_at')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(function (ClarificationRequest $request) use ($items): void {
                $items->push(new ActionQueueItem(
                    'clarification:'.$request->getKey(),
                    'clarification',
                    'Clarification response received',
                    'A response is available for your clarification request.',
                    'clarification',
                    (int) $request->getKey(),
                    false,
                    CarbonImmutable::instance($request->updated_at),
                ));
            });

        Dispute::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('status', 'resolved')
            ->whereNull('resolved_at')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(function (Dispute $dispute) use ($items): void {
                $items->push(new ActionQueueItem(
                    'dispute:'.$dispute->getKey(),
                    'dispute',
                    'Dispute resolution available',
                    'Your formal dispute has a recorded resolution.',
                    'dispute',
                    (int) $dispute->getKey(),
                    false,
                    CarbonImmutable::instance($dispute->updated_at),
                ));
            });

        return $items->sortByDesc(fn (ActionQueueItem $item): CarbonImmutable => $item->createdAt)->values();
    }

    /** @return Collection<int, ActionQueueItem> */
    public function forAuditor(User $user): Collection
    {
        $items = collect();

        AuditorAssignment::query()
            ->where('auditor_id', $user->getKey())
            ->where('status', 'offered')
            ->with('evaluation.product')
            ->orderByDesc('assigned_at')
            ->limit(25)
            ->get()
            ->each(function (AuditorAssignment $assignment) use ($items): void {
                $product = $assignment->evaluation->product;
                $title = $product->title;
                $items->push(new ActionQueueItem(
                    'assignment:'.$assignment->getKey(),
                    'assignment',
                    'Evaluation assignment',
                    sprintf('Accept or decline your assignment for %s.', $title),
                    'auditor_assignment',
                    (int) $assignment->getKey(),
                    false,
                    CarbonImmutable::parse($assignment->assigned_at ?? $assignment->created_at ?? now()),
                ));
            });

        AuditorEvaluation::query()
            ->whereHas('assignment', fn ($query) => $query->where('auditor_id', $user->getKey())->where('status', 'accepted'))
            ->where('status', 'draft')
            ->with('evaluation.product')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(function (AuditorEvaluation $evaluation) use ($items): void {
                $product = $evaluation->evaluation->product;
                $title = $product->title;
                $items->push(new ActionQueueItem(
                    'auditor-evaluation:'.$evaluation->getKey(),
                    'submission',
                    'Evaluation work pending',
                    sprintf('Complete and submit your independent evaluation of %s.', $title),
                    'auditor_evaluation',
                    (int) $evaluation->getKey(),
                    false,
                    CarbonImmutable::instance($evaluation->updated_at),
                ));
            });

        return $items->sortByDesc(fn (ActionQueueItem $item): CarbonImmutable => $item->createdAt)->values();
    }

    /** @return Collection<int, ActionQueueItem> */
    public function forPlatformAdmin(): Collection
    {
        $items = collect();

        Evaluation::query()
            ->where('status', EvaluationStatus::ReadyForDecision->value)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(fn (Evaluation $evaluation) => $items->push(new ActionQueueItem(
                'evaluation-decision:'.$evaluation->getKey(),
                'decision',
                'Evaluation decision required',
                'A completed evaluation is ready for an authorized decision.',
                'evaluation',
                (int) $evaluation->getKey(),
                false,
                CarbonImmutable::instance($evaluation->updated_at),
            )));

        AuditorEvaluation::query()
            ->where('status', 'submitted')
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get()
            ->each(fn (AuditorEvaluation $evaluation) => $items->push(new ActionQueueItem(
                'auditor-review:'.$evaluation->getKey(),
                'submission',
                'Auditor submission to review',
                'An Auditor evaluation has been submitted and requires review.',
                'auditor_evaluation',
                (int) $evaluation->getKey(),
                false,
                CarbonImmutable::parse($evaluation->submitted_at ?? $evaluation->updated_at ?? now()),
            )));

        ClarificationRequest::query()
            ->where('status', 'open')
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get()
            ->each(fn (ClarificationRequest $request) => $items->push(new ActionQueueItem(
                'clarification:'.$request->getKey(),
                'clarification',
                'Clarification requires review',
                'A creator has requested clarification on an evaluation.',
                'clarification',
                (int) $request->getKey(),
                false,
                CarbonImmutable::parse($request->submitted_at ?? $request->updated_at ?? now()),
            )));

        Dispute::query()
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get()
            ->each(fn (Dispute $dispute) => $items->push(new ActionQueueItem(
                'dispute:'.$dispute->getKey(),
                'dispute',
                'Formal dispute requires action',
                'A formal dispute is awaiting independent review or resolution.',
                'dispute',
                (int) $dispute->getKey(),
                false,
                CarbonImmutable::parse($dispute->submitted_at ?? $dispute->updated_at ?? now()),
            )));

        Report::query()
            ->whereNull('delivered_at')
            ->whereNotNull('current_version_id')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->each(fn (Report $report) => $items->push(new ActionQueueItem(
                'report-delivery:'.$report->getKey(),
                'report',
                'Report delivery required',
                'A report is ready to be delivered to its creator.',
                'report',
                (int) $report->getKey(),
                false,
                CarbonImmutable::instance($report->updated_at),
            )));

        return $items->sortByDesc(fn (ActionQueueItem $item): CarbonImmutable => $item->createdAt)->values();
    }
}
