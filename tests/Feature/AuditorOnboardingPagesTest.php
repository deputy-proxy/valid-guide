<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Models\AuditorProfile;
use App\Models\User;

it('renders the auditor profile page for an auditor', function () {
    $auditor = User::factory()->create();
    AuditorProfile::create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Pending,
        'methodology_literate' => false,
        'format_experience' => ['course'],
        'bio' => 'Experienced evaluator.',
        'credentials' => 'Relevant credentials.',
    ]);

    $this->actingAs($auditor)
        ->get('/auditor/profile')
        ->assertSuccessful()
        ->assertSee('My Auditor Profile')
        ->assertSee('Experienced evaluator.')
        ->assertSee('Save profile');
});

it('renders the annual conflict declaration page for an auditor', function () {
    $auditor = User::factory()->create();
    AuditorProfile::create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Pending,
    ]);

    $this->actingAs($auditor)
        ->get('/auditor/annual-conflict-declaration')
        ->assertSuccessful()
        ->assertSee('Annual Conflict-of-Interest Declaration')
        ->assertSee('Submit declaration')
        ->assertSee((string) now()->year);
});
