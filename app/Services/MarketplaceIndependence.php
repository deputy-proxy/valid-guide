<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorProfile;
use App\Models\Evaluation;
use App\Models\MarketplaceTransaction;
use App\Models\User;

final class MarketplaceIndependence
{
    public function assertAuditorClear(Evaluation $evaluation, User $auditor, User $determinedBy): void
    {
        $organizationId = $evaluation->product()->value('organization_id');

        if ($organizationId === null) {
            return;
        }

        $profileId = AuditorProfile::query()
            ->where('auditor_id', $auditor->getKey())
            ->value('id');

        if ($profileId === null) {
            return;
        }

        $hasCommercialRelationship = MarketplaceTransaction::query()
            ->where('auditor_profile_id', (int) $profileId)
            ->where('organization_id', (int) $organizationId)
            ->exists();

        if (! $hasCommercialRelationship) {
            return;
        }

        throw new MarketplaceCommercialConflictException($evaluation, $auditor, $determinedBy);
    }
}
