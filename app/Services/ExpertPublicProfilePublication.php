<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertiseArea;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\ProductType;
use App\Models\AuditorProfile;
use App\Models\ExpertPublicProfile;
use App\Models\User;
use Illuminate\Support\Str;

final class ExpertPublicProfilePublication
{
    public function publish(AuditorProfile $profile, User $actor): ExpertPublicProfile
    {
        $this->authorize($actor);

        $profile = AuditorProfile::query()
            ->with(['auditor', 'competencies', 'expertBoardMembership'])
            ->where('id', $profile->getKey())
            ->firstOrFail();

        if ($profile->status !== AuditorProfileStatus::Approved) {
            throw new DomainStateTransitionException('Only approved Auditor profiles can be published as Experts.');
        }

        if ($profile->expertBoardMembership?->status !== ExpertBoardMembershipStatus::Approved) {
            throw new DomainStateTransitionException('Only approved Expert Board members can be published as Experts.');
        }

        $publicProfile = ExpertPublicProfile::query()->firstOrNew([
            'auditor_profile_id' => $profile->getKey(),
        ]);

        if ($publicProfile->status === ExpertPublicProfileStatus::Removed) {
            throw new DomainStateTransitionException('A removed public Expert profile cannot be republished.');
        }

        $publicProfile->forceFill([
            'slug' => $publicProfile->slug ?? $this->slug($profile),
            'display_name' => $profile->auditor->name,
            'bio' => $profile->bio,
            'credentials' => $profile->credentials,
            'expertise_areas' => $this->expertiseAreas($profile),
            'product_types' => $this->productTypes($profile),
            'status' => ExpertPublicProfileStatus::Published,
            'published_at' => $publicProfile->published_at ?? now(),
            'suspended_at' => null,
            'removed_at' => null,
        ])->save();

        AuditLogger::record(
            event: 'expert_public_profile.published',
            auditable: $publicProfile,
            after: [
                'auditor_profile_id' => $profile->getKey(),
                'status' => ExpertPublicProfileStatus::Published->value,
            ],
            actor: $actor,
        );

        return $publicProfile->refresh();
    }

    public function suspend(ExpertPublicProfile $publicProfile, User $actor): ExpertPublicProfile
    {
        $this->authorize($actor);

        $publicProfile = ExpertPublicProfile::query()->whereKey($publicProfile->getKey())->firstOrFail();

        if ($publicProfile->status !== ExpertPublicProfileStatus::Published) {
            throw new DomainStateTransitionException('Only published public Expert profiles can be suspended.');
        }

        $publicProfile->forceFill([
            'status' => ExpertPublicProfileStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'expert_public_profile.suspended',
            auditable: $publicProfile,
            after: ['status' => ExpertPublicProfileStatus::Suspended->value],
            actor: $actor,
        );

        return $publicProfile->refresh();
    }

    public function remove(ExpertPublicProfile $publicProfile, User $actor): ExpertPublicProfile
    {
        $this->authorize($actor);

        $publicProfile = ExpertPublicProfile::query()->whereKey($publicProfile->getKey())->firstOrFail();

        if (in_array($publicProfile->status, [ExpertPublicProfileStatus::Removed, ExpertPublicProfileStatus::Draft], true)) {
            throw new DomainStateTransitionException('Only published or suspended public Expert profiles can be removed.');
        }

        $publicProfile->forceFill([
            'status' => ExpertPublicProfileStatus::Removed,
            'removed_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'expert_public_profile.removed',
            auditable: $publicProfile,
            after: ['status' => ExpertPublicProfileStatus::Removed->value],
            actor: $actor,
        );

        return $publicProfile->refresh();
    }

    /** @return list<string> */
    private function expertiseAreas(AuditorProfile $profile): array
    {
        $areas = [];

        foreach ($profile->competencies->whereNotNull('verified_at') as $competency) {
            $area = ExpertiseArea::tryFrom(Str::snake((string) $competency->topic));

            if ($area !== null) {
                $areas[] = $area->value;
            }
        }

        $areas = array_values(array_unique($areas));
        sort($areas);

        return $areas;
    }

    /** @return list<string> */
    private function productTypes(AuditorProfile $profile): array
    {
        $types = [];

        foreach ($profile->format_experience ?? [] as $format) {
            $type = ProductType::tryFrom((string) $format);

            if ($type !== null) {
                $types[] = $type->value;
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }

    private function slug(AuditorProfile $profile): string
    {
        return Str::slug($profile->auditor->name).'-'.$profile->getKey();
    }

    private function authorize(User $actor): void
    {
        if ($actor->isPlatformAdmin() === false) {
            throw new DomainStateTransitionException('Only platform administrators can govern public Expert profiles.');
        }
    }
}
