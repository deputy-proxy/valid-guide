# Phase 3 Integration and Regression Audit

Issue: #14
Status: completion gate

## Scope

This audit closes the specification-to-code gap for the complete Phase 3 lifecycle. It records the regression matrix and the authoritative-state boundaries that must remain true as guidance, opportunities, matching, discovery and recommendations evolve.

## Lifecycle

```text
Evaluation Findings / Report
        ↓
Structured Guidance
        ↓
Improvement Opportunities / Creator Progress
        ↓
Audience & Use-Case Matching
        ↓
Public Discovery
        ↓
Explainable Recommendations
```

## Regression matrix

| Phase 3 area | Primary regression coverage | Required boundary |
| --- | --- | --- |
| Auditor workspace and assignments | `AuditorWorkspaceTest.php`, `AuditorEvaluationWorkspaceTest.php` | Assignment, tenancy and authorization |
| Auditor eligibility and COI | `AuditorEligibilityTest.php`, `AuditorAnnualConflictDeclarationTest.php`, `AuditorConflictDeclarationTest.php` | Eligibility and conflict gates |
| Assessment, evidence and findings | `AuditorEvidenceAndFindingTest.php`, `CriterionApplicabilityTest.php`, `CriterionVotingTest.php` | Frozen methodology and private Auditor work |
| Auditor submission | `AuditorEvaluationSubmissionTest.php`, `AuditorEvaluationFinalizationTest.php` | Submission boundary, concurrency and immutability |
| Platform governance | `PlatformGovernanceInterfaceTest.php`, `EvaluationDecisionServiceTest.php`, `ValidationIssuanceTest.php`, `ValidationStateTransitionTest.php` | Controlled governance workflows |
| Reports and creator visibility | `CreatorReportAccessTest.php`, `ReportDeliveryTest.php`, `ReportVersioningTest.php`, `PublicVerificationTest.php`, `PublicVerificationSnapshotTest.php` | Disclosure and historical integrity |
| Guidance and creator progress | `ImprovementGuidanceWorkflowTest.php`, `ImprovementOpportunityWorkflowTest.php`, `CreatorImprovementOpportunityIntegrationTest.php` | Provenance, tenancy and creator-only mutation |
| Matching and suitability | `ProductMatchingTest.php`, `ProductSuitabilityTest.php` | Explicit signals, current release and active validation |
| Public discovery | `PublicDirectoryTest.php` | Public projection, privacy and current validation state |
| Explainable recommendations | `ProductRecommendationsTest.php` | Deterministic scoring, explanations and eligibility |
| Notifications and action queues | `WorkflowNotificationTest.php`, `CreatorActionWorkflowTest.php` | Recipient, tenancy and stale-state safety |
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
13. Public discovery and recommendation results require an active Validation and an explicitly visible public projection.
14. Commercial product fields, payment state and affiliate relationships do not contribute to Validation state or recommendation eligibility.
15. Matching and recommendation explanations are derived only from explicit public suitability and trust signals and have deterministic ordering.

## Exit gate

Phase 3 may be declared complete only after the regression matrix passes in CI with Pint/lint, PHPStan and the complete test suite green. Any newly discovered security, tenancy, privacy, lifecycle, methodology, historical-integrity, performance or accessibility blocker reopens the Phase 3 gate.
