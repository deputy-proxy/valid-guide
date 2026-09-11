<?php

declare(strict_types=1);

use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\AuditorConflictDeclarationService;
use App\Services\ConflictDeclarationDecision;
use App\Services\DomainStateTransitionException;

it('allows an auditor to submit a declaration for their own assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;

    $declaration = app(AuditorConflictDeclarationService::class)->submit(
        $assignment,
        $auditor,
        'I have no financial relationship with this product.',
    );

    expect($declaration->auditor_assignment_id)->toBe($assignment->id)
        ->and($declaration->evaluation_id)->toBe($assignment->evaluation_id)
        ->and($declaration->declaration_type)->toBe('assignment')
        ->and($declaration->disclosure)->toBe('I have no financial relationship with this product.');
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
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $auditor = $assignment->auditor;
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $declaration = app(AuditorConflictDeclarationService::class)->submit($assignment, $auditor, 'Original disclosure.');

    app(ConflictDeclarationDecision::class)->decide($declaration, 'cleared', $admin);

    expect(fn () => app(AuditorConflictDeclarationService::class)->submit($assignment, $auditor, 'Changed disclosure.'))
        ->toThrow(DomainStateTransitionException::class)
        ->and(ConflictDeclaration::findOrFail($declaration->id)->disclosure)->toBe('Original disclosure.');
});
