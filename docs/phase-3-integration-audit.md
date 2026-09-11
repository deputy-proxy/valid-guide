# Phase 3 Integration and Regression Audit

Issue: #100
Status: completion gate

## Scope

This audit closes the specification-to-code gap for Phase 3. The eight implementation issues preceding #100 provide the role-specific application surfaces; this document records the regression matrix that must remain true while those surfaces evolve.

## Regression matrix

| Phase 3 area | Primary regression coverage | Required boundary |
| --- | --- | --- |
| Auditor workspace and assignments | `AuditorWorkspaceTest.php`, `AuditorEvaluationWorkspaceTest.php` | Assignment, tenancy and authorization |
| Auditor eligibility and COI | `AuditorEligibilityTest.php`, `AuditorAnnualConflictDeclarationTest.php`, `AuditorConflictDeclarationTest.php` | Eligibility and conflict gates |
| Assessment, evidence and findings | `AuditorEvidenceAndFindingTest.php`, `CriterionApplicabilityTest.php`, `CriterionVotingTest.php` | Frozen methodology and private Auditor work |
| Auditor submission | `AuditorEvaluationSubmissionTest.php`, `AuditorEvaluationFinalizationTest.php` | Submission boundary, concurrency and immutability |
| Platform governance | `PlatformGovernanceInterfaceTest.php`, `EvaluationDecisionServiceTest.php`, `ValidationIssuanceTest.php`, `ValidationStateTransitionTest.php` | Controlled governance workflows |
| Reports and creator visibility | `CreatorReportAccessTest.php`, `ReportDeliveryTest.php`, `ReportVersioningTest.php`, `PublicVerificationTest.php`, `PublicVerificationSnapshotTest.php` | Disclosure and historical integrity |
| Notifications and action queues | `WorkflowNotificationTest.php` | Recipient, tenancy and stale-state safety |
| Cross-cutting authorization | `AuthorizationTest.php`, `AuditLogTest.php` | Server-side role and audit boundaries |
| UI and Livewire transport | `Phase2UiAuditTest.php`, Auditor workspace tests | No client-controlled lifecycle or tenant identifiers |

## Required invariants

1. Authorization is enforced server-side. UI visibility is never the security boundary.
2. Organization-scoped data cannot be accessed through another tenant's identifiers.
3. Auditor work is available only to assigned, eligible and conflict-cleared Auditors.
4. Frozen Standard Versions remain the source of truth for an active Evaluation.
5. Individual Auditor work remains distinct from collective voting and the final Evaluation Decision.
6. Submitted Auditor work and published reports retain their historical meaning.
7. Creator views expose only information permitted by the disclosure model.
8. Notifications and action queues are derived from authoritative state and cannot bypass current authorization.
9. High-trust lifecycle changes go through controlled application/domain services rather than presentation code.
10. Direct requests and Livewire payloads must not permit mutation of locked tenant, assignment, evaluation or request context.
11. Accessibility-critical landmarks and keyboard/focus affordances remain present in representative workflows.
12. Raw/bulk database mutations remain outside application workflow boundaries.

## Exit gate

Phase 3 may be declared complete only after the regression matrix passes in CI with Pint/lint, PHPStan and the complete test suite green. Any newly discovered security, tenancy, privacy, lifecycle, methodology, historical-integrity or accessibility blocker reopens the Phase 3 gate.
