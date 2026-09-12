<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\PlatformRole;
use App\Models\AuditorCompetency;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\ExpertPublicProfile;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertPublicProfilePublication;

function publicExpertFixture(array $profileOverrides = []): array
{
    $expert = User::factory()->create([
        'name' => 'Alex Expert',
        'email' => 'alex@example.test',
    ]);

    $profile = AuditorProfile::query()->create(array_merge([
        'auditor_id' => $expert->id,
        'status' => AuditorProfileStatus::Approved,
        'methodology_literate' => true,
        'format_experience' => ['course', 'workshop'],
        'bio' => 'Public Expert biography.',
        'credentials' => 'Public credentials.',
    ], $profileOverrides));

    $membership = ExpertBoardMembership::query()->create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Approved,
        'applied_at' => now()->subDay(),
        'reviewed_at' => now()->subDay(),
        'approved_at' => now()->subDay(),
    ]);

    return [$expert, $profile, $membership];
}

function publishPublicExpert(AuditorProfile $profile): ExpertPublicProfile
{
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    return app(ExpertPublicProfilePublication::class)->publish($profile, $admin);
}

it('publishes only approved Expert profile data and verified structured expertise', function () {
    [, $profile] = publicExpertFixture();

    AuditorCompetency::query()->create([
        'auditor_profile_id' => $profile->id,
        'topic' => 'Instructional Design',
        'experience_type' => 'teaching',
        'years_experience' => 8,
        'evidence' => 'Private competency evidence.',
        'verified_at' => now()->subHour(),
        'verified_by' => User::factory()->create(['platform_role' => PlatformRole::Admin])->id,
    ]);
    AuditorCompetency::query()->create([
        'auditor_profile_id' => $profile->id,
        'topic' => 'Private Internal Topic',
        'experience_type' => 'internal',
        'years_experience' => 2,
        'evidence' => 'Private evidence.',
    ]);

    $publicProfile = publishPublicExpert($profile);

    expect($publicProfile->status)->toBe(ExpertPublicProfileStatus::Published)
        ->and($publicProfile->display_name)->toBe('Alex Expert')
        ->and($publicProfile->expertise_areas)->toBe(['instructional_design'])
        ->and($publicProfile->product_types)->toBe(['course', 'workshop'])
        ->and($publicProfile->credentials)->toBe('Public credentials.');
});

it('allows only platform administrators to publish Expert profiles', function () {
    [, $profile] = publicExpertFixture();
    $nonAdmin = User::factory()->create();

    expect(fn () => app(ExpertPublicProfilePublication::class)->publish($profile, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires an approved Expert Board membership before public publication', function () {
    [, $profile, $membership] = publicExpertFixture();
    $membership->update(['status' => ExpertBoardMembershipStatus::Suspended]);
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    expect(fn () => app(ExpertPublicProfilePublication::class)->publish($profile, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('searches public Experts deterministically by public metadata and taxonomy', function () {
    [, $first] = publicExpertFixture();
    $firstPublic = publishPublicExpert($first);
    $firstPublic->update(['display_name' => 'Beta Expert']);

    [, $second] = publicExpertFixture(['bio' => 'Assessment specialist.']);
    AuditorCompetency::query()->create([
        'auditor_profile_id' => $second->id,
        'topic' => 'Assessment',
        'experience_type' => 'evaluation',
        'years_experience' => 6,
        'evidence' => 'Verified assessment evidence.',
        'verified_at' => now()->subHour(),
        'verified_by' => User::factory()->create(['platform_role' => PlatformRole::Admin])->id,
    ]);
    $secondPublic = publishPublicExpert($second);
    $secondPublic->update(['display_name' => 'Alpha Expert']);

    $response = $this->get(route('public.experts', ['expertise' => 'assessment']));

    $response->assertOk()
        ->assertSee('Alpha Expert')
        ->assertDontSee('Beta Expert');
});

it('does not expose private contact, conflict, evidence or operational data', function () {
    [$expert, $profile] = publicExpertFixture();
    AuditorCompetency::query()->create([
        'auditor_profile_id' => $profile->id,
        'topic' => 'Assessment',
        'experience_type' => 'evaluation',
        'years_experience' => 6,
        'evidence' => 'Secret competency evidence.',
        'verified_at' => now()->subHour(),
        'verified_by' => User::factory()->create(['platform_role' => PlatformRole::Admin])->id,
    ]);

    $publicProfile = publishPublicExpert($profile);
    $publicProfile->load('auditorProfile.expertBoardMembership');

    $response = $this->get(route('public.experts.show', ['slug' => $publicProfile->slug]));

    $response->assertOk()
        ->assertSee('Alex Expert')
        ->assertSee('Public credentials.')
        ->assertDontSee($expert->email)
        ->assertDontSee('Secret competency evidence.')
        ->assertDontSee('decision_reason')
        ->assertDontSee('compensation')
        ->assertDontSee('auditor');
});

it('does not discover suspended or removed public Experts', function () {
    [, $profile] = publicExpertFixture();
    $publicProfile = publishPublicExpert($profile);
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    app(ExpertPublicProfilePublication::class)->suspend($publicProfile, $admin);

    $this->get(route('public.experts'))
        ->assertOk()
        ->assertDontSee('Alex Expert');

    $this->get(route('public.experts.show', ['slug' => $publicProfile->slug]))
        ->assertNotFound();
});

it('does not discover an Expert after current Board membership is suspended', function () {
    [, $profile, $membership] = publicExpertFixture();
    $publicProfile = publishPublicExpert($profile);
    $membership->update(['status' => ExpertBoardMembershipStatus::Suspended]);

    $this->get(route('public.experts'))
        ->assertOk()
        ->assertDontSee($publicProfile->display_name);

    $this->get(route('public.experts.show', ['slug' => $publicProfile->slug]))
        ->assertNotFound();
});
