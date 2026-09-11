<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AuditorProfileManagement
{
    /**
     * @param  list<string>  $formatExperience
     */
    public function update(
        User $auditor,
        string $bio,
        string $credentials,
        array $formatExperience,
        bool $methodologyLiterate,
    ): AuditorProfile {
        $profile = $auditor->auditorProfile;

        if ($profile === null) {
            throw new DomainStateTransitionException('An Auditor profile is required before it can be updated.');
        }

        $bio = trim($bio);
        $credentials = trim($credentials);
        $formatExperience = array_values(array_filter(
            array_map(static fn (string $value): string => trim($value), $formatExperience),
            static fn (string $value): bool => $value !== '',
        ));

        if ($bio === '' || $credentials === '') {
            throw new DomainStateTransitionException('Auditor bio and credentials are required.');
        }

        return DB::transaction(function () use ($profile, $bio, $credentials, $formatExperience, $methodologyLiterate): AuditorProfile {
            $profile = AuditorProfile::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $profile->forceFill([
                'bio' => $bio,
                'credentials' => $credentials,
                'format_experience' => $formatExperience,
                'methodology_literate' => $methodologyLiterate,
            ])->save();

            return $profile->refresh();
        });
    }
}
