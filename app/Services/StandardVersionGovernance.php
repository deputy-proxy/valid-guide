<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlatformRole;
use App\Enums\StandardVersionStatus;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StandardVersionGovernance
{
    private const TRANSITIONS = [
        'draft' => ['scheduled'],
        'scheduled' => ['effective'],
        'effective' => ['retired'],
        'retired' => [],
    ];

    public function transition(StandardVersion $version, StandardVersionStatus $to, User $actor): StandardVersion
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($version, $to, $actor): StandardVersion {
            $version = StandardVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $from = $version->status instanceof StandardVersionStatus
                ? $version->status->value
                : (string) $version->status;

            if (! in_array($to->value, self::TRANSITIONS[$from] ?? [], true)) {
                throw new DomainStateTransitionException(
                    "Invalid standard version transition from {$from} to {$to->value}.",
                );
            }

            if ($to === StandardVersionStatus::Scheduled) {
                app(MethodologyRuleValidator::class)->validateStandardVersion($version);

                if ($version->effective_at === null || $version->effective_at->lte(now())) {
                    throw new DomainStateTransitionException(
                        'A standard version must have a future effective date before it can be scheduled.',
                    );
                }
            }

            $before = [
                'status' => $from,
                'effective_at' => $version->effective_at?->toISOString(),
                'retired_at' => $version->retired_at?->toISOString(),
                'approved_by' => $version->approved_by,
                'approved_at' => $version->approved_at?->toISOString(),
            ];

            if ($to === StandardVersionStatus::Scheduled) {
                $version->approved_by = $actor->getKey();
                $version->approved_at = now();
            }

            if ($to === StandardVersionStatus::Effective) {
                if ($version->effective_at === null || $version->effective_at->gt(now())) {
                    throw new DomainStateTransitionException(
                        'A standard version cannot become effective before its effective date.',
                    );
                }

                $existingEffective = StandardVersion::query()
                    ->where('evaluation_standard_id', $version->evaluation_standard_id)
                    ->where('status', StandardVersionStatus::Effective->value)
                    ->whereKeyNot($version->getKey())
                    ->exists();

                if ($existingEffective) {
                    throw new DomainStateTransitionException(
                        'A standard cannot have more than one effective version at the same time.',
                    );
                }
            }

            if ($to === StandardVersionStatus::Retired) {
                $version->retired_at = now();
            }

            $version->status = $to;
            $version->save();

            AuditLogger::record(
                event: 'standard_version.status_changed',
                auditable: $version,
                before: $before,
                after: [
                    'status' => $to->value,
                    'effective_at' => $version->effective_at?->toISOString(),
                    'retired_at' => $version->retired_at?->toISOString(),
                    'approved_by' => $version->approved_by,
                    'approved_at' => $version->approved_at?->toISOString(),
                ],
                metadata: ['actor_id' => $actor->getKey()],
            );

            return $version->refresh();
        });
    }

    public function schedule(StandardVersion $version, User $actor): StandardVersion
    {
        return $this->transition($version, StandardVersionStatus::Scheduled, $actor);
    }

    public function makeEffective(StandardVersion $version, User $actor): StandardVersion
    {
        return $this->transition($version, StandardVersionStatus::Effective, $actor);
    }

    public function retire(StandardVersion $version, User $actor): StandardVersion
    {
        return $this->transition($version, StandardVersionStatus::Retired, $actor);
    }

    private function authorize(User $actor): void
    {
        if ($actor->platform_role !== PlatformRole::Admin) {
            throw new DomainStateTransitionException(
                'Only a platform administrator may govern evaluation standard versions.',
            );
        }
    }
}
