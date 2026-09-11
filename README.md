# Valid.guide

Valid.guide is an independent validation service for courses, guides, and other online learning products.

It turns product quality into a credible, transparent signal that buyers can understand and creators can use to build trust.

## Product Definition

Valid.guide is:

- an independent validation service;
- a transparent quality-assessment system;
- a structured evaluation process performed by qualified independent Auditors;
- a public trust signal backed by a verifiable record.

Valid.guide is not:

- a generic review marketplace;
- a star-rating website;
- an affiliate catalogue whose incentives depend on recommendations;
- a pay-for-positive-review service;
- a guarantee of learner outcomes;
- a substitute for buyer judgement.

Creators pay for an evaluation, never for a positive result. Payment must never influence the evaluation outcome.

## Product Principles

1. **Independence** — evaluation outcomes are independent of commercial relationships.
2. **Transparency** — the methodology and relevant evaluation information are understandable and verifiable.
3. **Evidence** — conclusions are grounded in submitted product material and documented evaluation work.
4. **Consistency** — evaluations use controlled, versioned standards.
5. **Historical integrity** — completed evaluations and published trust records retain their historical meaning.
6. **Accountability** — important decisions, conflicts and trust-state changes are auditable.
7. **Privacy** — private creator material, evidence and internal deliberation are not public by default.
8. **Buyer usefulness** — public information should help people make better-informed decisions.

## Primary Actors

### Creator

The person or organization submitting a learning product for validation.

### Auditor

An independent subject-matter expert responsible for evaluating an assigned product against the applicable Evaluation Standard.

### Valid.guide Administrator

An authorized platform operator responsible for governance, assignments, conflicts, decisions, trust state, disputes and other platform-level operations.

### Buyer / Learner

The public consumer of validation information.

## Core Domain Model

The application is organized around explicit domain boundaries rather than a collection of uncontrolled CRUD records.

### Identity & Access

- User
- Organization
- Organization Membership

Organization membership roles:

- Owner
- Admin
- Editor
- Billing

Platform administration is separate from organization membership.

### Product & Intake

- Product
- Product Release
- Evaluation Request
- Submitted Material / Access

A Product Release identifies the exact edition, version or state evaluated. Validation attaches to the evaluated release, not merely to the abstract product.

### Methodology

- Evaluation Standard
- Standard Version
- Criterion
- Criterion Guidance
- Applicability Rules
- Scoring Rules / Assessment Anchors

Methodology is versioned. An Evaluation uses the Standard Version applicable when the formal evaluation starts, and that interpretation is frozen for the life of the Evaluation.

### Evaluation Operations

- Evaluation
- Auditor Profile
- Auditor Assignment
- Annual Conflict Declaration
- Assignment Conflict Declaration
- Auditor Evaluation
- Criterion Result
- Criterion Vote
- Evidence
- Finding

### Decision & Trust

- Evaluation Decision
- Validation
- Badge
- Public Verification Record
- Report
- Report Version
- Clarification Request
- Formal Dispute
- Independent Review

### Commerce

- Service Package
- Evaluation Request commercial snapshot
- Order
- Payment
- Refund
- Auditor Compensation
- Payout

### Platform

- Notification
- Audit Log
- Public Directory projection

## Core Business Lifecycle

The business lifecycle is separate from the application's development roadmap.

```text
Evaluation Request
        ↓
Intake
        ↓
Commercial terms fixed / payment
        ↓
Materials received and verified
        ↓
Evaluation starts
        ↓
Standard Version frozen
        ↓
Auditor assignment + COI clearance
        ↓
Auditor work
        ↓
Review / voting
        ↓
Evaluation Decision
        ↓
Validated OR Not Validated
        ↓
Report / publication / Validation / Badge
```

Clarifications and formal disputes are controlled workflows. They must not silently mutate historical Auditor work or completed evaluations.

## Methodology

Valid.guide uses a common core methodology with product-type modules.

Supported product formats include:

- Course
- Cohort course
- Guide
- Ebook learning product
- Workshop
- Program
- Membership
- Other approved format

The Validation Methodology v1 defines ten dimensions:

1. Promise & Audience Fit
2. Subject-Matter Credibility
3. Content Quality & Completeness
4. Structure & Coherence
5. Learning Design & Engagement
6. Practical Applicability & Transfer
7. Evidence, Support & Intellectual Integrity
8. Usability, Accessibility & Delivery Integrity
9. Differentiation & Added Value
10. Outcome Realism & Overall Product Integrity

