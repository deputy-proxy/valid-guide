<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Models\AuditorProfile;
use App\Models\User;
use App\Services\AuditorProfileManagement;
use App\Services\DomainStateTransitionException;

function auditorProfileManagementProfile(User $auditor): AuditorProfile
{
    return AuditorProfile::create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Pending,
        'methodology_literate' => false,
        'format_experience' => ['course'],
        'bio' => 'Original professional bio.',
        'credentials' => 'Original credentials.',
    ]);
}

it('allows an auditor to update only their own profile information', function () {
    $auditor = User::factory()->create();
    $profile = auditorProfileManagementProfile($auditor);

    $updated = app(AuditorProfileManagement::class)->update(
        $auditor,
        'Updated professional bio.',
        'Updated credentials.',
        ['course', 'workshop'],
        true,
    );

    expect($updated->id)->toBe($profile->id)
        ->and($updated->bio)->toBe('Updated professional bio.')
        ->and($updated->credentials)->toBe('Updated credentials.')
        ->and($updated->format_experience)->toBe(['course', 'workshop'])
        ->and($updated->methodology_literate)->toBeTrue()
        ->and($updated->status)->toBe(AuditorProfileStatus::Pending);
});

it('rejects profile updates when the auditor has no profile', function () {
    $auditor = User::factory()->create();

    expect(fn () => app(AuditorProfileManagement::class)->update(
        $auditor,
        'Bio',
        'Credentials',
        ['course'],
        true,
    ))->toThrow(DomainStateTransitionException::class);
});

it('does not allow blank required profile information', function () {
    $auditor = User::factory()->create();
    auditorProfileManagementProfile($auditor);

    expect(fn () => app(AuditorProfileManagement::class)->update(
        $auditor,
        '   ',
        'Credentials',
        ['course'],
        true,
    ))->toThrow(DomainStateTransitionException::class);

    expect(fn () => app(AuditorProfileManagement::class)->update(
        $auditor,
        'Bio',
        '   ',
        ['course'],
        true,
    ))->toThrow(DomainStateTransitionException::class);
});
