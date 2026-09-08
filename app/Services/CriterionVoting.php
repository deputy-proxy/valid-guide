<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorEvaluation;
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

        $counts = $votes->groupBy('decision')->map->count()->sortDesc();
        $winner = $counts->keys()->first();
        $winnerCount = $counts->first();

        if ($winnerCount <= intdiv($voterCount, 2)) {
            throw new DomainStateTransitionException('No criterion decision has a simple majority.');
        }

        return [
            'decision' => $winner,
            'counts' => $counts->all(),
            'voter_count' => $voterCount,
        ];
    }
}
