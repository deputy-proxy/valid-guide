<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Filament\Auditor\Pages\Assignment;
use App\Filament\Auditor\Pages\Assignments;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\User;
use App\Services\AuditorAssignmentAccess;
use Illuminate\Auth\Access\AuthorizationException;

function clearAuditor(User $user): void
{
    AuditorProfile::query()->updateOrCreate(
        ['auditor_id' => $user->id],
        ['status' => AuditorProfileStatus::Approved],
    );

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $user->id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);
}

it('returns only cleared assignments belonging to the authenticated auditor', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;
    clearAuditor($auditor);

    [$otherAuditorEvaluation] = auditorEvaluationFixture();
    clearAuditor($otherAuditorEvaluation->assignment->auditor);

    $assignments = app(AuditorAssignmentAccess::class)->queryFor($auditor)->get();

    expect($assignments)->toHaveCount(1)
        ->and($assignments->first()->id)->toBe($assignment->id);
});

it('hides assignments when annual auditor clearance is missing', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    expect(app(AuditorAssignmentAccess::class)->queryFor($auditor)->count())->toBe(0);
});

it('rejects direct access to another auditors assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    [$otherAuditorEvaluation] = auditorEvaluationFixture();
    clearAuditor($otherAuditorEvaluation->assignment->auditor);

    expect(fn () => app(AuditorAssignmentAccess::class)->findFor($auditor, $otherAuditorEvaluation->assignment->id))
        ->toThrow(AuthorizationException::class);
});

it('renders only the authenticated auditors cleared assignments', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;
    clearAuditor($auditor);

    $otherUser = User::factory()->create();
    $otherAssignment = $auditorEvaluation->evaluation->assignments()->create([
        'auditor_id' => $otherUser->id,
        'sequence' => 2,
        'status' => 'accepted',
        'assigned_at' => now(),
        'accepted_at' => now(),
    ]);
    $otherAssignment->conflictDeclarations()->create([
        'evaluation_id' => $auditorEvaluation->evaluation_id,
        'declaration_type' => 'assignment',
        'disclosure' => 'No known conflict.',
        'outcome' => 'cleared',
        'determined_by' => $auditor->id,
        'determined_at' => now(),
    ]);

    $this->actingAs($auditor)
        ->get(Assignments::getUrl())
        ->assertSuccessful()
        ->assertSee($assignment->evaluation->product->title)
        ->assertSee('View assignment');

    $this->actingAs($auditor)
        ->get(Assignment::getUrl(['assignment' => $otherAssignment->id]))
        ->assertForbidden();
});
