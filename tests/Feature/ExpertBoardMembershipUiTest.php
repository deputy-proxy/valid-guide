<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\User;

function expertBoardUiMembership(): ExpertBoardMembership
{
    $auditor = User::factory()->create();
    $profile = AuditorProfile::create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Approved,
        'methodology_literate' => true,
        'format_experience' => ['course'],
        'bio' => 'Experienced evaluator.',
        'credentials' => 'Relevant experience.',
        'approved_at' => now(),
    ]);

    return ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Pending,
        'applied_at' => now(),
    ]);
}

it('allows platform admins to access Expert Board administration', function () {
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    expertBoardUiMembership();

    $this->actingAs($admin)
        ->get('/admin/expert-board-memberships')
        ->assertSuccessful()
        ->assertSee('Expert Board')
        ->assertSee('Pending');
});

it('denies Expert Board administration to non-platform users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/expert-board-memberships')
        ->assertForbidden();
});
