<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionVotingMode;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\CriterionVote;
use App\Models\Evaluation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CriterionVoting
{
    /** @return Collection<int, CriterionVote> */
    public function record(AuditorEvaluation $auditorEvaluation): Collection
    {
        return DB::transaction(function () use ($auditorEvaluation): Collection {
            $auditorEvaluation = AuditorEvaluation::query()
                ->whereKey($auditorEvaluation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($auditorEvaluation->locked_at === null || $auditorEvaluation->status !== 'submitted') {
                throw new DomainStateTransitionException('Only submitted auditor evaluations can be converted into criterion votes.');
            }

            $assignment = $auditorEvaluation->assignment()->lockForUpdate()->firstOrFail();

            if ($assignment->status !== 'accepted' && $assignment->status !== 'completed') {
                throw new DomainStateTransitionException('The auditor assignment must be accepted before criterion votes can be recorded.');
            }

            $votes = collect();

            foreach ($auditorEvaluation->criterionResults()->get() as $result) {
                $criterion = Criterion::query()->findOrFail($result->criterion_id);

                if ($criterion->voting_mode !== CriterionVotingMode::Majority) {

                    continue;
                }

                $existing = CriterionVote::query()
                    ->where('evaluation_id', $auditorEvaluation->evaluation_id)
                    ->where('criterion_id', $result->criterion_id)
                    ->where('auditor_id', $assignment->auditor_id)
                    ->first();

                if ($existing !== null) {
                    $votes->push($existing);

                    continue;
                }

                $votes->push(CriterionVote::query()->create([
                    'evaluation_id' => $auditorEvaluation->evaluation_id,
                    'criterion_id' => $result->criterion_id,
                    'criterion_result_id' => $result->id,
                    'auditor_id' => $assignment->auditor_id,
                    'decision' => $result->assessment,
                ]));
            }

            return $votes;
        });
    }

    /** @return array{decision: string, counts: array<string, int>, voter_count: int} */
    public function aggregate(Evaluation $evaluation, int $criterionId): array
    {
        $criterion = Criterion::query()->findOrFail($criterionId);

        if ($criterion->standard_version_id !== $evaluation->standard_version_id) {
            throw new DomainStateTransitionException('A criterion can only be aggregated within the evaluation standard version.');
        }

        if ($criterion->voting_mode !== CriterionVotingMode::Majority) {
            throw new DomainStateTransitionException('Only methodology-designated collective criteria can be aggregated by majority vote.');
        }

        $votes = CriterionVote::query()
            ->where('evaluation_id', $evaluation->getKey())
            ->where('criterion_id', $criterionId)
            ->get();

        $voterCount = $votes->unique('auditor_id')->count();

        if ($voterCount === 0) {
            throw new DomainStateTransitionException('A criterion cannot be aggregated without auditor votes.');
        }

        if ($voterCount % 2 === 0) {
            throw new DomainStateTransitionException('Criterion voting requires an odd number of auditors.');
        }

        $counts = [];
        foreach ($votes as $vote) {
            $decision = $vote->decision->value;
            $counts[$decision] = ($counts[$decision] ?? 0) + 1;
        }
        arsort($counts);
        $winner = array_key_first($counts);
        $winnerCount = $winner !== null ? $counts[$winner] : 0;

        if ($winner === null || $winnerCount <= intdiv($voterCount, 2)) {
            throw new DomainStateTransitionException('No criterion decision has a simple majority.');
        }

        return [
            'decision' => $winner,
            'counts' => $counts,
            'voter_count' => $voterCount,
        ];
    }
}
