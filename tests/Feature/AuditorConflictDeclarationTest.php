<?php

declare(strict_types=1);

use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\AuditorConflictDeclarationService;
use App\Services\ConflictDeclarationDecision;
use App\Services\DomainStateTransitionException;
use Illuminate\Support\Facades\DB;

it('allows an auditor to submit a declaration for their own assignment', function () {
    [$auditorEvaluation, , $declaration] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;

    DB::table('conflict_declarations')->where('id', $declaration->id)->update([
        'outcome' => null,
        'determined_by' => null,
        'determined_at' => null,
    ]);

    $updated = app(AuditorConflictDeclarationService::class)->submit(
        $assignment,
        $auditor,
        'I have no financial relationship with this product.',
    );

    expect($updated->auditor_assignment_id)->toBe($assignment->id)
        ->and($updated->evaluation_id)->toBe($assignment->evaluation_id)
        ->and($updated->declaration_type)->toBe('assignment')
        ->and($updated->disclosure)->toBe('I have no financial relationship with this product.');
});

it('rejects declarations for another auditors assignment', function () {
    [$firstEvaluation] = auditorEvaluationFixture();
    [$secondEvaluation] = auditorEvaluationFixture();

    expect(fn () => app(AuditorConflictDeclarationService::class)->submit(
        $secondEvaluation->assignment,
        $firstEvaluation->assignment->auditor,
        'I have no conflict.',
    ))->toThrow(DomainStateTransitionException::class);
});

it('rejects blank assignment disclosures', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();

    expect(fn () => app(AuditorConflictDeclarationService::class)->submit(
        $auditorEvaluation->assignment,
        $auditorEvaluation->assignment->auditor,
        '   ',
    ))->toThrow(DomainStateTransitionException::class);
});

it('does not replace a determined assignment declaration', function () {
    [$auditorEvaluation, , $declaration] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;
    $admin = User::factory()->create(['platform_role' => 'admin']);

    DB::table('conflict_declarations')->where('id', $declaration->id)->update([
        'outcome' => null,
        'determined_by' => null,
        'determined_at' => null,
    ]);

    $updated = app(AuditorConflictDeclarationService::class)->submit($assignment, $auditor, 'Original disclosure.');

    app(ConflictDeclarationDecision::class)->decide($updated, 'cleared', $admin);

    expect(fn () => app(AuditorConflictDeclarationService::class)->submit($assignment, $auditor, 'Changed disclosure.'))
        ->toThrow(DomainStateTransitionException::class)
        ->and(ConflictDeclaration::findOrFail($updated->id)->disclosure)->toBe('Original disclosure.');
});
