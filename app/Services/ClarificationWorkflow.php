<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClarificationRequestStatus;
use App\Enums\ClarificationRequestType;
use App\Enums\EvaluationStatus;
use App\Models\ClarificationRequest;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClarificationWorkflow
{
    public function __construct(private readonly WorkflowNotificationService $notifications) {}

    public function submit(
        Evaluation $evaluation,
        Organization $organization,
        User $submittedBy,
        ClarificationRequestType $type,
        string $message,
    ): ClarificationRequest {
        if (! $this->creatorCanAct($organization, $submittedBy)) {
            throw new DomainStateTransitionException('The user cannot submit clarification requests for this organization.');
        }

        if ($evaluation->request->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The clarification organization does not own the evaluation.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new DomainStateTransitionException('Clarifications can only be submitted after an evaluation is completed.');
        }

        if (trim($message) === '') {
            throw new DomainStateTransitionException('A clarification request requires a message.');
        }

        $request = DB::transaction(function () use ($evaluation, $organization, $submittedBy, $type, $message): ClarificationRequest {
            $request = ClarificationRequest::query()->create([
                'evaluation_id' => $evaluation->id,
                'organization_id' => $organization->id,
                'submitted_by' => $submittedBy->id,
                'type' => $type,
                'message' => trim($message),
                'status' => ClarificationRequestStatus::Open,
                'submitted_at' => now(),
            ]);

            AuditLogger::record(
                event: 'clarification.submitted',
                auditable: $request,
                after: ['evaluation_id' => $evaluation->id, 'submitted_by' => $submittedBy->id],
            );

            return $request->refresh();
        });

        $this->notifications->clarificationSubmitted($request);

        return $request;
    }

    public function answer(ClarificationRequest $request, User $answeredBy, string $response): ClarificationRequest
    {
        if (! $answeredBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can answer clarification requests.');
        }

        if ($request->status->value !== ClarificationRequestStatus::Open->value) {
            throw new DomainStateTransitionException('Only open clarification requests can be answered.');
        }

        if (trim($response) === '') {
            throw new DomainStateTransitionException('A clarification answer requires a response.');
        }

        $request = DB::transaction(function () use ($request, $answeredBy, $response): ClarificationRequest {
            $request = ClarificationRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== ClarificationRequestStatus::Open) {
                throw new DomainStateTransitionException('Only open clarification requests can be answered.');
            }

            $request->response = trim($response);
            $request->status = ClarificationRequestStatus::Answered;
            $request->save();

            AuditLogger::record(
                event: 'clarification.answered',
                auditable: $request,
                after: ['resolved_by' => $answeredBy->id],
            );

            return $request->refresh();
        });

        $this->notifications->clarificationAnswered($request);

        return $request;
    }

    public function close(ClarificationRequest $request, User $closedBy): ClarificationRequest
    {
        if (! $closedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can close clarification requests.');
        }

        if ($request->status->value !== ClarificationRequestStatus::Answered->value) {
            throw new DomainStateTransitionException('Only answered clarification requests can be closed.');
        }

        return DB::transaction(function () use ($request, $closedBy): ClarificationRequest {
            $request = ClarificationRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== ClarificationRequestStatus::Answered) {
                throw new DomainStateTransitionException('Only answered clarification requests can be closed.');
            }

            $request->status = ClarificationRequestStatus::Closed;
            $request->resolved_at = now();
            $request->resolved_by = $closedBy->id;
            $request->save();

            AuditLogger::record(
                event: 'clarification.closed',
                auditable: $request,
                after: ['resolved_by' => $closedBy->id],
            );

            return $request->refresh();
        });
    }

    private function creatorCanAct(Organization $organization, User $user): bool
    {
        return $organization->users()
            ->whereKey($user->id)
            ->wherePivotIn('role', ['owner', 'admin', 'editor'])
            ->exists();
    }
}