The authoritative methodology is maintained in `docs/validation-methodology-v1.md`.

## Public Trust Model

The Verification Page is authoritative. A badge is a signal that points to the verification record.

A public verification record contains, where applicable:

- product and release identity;
- creator;
- product type;
- Validation status and date;
- Standard Version;
- evaluation scope;
- overall result;
- published criterion results;
- strengths and weaknesses;
- Auditor count;
- Auditor identities and credentials where disclosure is permitted;
- unique verification identifier;
- relevant status history and revocation information;
- a Valid.guide-generated public abstract;
- optional full report content.

Validated products may appear in the public directory by default. Creators may opt out of directory listing while retaining a verifiable badge.

## Development Status

The repository has completed **Phase 3: Auditor, governance and post-evaluation workflows**.

Phase 3 contained nine implementation issues, #92 through #100. **All 9 of 9 issues are now complete and closed.** Issue #100 served as the final Phase 3 completion gate.

### Phase 3 completed work

The following Phase 3 issues are closed:

- **#92 — Auditor application foundation and assignment workspace**: Auditor navigation, assignment visibility, readiness presentation, authorization-driven actions and shared Auditor UI conventions.
- **#93 — Auditor onboarding, eligibility and conflict-of-interest workflow**: Auditor profile/eligibility workflow plus annual and assignment-level conflict declarations and gates.
- **#94 — Auditor evaluation workspace**: Frozen evaluation context, Standard Version enforcement, criterion workflow, Auditor notes/evidence boundaries and evaluation-state handling.
- **#95 — Criterion assessment, evidence and findings interface**: Controlled criterion assessment, evidence references, findings, voting inputs and historical submission protection.
- **#96 — Auditor submission and evaluation finalization**: Completeness validation, controlled submission, immutable submission boundary, concurrency/idempotency protections and explicit handoff to review/decision.
- **#97 — Platform administration and governance interfaces**: Evaluation operations, assignments, conflicts, submitted-material verification, decisions, Validation, reports, disputes, verification records and audit visibility.
- **#98 — Creator reports, Validation results and permitted post-evaluation actions**: Creator-facing reports, Validation state, permitted disclosure, clarification/dispute entry points and badge/trust-state presentation.
- **#99 — Notifications and role-based action queues**: Role-specific notifications and action queues derived from authoritative backend state with tenant and privacy protections.
- **#100 — Phase 3 integration, accessibility and regression audit**: Final specification-to-code audit, regression matrix, authorization and privacy boundary verification, accessibility checks, mutation-boundary checks and complete quality-gate verification.

These closures establish the main Phase 3 application surfaces across the **Auditor**, **Platform Administrator** and **Creator** workflows. The implementation has consistently treated authorization, tenancy, privacy, methodology versioning and historical integrity as server-side concerns rather than presentation-only behavior.

### Phase 3 audit result

Issue **#100 is complete**. The executable regression matrix is maintained in `docs/phase-3-integration-audit.md`, with its presence and referenced feature-test coverage guarded by `tests/Feature/Phase3IntegrationAuditTest.php`.

The final Phase 3 CI run passed:

- Pint/lint;
- PHPStan;
- the complete test suite;
- GitHub Actions CI.

The audit specifically protects the Phase 3 boundaries around tenancy, authorization, Auditor eligibility/COI, methodology versioning, historical integrity, notification state, Livewire transport and accessibility landmarks.

### Phase 3 exit criteria

All Phase 3 exit criteria are satisfied:

- all Phase 3 acceptance requirements have executable coverage or documented test-layer rationale;
- no known role, tenancy, privacy, lifecycle, methodology, historical-integrity or trust-boundary finding remains open;
- Creator, Auditor and Platform Administrator experiences remain consistent with backend state;
- private Auditor work and platform-only information cannot leak through UI or direct requests within the audited boundaries;
- accessibility-critical and responsive requirements are covered by the implemented regression checks;
- Pint/lint passes;
- PHPStan passes;
- the complete test suite passes;
- CI is green on the final Phase 3 implementation; and
- this README records Phase 3 completion.

### Current phase summary

| Phase | Status | Progress |
| --- | --- | --- |
| Phase 1 | Complete | Foundation and domain decisions established |
| Phase 2 | Complete | Core domain/application implementation completed |
| **Phase 3** | **Complete** | **9/9 issues closed; final integration audit passed** |
| **Phase 4** | **Unblocked** | Ready to begin |

The development roadmap can now advance to **Phase 4**. Human beings may proceed to create more tickets, as tradition demands.
