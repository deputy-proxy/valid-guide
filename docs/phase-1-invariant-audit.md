# Phase 1 Invariant Audit

> Project: Valid.guide Validation
> Issue: #33

## Purpose

This is the final specification-to-code regression matrix for Phase 1. Each material trust invariant must have executable regression coverage or an explicit reason why it cannot be reliably tested at this application layer.

## Audit matrix

| Area | Invariant | Enforcement / regression coverage |
|---|---|---|
| Methodology | Standard Version follows `draft → scheduled → effective → retired` | `StandardVersionGovernanceTest` |
| Methodology | Scheduled/effective/retired methodology content is immutable | `StandardVersionGovernanceTest`, `MethodologyAssessmentModelTest` |
| Methodology | Scheduling validates methodology completeness | `MethodologyRuleValidatorTest`, `StandardVersionGovernanceTest` |
| Assessment | Exactly six controlled assessment states exist | `MethodologyAssessmentModelTest` |
| Assessment | Categorical assessment and numerical score cannot contradict | `MethodologyAssessmentModelTest` |
| Decision | All required validation gates must pass | `EvaluationDecisionServiceTest` |
| Voting | Voting occurs only for methodology-configured majority criteria | `CriterionVotingTest`, `EvaluationDecisionServiceTest` |
| Staffing | Complexity determines exact Auditor panel size | `AuditorStaffingTest`, `AuditorAssignmentCreationTest` |
| Auditor | Assignment requires explicit eligibility and COI | `AuditorAssignmentCreationTest`, `AuditorAnnualConflictDeclarationTest` |
| Auditor | Prior participation creates a conflict | `AuditorAssignmentCreationTest` |
| Evaluation | Historical identity and completed state are immutable | `EvaluationStateTransitionTest` |
| Report | Report versions are append-only and delivery is final | `ReportVersioningTest`, `ReportDeliveryTest` |
| Refund | Delivery ends refund eligibility | `ReportDeliveryTest`, `EvaluationRequestLifecycleIntegrityTest` |
| Finance | Compensation can be reserved only once | `AuditorCompensationTest`, `PayoutServiceTest` |
| Public trust | Verification record is a complete persisted projection | `PublicVerificationSnapshotTest` |
| Public trust | Historical verification identity survives status changes | `PublicVerificationSnapshotTest`, `ValidationStateTransitionTest` |
| Security | Platform-admin-only operations cannot be bypassed through services | `AuthorizationTest` and service-specific authorization tests |
| Data integrity | Raw/bulk mutations do not occur in application workflows outside controlled services | `Phase1InvariantAuditTest` static application-path audit |
| Concurrency | Lock-protected operations preserve uniqueness/cardinality | Service transactions and database constraints; SQLite limitation documented below |

## Quality gate

The repository's Composer scripts define the required quality layers. The final audit must pass:

```text
composer lint:check
composer types:check
php artisan test
composer test
```

`composer test` is the final aggregate gate and must complete formatting validation, PHPStan and the complete test suite without errors.

## Concurrency testing limitation

The test suite uses SQLite in-memory isolation. It can exercise application-level transaction and lock paths, but it cannot faithfully prove every production-database isolation and locking characteristic. Database-specific behavior that cannot be reproduced at this layer is therefore protected by database uniqueness constraints plus the controlled transaction/lock implementation and is documented rather than falsely claimed as fully reproduced by SQLite.

## Completion rule

A Phase 1 finding is closed only when the permitted transition, ordinary Eloquent mutation boundary, relevant invalid state, authorization boundary and applicable concurrency boundary are covered or explicitly documented. A passing happy path alone is insufficient.
