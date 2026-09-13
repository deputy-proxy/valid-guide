# valid.guide

Valid.guide is an independent validation platform for courses, guides, and other online learning products.

It turns product quality into a credible, transparent signal that buyers can understand and creators can use to build trust. The application is designed around independent evaluation, versioned methodology, controlled governance, historical integrity, and a public verification record.

---

## Product Definition

Valid.guide is:

- an independent validation service;
- a transparent quality-assessment system;
- a structured quality-assessment process performed by qualified independent Auditors;
- a public trust signal backed by a verifiable record;
- a workflow platform separating creator intake, commercial operations, Auditor work, governance and public verification.

Valid.guide is not:

- a generic review marketplace;
- a star-rating website;
- an affiliate catalogue whose incentives depend on recommendations;
- a pay-for-positive-review service;
- a guarantee of learner outcomes;
- a substitute for buyer judgement.

Creators pay for an evaluation, never for a positive result. Payment must never influence the evaluation outcome.

The application currently includes the validation domain and its supporting operational workflows, together with the completed Expert, marketplace and community capabilities from Phase 4. Guides, Opportunities & Matching are complete. Phase 5 now adds trust-state monitoring, subscription products, public directory and product-detail discovery, public verification, public website surfaces, and lifecycle trust/billing communication.

---

## Product Principles

The project is governed by the following principles:

1. **Independence** — evaluation outcomes are independent of commercial relationships.
2. **Transparency** — the methodology and relevant evaluation information are understandable and verifiable.
3. **Evidence** — conclusions are grounded in submitted product material and documented evaluation work.
4. **Consistency** — evaluations use controlled, versioned standards and methodology configuration.
5. **Historical integrity** — completed evaluations, reports, decisions and public trust records retain their historical meaning.
6. **Accountability** — important decisions, conflicts, lifecycle changes and trust-state changes are auditable.
7. **Privacy** — private creator material, evidence and internal deliberation are not public by default.
8. **Buyer usefulness** — public information should help people make better-informed decisions without overstating what validation proves.
9. **Fail closed** — malformed methodology, unauthorized operations and incomplete decision prerequisites must block trusted output rather than silently produce it.
10. **Controlled mutation** — high-trust domain changes occur through explicit application/domain workflows instead of uncontrolled CRUD or bulk database writes.

---

## Primary Actors

### Creator

The person or organization submitting a learning product for validation.

Creators can manage organization-scoped products, request evaluations, provide access and supporting material, follow evaluation progress, receive reports, respond to controlled clarification requests, and view validation and billing information permitted by the disclosure model.

### Auditor

An independent subject-matter expert responsible for evaluating an assigned product against the applicable Evaluation Standard.

Auditors maintain their profile and eligibility information, declare conflicts of interest, receive and accept assignments, access only authorized evaluation material, perform structured criterion assessments, attach evidence and findings, submit evaluation work, and participate in controlled compensation workflows.

The current domain consistently uses **Auditor** as the product role name.

### Valid.guide Administrator

An authorized platform operator responsible for governance, Auditor eligibility and assignments, conflicts, evaluation decisions, Validation state, reports, disputes, commerce operations, auditability and other platform-level operations.

### Buyer / Learner

The public consumer of validation information. The current product primarily serves this actor through public verification and validation information rather than a dedicated authenticated buyer account workflow.

---

## Core Domain Model

The application is organized around explicit domain boundaries rather than an uncontrolled collection of CRUD records.

### Identity & Access

- User
- Organization
- Organization Membership
- Owner, Admin, Editor and Billing membership roles
- Separate platform administration authority
- Server-side authorization and tenant isolation

### Product & Intake

- Product
- Product Release
- Evaluation Request
- Submitted Material / Access
- Product lifecycle and release history

A Product is the enduring entity. A Product Release identifies the exact edition, version or state evaluated. Validation and Evaluation records therefore retain historical context rather than depending only on mutable current Product data.

### Methodology

- Evaluation Standard
- Standard Version
- Criterion
- Criterion Guidance
- Applicability Rules
- Scoring Rules / Assessment Anchors
- Product-type weighting profiles
- Decision thresholds
- Auditor staffing rules
- Criterion voting modes

Methodology is versioned. The applicable Standard Version is frozen for an active Evaluation and its scoring, staffing, applicability and voting configuration remain historically interpretable.

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
- Clarification Request

Auditor work is distinct from collective voting and final Evaluation Decision work.

### Decision & Trust

- Evaluation Decision
- Validation
- Badge
- Public Verification Record / Snapshot
- Report / Report Version
- Report Delivery
- Clarification Request
- Formal Dispute
- Independent Review

The public verification record is a persisted trust snapshot. The public verification experience must not reconstruct historical public state from mutable internal records.

### Commerce

- Service Package
- Evaluation Request commercial snapshot
- Order / Payment
- Refund
- Auditor Compensation
- Payout

Commercial state is intentionally separate from evaluation outcome.

### Platform

- Notification
- Action Queue / workflow action state
- Audit Log
- Public Directory projection

The Expert Board, expert discovery, community and marketplace capabilities are implemented as governed Phase 4 capabilities. Phase 5 extends those foundations with governed monitoring, subscriptions, public discovery, verification, product-detail and website surfaces, and lifecycle communication.

---

## Core Business Lifecycle

The business lifecycle is separate from the development roadmap.

```text
Evaluation Request
        ↓
Draft / Intake
        ↓
Commercial terms fixed / payment
        ↓
Materials received and verified
        ↓
Evaluation starts
        ↓
Standard Version frozen
        ↓
Auditor eligibility + assignment + COI clearance
        ↓
Auditor work
        ↓
Evidence / findings / criterion assessment
        ↓
Auditor submission + finalization
        ↓
Collective voting where methodology requires it
        ↓
Evaluation Decision
        ↓
Validated OR Not Validated / other controlled outcome
        ↓
Report / publication / Validation / Badge
```

Clarifications, refunds, disputes, report delivery and other exceptional workflows are controlled workflows. They must not silently mutate historical Auditor work or completed evaluations.

### Lifecycle Rules

- Draft Evaluation Requests may exist before commercial terms are frozen and before a Product Release is selected.
- Payment does not determine evaluation outcome.
- Product Release must belong to the requested Product when supplied.
- The applicable Standard Version is frozen for the life of the Evaluation.
- Standard Version methodology content becomes immutable after scheduling.
- Auditor assignments require eligibility and conflict clearance.
- Required Auditor panel size is controlled by the methodology version.
- Auditor evaluation submissions require explicit evidence-sufficiency and audience/promise-coherence conclusions.
- Claimed outcomes require supporting evidence before Auditor submission can be accepted.
- Individual Auditor assessments remain distinct from collective voting.
- Validation cannot be issued when required decision gates are unresolved or failed.
- Published reports and public verification records preserve their historical meaning.
- High-trust lifecycle transitions are performed through controlled application/domain services.

---

## Methodology

Valid.guide uses a common core methodology with product-type modules.

Supported product formats in Validation Methodology v1 include Course, Cohort course, Guide, Ebook learning product, Workshop, Program and Membership. The `other` product type requires an explicit applicability profile rather than being silently assigned to one of the fixed profiles.

The authoritative methodology is maintained in `docs/validation-methodology-v1.md`.

### Validation Methodology v1

The methodology defines ten dimensions:

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

### Assessment Scale

Criterion assessments are controlled and distinct from numerical scores:

- `exceeds`
- `meets`
- `partially_meets`
- `does_not_meet`
- `insufficient_evidence`
- `not_applicable`

The default v1 numerical anchors are:

| Assessment | Score range |
| --- | ---: |
| Exceeds | 90–100 |
| Meets | 75–89 |
| Partially Meets | 50–74 |
| Does Not Meet | 0–49 |
| Insufficient Evidence | No numerical score |
| Not Applicable | No numerical score |

Scoring anchors and decision thresholds are stored on the Standard Version so the scoring policy is versioned rather than hard-coded in the decision engine.

### Auditor Staffing

Evaluation complexity is controlled as:

- Simple = 1 Auditor
- Standard = 1 Auditor
- Complex = 3 Auditors
- Exceptional = 5 Auditors

Configured panel sizes must be positive and odd. Staffing rules are versioned with the applicable Standard Version.

### Criterion Voting

Criteria support methodology-controlled voting modes:

- `individual`
- `majority`

Individual criteria retain separate Auditor results and require categorical agreement before collective resolution. Majority criteria use an odd number of distinct Auditor votes and simple majority aggregation. Underlying Auditor results remain historically distinct.

### Methodology Validation

A Standard Version cannot be scheduled unless its methodology configuration is valid. Validation includes complete assessment coverage, numerical score coverage, decision thresholds, product-type weighting profiles, dimension coverage, effective weights, applicability rules, voting configuration and staffing rules.

---

## Public Trust Model

The Verification Page and persisted Public Verification Snapshot are authoritative for public trust information. A badge is a signal that points to the verification record rather than an independent source of truth.

### Authoritative Record

The internal Evaluation, Evaluation Decision and Validation records provide the governed source of truth. The persisted Public Verification Snapshot provides the historical public representation that may be safely rendered without reconstructing past state from mutable records.

### Public Record

A public verification record contains, where applicable:

- product and release identity;
- creator;
- product type;
- Validation status and date;
- Standard Version;
- evaluation scope;
- overall result;
- published criterion results;
- strengths and weaknesses where disclosure permits;
- Auditor count;
- Auditor identities and credentials where disclosure is permitted;
- unique verification identifier;
- relevant status history and revocation information;
- a Valid.guide-generated public abstract;
- optional full report content.

### Public Visibility Rules

- Private creator material and internal Auditor deliberation are not public by default.
- Creator-facing report access follows the disclosure model.
- Public verification renders from persisted public snapshot data.
- Creator corrections enter controlled clarification workflows rather than mutating published history directly.
- Validated products may be exposed through public verification and directory surfaces where applicable.
- Expert discovery, community and marketplace public surfaces are governed by their Phase 4 disclosure rules.
- The public directory, subscription and monitoring capabilities are now implemented as dedicated Phase 5 product surfaces.

---

# Development Roadmap

The phases below represent the **original Valid.guide product development roadmap**. Technical issue groupings are implementation slices and do not replace this roadmap.

---

## Phase 0 — Product & Architecture

**Status: Complete**

### Objective

Define what Valid.guide is and establish the business, domain, methodology, trust and architecture decisions required before implementation.

### Scope

- Product positioning and non-goals
- Actor model
- Domain boundaries
- Product and Product Release model
- Evaluation and Validation lifecycles
- Auditor eligibility and conflict rules
- Validation Methodology v1
- Trust and verification architecture
- Reports and disputes
- Commerce rules
- Tenancy and authorization principles
- Historical-integrity requirements

### Completion Criteria

- Product definition approved and documented.
- Domain and database specification established.
- Validation Methodology v1 established.
- Major lifecycle and trust invariants documented.
- Architecture suitable for implementation established.

---

## Phase 1 — Application Foundation & Domain Implementation

**Status: Complete**

### Objective

Turn the approved architecture into a secure, persistent Laravel domain foundation.

### Scope

- Core Eloquent/domain model
- Migrations and database constraints
- Organization tenancy and authorization
- Lifecycle transitions and invariants
- Auditability
- Methodology persistence and versioning
- Evaluation and decision persistence
- Trust/public-verification persistence
- Historical-integrity protections
- Regression coverage

### Completion Criteria

- Core domain persistence implemented.
- Server-side authorization and tenancy boundaries implemented.
- Lifecycle and historical-integrity rules executable.
- Methodology versioning implemented.
- Trust persistence implemented.
- Phase 1 invariant audit completed.
- Pint/lint, PHPStan and the complete test suite pass in CI.

Phase 1 completion is documented by the Phase 1 invariant audit and implementation history; the former issue reference **#33** is no longer authoritative because #33 is now the Phase 4.6 marketplace-governance issue.

---

## Phase 2 — Evaluator & Action Plan

**Status: Complete**

### Objective

Build the actual evaluation engine and the structured work produced from an evaluation: evaluation intake, standards, Auditor work, evidence/findings, decisions, reports, Validation and resulting action-oriented output.

### Scope

- Evaluation Request intake
- Commercial terms and payment workflow
- Product Release selection
- Auditor onboarding and eligibility
- Auditor assignments and conflict declarations
- Evaluation workspace
- Criterion applicability and assessment
- Evidence and findings
- Auditor submission and finalization
- Methodology-controlled voting
- Methodology-controlled staffing
- Evaluation Decision
- Validation issuance and state transitions
- Reports and report versions
- Creator report access
- Public verification
- Notifications and action queues
- Creator and internal operational UI foundations

### Completion Criteria

- Evaluation lifecycle is fully controlled.
- Auditor work is isolated by assignment, eligibility, conflict and tenancy.
- Methodology configuration is frozen and enforced.
- Required evidence and decision gates are enforced.
- Decisions and Validation are governed by explicit services.
- Reports preserve historical meaning.
- Creator visibility follows the disclosure model.
- Relevant Filament and public UI workflows are implemented and audited.
- Pint/lint, PHPStan and the complete test suite are green in CI.

---

## Phase 3 — Guides, Opportunities & Matching

**Status: Complete**

### Objective

Expand Valid.guide beyond validation results into actionable guidance and discovery: structured recommendations, improvement opportunities, matching validated products to relevant audiences/use cases, and product experiences that make evaluation results useful after the report is delivered.

### Scope

- Structured improvement guidance
- Opportunities derived from evaluation findings
- Creator action plans beyond the current report/finding foundation
- Product-to-audience/use-case matching
- Discovery experiences based on validated product information
- Recommendation logic
- Buyer-facing usefulness beyond verification

### Completion Criteria

- Guidance is a dedicated product capability rather than only report prose.
- Opportunities can be represented, tracked and surfaced appropriately.
- Matching has explicit domain and product rules.
- Public discovery remains grounded in authoritative Validation data.
- Recommendation behavior does not compromise independence.
- Appropriate UI and authorization boundaries are implemented.
- Pint/lint, PHPStan and the complete test suite are green in CI.

Phase 3 was formally closed by the Phase 3.6 integration, accessibility and regression audit in issue **#14**. The audit covers the complete findings/report → guidance → opportunities → matching → discovery → recommendation lifecycle.

---

## Phase 4 — Experts, Marketplace & Community

**Status: Complete**

### Objective

Build the broader expert ecosystem around Valid.guide: Auditor Board growth, expert profiles, discovery, community participation, expert opportunities and marketplace capabilities while preserving independence and avoiding pay-to-play incentives.

### Scope

- Auditor Board growth
- Expert profiles and expertise discovery
- Expert opportunities
- Expert/community participation
- Marketplace capabilities
- Marketplace governance and independence safeguards

### Completion Criteria

- Expert discovery is implemented.
- Marketplace participation rules preserve independence.
- Community capabilities have explicit moderation and authorization rules.
- Commercial incentives cannot purchase or influence Validation outcomes.
- Expert lifecycle and compensation remain auditable.
- Appropriate public and internal UI are implemented.
- Pint/lint, PHPStan and the complete test suite are green in CI.

Phase 4 was formally closed by the Phase 4.7 integration audit in issue **#34**. Issues **#28–#33** implemented the Expert Board, profiles/discovery, opportunities, community, marketplace and marketplace-governance slices that the final audit integrated and verified.

---

## Phase 5 — Monitor, Subscription & Public Product

**Status: Complete**

### Objective

Build the public-facing product around ongoing trust and user value: monitoring, subscriptions, public discovery, verification, directory experiences, public website surfaces and lifecycle notifications.

### Scope

- Monitoring of Validation/trust state
- Subscription products
- Public discovery
- Public directory
- Verification experiences
- Public website surfaces
- Lifecycle notifications
- Ongoing trust-state communication

### Completion Criteria

- Public discovery and directory behavior are implemented.
- Monitoring is implemented with explicit lifecycle rules.
- Subscription products and billing behavior are implemented.
- Public verification remains based on authoritative persisted snapshots.
- Notifications cover relevant lifecycle events.
- Public product accessibility and authorization are audited.
- Pint/lint, PHPStan and the complete test suite are green in CI.

Phase 5 was formally closed by the Phase 5.7 integration and accessibility audit in issue **#51**. Issues **#45–#50** implemented the monitoring, subscription, public directory, public verification/product, public website and lifecycle communication slices that the final audit integrated and verified.

---

## Phase 6 — Optimization & Scale

**Status: Not started**

### Objective

Prepare Valid.guide for sustained operation and scale: calibration, quality measurement, operational optimization, automation, analytics, performance, reliability, observability, security hardening and product iteration based on real-world usage.

### Scope

- Methodology calibration
- Quality measurement
- Operational optimization
- Automation
- Analytics
- Performance optimization
- Reliability engineering
- Observability
- Security hardening
- Product iteration based on operational evidence

### Completion Criteria

- Production quality and calibration processes are established.
- Operational metrics and observability are sufficient for reliable operation.
- Performance and reliability targets are defined and met.
- Security controls are reviewed and hardened.
- Automation is introduced only where it preserves domain integrity.
- Product iteration is driven by measured usage and quality evidence.

---

# Current Reconciliation

The current repository should be understood against the original roadmap as follows.

| Original phase | Current status | Reconciliation |
| --- | --- | --- |
| **Phase 0 — Product & Architecture** | **Complete** | Product, architecture, domain, methodology, trust, commerce and historical-integrity foundations are established. |
| **Phase 1 — Application Foundation & Domain Implementation** | **Complete** | Domain persistence, tenancy, authorization, lifecycle controls, methodology, trust persistence and invariant regression coverage are implemented and audited. |
| **Phase 2 — Evaluator & Action Plan** | **Complete** | Former technical Phase 2 and Phase 3 work covers most of the evaluation engine and operational workflow. The implementation has been reconciled back to the original product phase rather than replacing its numbering. |
| **Phase 3 — Guides, Opportunities & Matching** | **Complete** | Issue #14 closed the Phase 3.6 integration, accessibility and regression audit across guidance, opportunities, matching, discovery and recommendations. |
| **Phase 4 — Experts, Marketplace & Community** | **Complete** | Issues #28–#34 completed and audited the Expert Board, profiles, opportunities, community, marketplace and independence-governance lifecycle. |
| **Phase 5 — Monitor, Subscription & Public Product** | **Complete** | Issue #51 closed the integration, accessibility and regression gate across monitoring, subscriptions, public discovery, verification/product, website surfaces and lifecycle communication. |
| **Phase 6 — Optimization & Scale** | **Not started** | Reserved for post-core-product optimization and operational scale. |

### Reconciliation Rules

- The original product roadmap remains authoritative.
- Technical issue groupings are implementation slices, not replacement phases.
- Completed technical work must be mapped back to the appropriate product phase.
- Partially implemented capabilities must be explicitly identified.
- Existing foundations must not be unnecessarily rebuilt.
- A capability is not considered complete merely because supporting infrastructure exists.
- Technical milestones may be named "Phase 2" or "Phase 3" in historical issue records without changing the meaning of the original product phases.

---

# Existing Implementation Milestones

The technical implementation history remains visible because it explains how the original roadmap has been executed.

### Phase 1 Completion — Invariant Audit

The Phase 1 specification-to-code invariant audit established and verified lifecycle, methodology, assessment, decision, voting, staffing, Auditor eligibility/conflict, reporting, refunds, compensation, public verification and authorization boundaries. The former issue reference **#33** has been removed because that issue now belongs to Phase 4.6.

### Former Technical Phase 2 Completion — Issue #50

Issue **#50** closed the creator intake, commerce, payment, refund and readiness audit for the former technical Phase 2 grouping.

### Public Verification — Issue #57

Issue **#57** implemented the public verification experience on top of the persisted public trust snapshot.

### Auditor / Governance Implementation — Issues #92–#100

Issues **#92–#100** established and audited the main Auditor, Creator post-evaluation and Platform Administrator workflow surfaces. Issue **#100** is the completion gate for that technical Phase 3 grouping and records the regression matrix that must remain true while those surfaces evolve.

### Phase 3 Completion — Issue #14

Issue **#14** closed the Phase 3.6 integration, accessibility and regression audit covering structured guidance, improvement opportunities, matching, public discovery and explainable recommendations.

### Phase 4 Completion — Issues #28–#34

Issues **#28–#34** completed the Expert Board, public expertise discovery, expert opportunities, community participation, marketplace, marketplace governance and final Phase 4 integration audit.

### Implementation History Rules

Technical milestones are subordinate to the original product roadmap.

They must not be interpreted as a new phase numbering system unless the product roadmap itself is formally changed.

---

# Authoritative Specifications

The following documents are authoritative or implementation-significant:

- `docs/domain-and-database-specification.md` — authoritative domain model, persistence model, lifecycle rules, authorization boundaries, trust model, commerce rules and public verification architecture.
- `docs/validation-methodology-v1.md` — authoritative Validation Methodology v1.0, including dimensions, criteria, scoring, blockers, Auditor rules, complexity and decision logic.
- `docs/phase-1-decisions.md` — implementation-significant decisions made while completing Phase 1.
- `docs/implementation-decisions.md` — implementation decisions and intentional deviations recorded during development.
- `docs/phase-1-invariant-audit.md` — Phase 1 specification-to-code invariant regression audit.
- `docs/phase-2-invariant-audit.md` — Phase 2 domain/invariant audit.
- `docs/phase-2-ui-regression-audit.md` — Phase 2 UI regression and accessibility audit.
- `docs/phase-3-integration-audit.md` — technical Phase 3 integration and regression completion gate.
- `docs/phase-4-integration-audit.md` — Phase 4.7 Expert, marketplace and community integration audit.
- `docs/issue-31-public-verification-snapshot.md` — public verification snapshot architecture and historical trust representation.
- `docs/auditor-workspace-conventions.md` — Auditor workspace implementation conventions.
- `docs/ui-conventions.md` — internal UI conventions.
- `docs/public-ui-conventions.md` — public UI conventions.
- `docs/refund-workflow.md` — refund workflow specification.

### Specification Authority

When implementation conflicts with an approved specification:

1. identify the conflict;
2. determine whether the specification or implementation is incorrect;
3. document the decision;
4. update the authoritative specification when the product decision changes;
5. implement the approved result.

No important architectural, product, security, methodology or domain decision should exist only in an issue comment, chat message or undocumented implementation detail.

---

# Development Rules

1. Important architectural, product, methodology, workflow, policy, security and data decisions must be documented.
2. Do not silently contradict an approved specification.
3. Preserve historical truth and data integrity.
4. Authorization must be enforced server-side.
5. UI visibility must never be treated as a security boundary.
6. Sensitive mutations must use controlled application/domain workflows.
7. Database writes must respect domain invariants.
8. Every implementation issue must account for Pint/lint, PHPStan and relevant automated tests.
9. New functionality must include appropriate regression coverage.
10. Existing functionality must not be broken to implement unrelated work.
11. Technical debt introduced deliberately must be documented.
12. CI must be green before an implementation issue is considered complete.
13. A pull request is not complete merely because the code works locally.
14. The final implementation must satisfy the repository's lint, static-analysis and test requirements.
15. Historical records must remain interpretable according to the rules under which they were created.
16. Client-controlled identifiers must never override authenticated tenant, assignment, evaluation or workflow context.
17. Raw or bulk database mutations remain outside application workflow boundaries except in explicitly controlled service-layer implementation paths.
18. Methodology configuration must fail closed when malformed or incomplete.

---

# Quality Gates

Every implementation must pass the project's required quality gates.

### Lint

```bash
composer lint:check
```

### Static Analysis

```bash
composer types:check
```

### Tests

```bash
composer test
```

### Full Validation

```bash
composer test
```

The repository's `test` script clears configuration, runs the Pint check, runs PHPStan and executes the Laravel test suite. CI is therefore expected to exercise these quality layers as a single completion gate.

### Completion Requirement

An implementation issue is complete only when:

- lint passes;
- static analysis passes with zero errors;
- relevant tests pass;
- the complete test suite passes;
- CI is green;
- the implementation conforms to the applicable specifications;
- the issue acceptance criteria are satisfied.

---

# Testing Strategy

## Unit Tests

Unit tests are applicable to isolated domain logic and pure components where database persistence is not required. Database-backed lifecycle and domain-state workflows are intentionally classified as Feature tests.

## Feature / Integration Tests

Feature tests cover application workflows, Eloquent persistence, domain services, authorization, lifecycle transitions, methodology validation, reports, trust, notifications and UI transport boundaries.

## Domain / Invariant Tests

Domain invariants are tested explicitly, including:

- lifecycle transitions;
- immutability boundaries;
- methodology completeness;
- scoring anchors;
- decision thresholds;
- Auditor staffing;
- voting rules;
- conflict-of-interest rules;
- evidence requirements;
- historical integrity;
- report versioning;
- Validation issuance and transitions.

## Authorization Tests

Authorization coverage verifies role boundaries, organization tenancy, platform administration authority, Auditor assignment access, creator visibility and protection against forged or client-controlled identifiers.

## Regression Tests

Phase-specific regression audits are maintained in `docs/phase-1-invariant-audit.md`, `docs/phase-2-invariant-audit.md`, `docs/phase-2-ui-regression-audit.md`, `docs/phase-3-integration-audit.md` and `docs/phase-4-integration-audit.md`.

## CI

CI is the authoritative completion gate. Formatting, PHPStan/static analysis and the test suite must remain green before an implementation issue is considered complete.

---

# Security & Authorization

## Authentication

Authentication is provided through the Laravel application and Fortify. A single authentication identity may participate in creator, Auditor and administrative capacities subject to authorization.

## Authorization

Authorization is enforced server-side through the application's domain/application boundaries. Organization membership roles include Owner, Admin, Editor and Billing. Platform administration authority is separate from creator organization membership.

Billing membership does not grant Product or evaluation-content access. Product management is restricted to Owner, Admin and Editor roles.

## Tenancy / Data Isolation

Organization-scoped data is resolved from authenticated membership. Client-supplied organization identifiers are never trusted without persisted membership verification.

Feature coverage explicitly tests forged organization identifiers, cross-tenant Product identifiers, role restrictions and direct ownership mutation attempts.

## Sensitive Operations

Sensitive operations include:

- lifecycle transitions;
- methodology scheduling and freezing;
- Auditor assignment and conflict determination;
- evaluation submission and finalization;
- Evaluation Decision;
- Validation issuance and state transitions;
- report delivery and publication;
- refunds and compensation;
- public trust-state changes.

These operations use controlled application/domain services and are protected against arbitrary presentation-layer mutation.

## Auditability

Important operations are recorded through the Audit Log. Conflict determinations, governance actions, trust-state changes and other high-trust mutations must remain auditable.

---

# Data & Historical Integrity

Valid.guide treats historical truth as a first-class domain requirement.

### Immutable Records

The following information becomes immutable at defined lifecycle boundaries:

- frozen Standard Version methodology content;
- submitted Auditor evaluation conclusions;
- conflict declaration determinations;
- completed historical evaluation work;
- published report versions;
- public verification snapshots;
- other records explicitly protected by lifecycle guards.

### Versioned Records

Versioned or snapshot-based records include:

- Standard Version;
- scoring anchors and decision thresholds;
- methodology weighting and staffing configuration;
- Product Release;
- Report / Report Version;
- Public Verification Snapshot;
- historical Evaluation and Validation state.

### Snapshot Rules

Historical public information must be persisted when required to preserve what was actually published. Public verification must render from the persisted snapshot rather than dynamically reconstructing old state from mutable Product, Organization or methodology records.

### Historical Interpretation

Completed Auditor work, standards, decisions, reports and Validation records must remain interpretable according to the rules that governed them when they were created or frozen.

Lifecycle guards compare persisted state where necessary so Eloquent enum casts, stale model instances or loaded relationships cannot bypass historical-integrity rules.

---

# User Interfaces

## Public Website

The public surface includes the completed website, directory, product-detail and verification experiences, all backed by explicit public projections or persisted verification snapshots rather than private internal records.

## Authenticated Application

The creator-facing application provides organization-scoped product and evaluation workflows, dashboard/action information, report access and permitted post-evaluation visibility.

## Administration

Filament 5 is the primary internal application UI for operational and governance workflows.

## Operational Interfaces

Operational interfaces cover Auditor workspaces, assignments, evaluation workflows, platform governance, reports, trust operations, commerce/refunds and action queues.

## UX Principles

- UI visibility is never the security boundary.
- Interfaces consume domain/application capabilities rather than duplicate lifecycle rules.
- High-trust actions are explicit and workflow-specific.
- Accessibility-critical landmarks and keyboard/focus affordances must remain present in representative workflows.
- Public verification is rendered from authoritative persisted public snapshot data.
- Internal Auditor work remains private and assignment-scoped.

---

# Notifications & Background Processing

## Notifications

Notifications exist for role-specific workflow events and action queues. The implementation derives notifications from authoritative state and protects recipient, tenancy and stale-state safety.

Examples include workflow progress, required actions, evaluation events, report availability and other role-appropriate state changes.

## Queues / Jobs

Dedicated queue architecture is **not a distinct current product capability documented as authoritative**. Where asynchronous processing is introduced, it must preserve idempotency, authorization, lifecycle and historical-integrity rules.

## Scheduled Tasks

Trust monitoring is scheduled every fifteen minutes and processes due monitor records with explicit cadence semantics. Subscription lifecycle transitions are controlled by the subscription domain service and communicate through the existing audit-driven notification pipeline. Production-scale operational scheduling and monitoring remain Phase 6 concerns.

## Failure Handling

Workflow failures must fail closed and must not create trusted output from incomplete state. Retry/idempotency behavior for any future asynchronous integration must be documented before becoming authoritative.

---

# Observability & Operations

## Logging

Application and audit logging are applicable. The Audit Log is the authoritative business trace for important domain operations.

## Monitoring

Dedicated production monitoring and operational dashboards are **not applicable as a completed product capability in the current repository**. They are part of Phase 6.

## Metrics

Dedicated production business and operational analytics are **not applicable as a completed capability in the current repository**. Phase 6 is responsible for establishing the required measurement model.

## Alerts

Dedicated production alerting is **not applicable as a completed capability in the current repository**. Alerting requirements should be defined as operational scale increases.

## Backups & Recovery

A complete production backup and disaster-recovery specification is **not currently an authoritative product capability in the repository**. It must be established before production-scale operations are treated as complete.

---

# Commerce

## Products / Packages

The domain supports Service Packages and an Evaluation Request commercial snapshot. Commercial terms are frozen before payment begins according to the controlled evaluation-request workflow.

## Orders

Orders/payment records represent the commercial transaction and are separate from Evaluation outcome.

## Payments

Payment state is persisted and controlled. Payment confirms commercial state only and must never be interpreted as a positive evaluation result.

The repository currently provides the application/domain workflow for payment state but does not define a dedicated external payment-provider integration as an authoritative public capability.

## Refunds

Refunds are a controlled workflow documented in `docs/refund-workflow.md`. Refund processing must preserve the separation between commercial state and evaluation outcome.

## Revenue / Compensation

Auditor Compensation and Payout concepts are part of the domain. Compensation must remain auditable and must never create pay-to-play influence over Evaluation Decisions.

## Commercial Integrity

Creators purchase an evaluation, never a guaranteed result. Commercial administration, Auditor work, methodology and governance are separated in the domain model.

---

# API & Integrations

## API

A dedicated public API is **not applicable to the current product implementation**. The current application is primarily a Laravel web application with authenticated, Filament and Livewire interfaces.

## External Services

The current repository does not define a dedicated external business integration as an authoritative product capability beyond the framework/platform dependencies used to operate the application.

## Webhooks

External webhook workflows are **not applicable to the current documented product scope**.

## Integration Rules

For future integrations, authentication, authorization, idempotency, retries, failure handling and historical integrity must be documented before an integration becomes a trusted domain boundary.

---

# Configuration

The application is configured as a Laravel 13 project using PHP 8.4+, Filament 5, Livewire 4, Flux 2, Tailwind CSS 4 and Vite Plus. The principal package definitions are maintained in `composer.json` and `package.json`.

| Configuration | Purpose |
| --- | --- |
| `.env` / `.env.example` | Application environment and service configuration |
| `config/*` | Laravel framework and application configuration |
| `composer.json` | PHP dependencies and development quality scripts |
| `package.json` | Front-end dependencies and build/dev scripts |
| `phpstan.neon` | PHPStan configuration |
| `pint.json` | Laravel Pint configuration |
| `vite.config.js` / equivalent Vite configuration | Front-end asset build configuration |

Sensitive credentials must remain outside source control.

---

# Repository Structure

```text
valid-guide/
├── app/
│   ├── Models/
│   ├── Services/
│   ├── Enums/
│   ├── Policies/
│   └── ...
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
├── resources/
│   ├── css/
│   └── views/
├── routes/
├── tests/
│   ├── Feature/
│   └── Unit/
├── .github/
├── composer.json
├── package.json
└── README.md
```

The repository follows standard Laravel boundaries. Domain/application behavior is implemented under `app`, persistence under `database`, UI under `resources`, HTTP routing under `routes`, automated coverage under `tests`, and authoritative product/architecture decisions under `docs`.

---

# Development Workflow

## Issue Naming Convention

Every GitHub Issue, whether open or closed, must follow this naming convention:

```text
Phase [PhaseNumber].[IssueNumber] - [Issue Title]
```

## Branching

Implementation branches use the established convention:

```text
[currentPhase]-[currentIssue] Development Branch
```

For example:

```text
phase-3-99 Development Branch
```

## Issue Workflow

1. Review the applicable specification.
2. Review issue acceptance criteria.
3. Check existing domain/application behavior before adding new behavior.
4. Create the implementation branch using the phase/issue convention.
5. Implement the change within the established domain boundaries.
6. Run lint.
7. Run PHPStan.
8. Run relevant tests.
9. Run the complete test suite.
10. Push the branch.
11. Wait for CI.
12. Resolve every CI failure.
13. Open the pull request.
14. Merge only after required checks are green.
15. Close the issue when its acceptance criteria and quality gates are satisfied.

## Pull Requests

The established implementation PR naming convention is:

```text
#[issue-number] implemented
```

Documentation-only changes may use an appropriate documentation commit/PR name while preserving the same review and CI expectations.

## CI Requirement

A pull request must not be considered complete until the required CI checks are green. This applies equally to domain, application, UI, documentation and infrastructure changes when those changes are covered by CI.

---

# Documentation Rules

Documentation must be updated when implementation changes:

- architecture;
- domain behavior;
- business rules;
- methodology;
- authorization;
- lifecycle behavior;
- public behavior;
- security;
- data integrity;
- operational procedures;
- roadmap interpretation.

The README is the high-level project map. Detailed specifications remain in `docs/` and are authoritative where explicitly designated.

Historical implementation decisions remain documented rather than silently removed when later work changes the implementation. This is necessary to understand why current code behaves as it does.

---

# Current Direction

The repository has completed the product-definition, application-foundation, evaluator, Auditor, governance, report, Validation, public-verification, Guides, Opportunities & Matching, Expert, marketplace, community and Phase 5 public-product capabilities described by Phases 0–5.

The current product roadmap continues with the original product sequence. Phase 5 is complete, and Phase 6 is now the next roadmap phase rather than creating another parallel phase numbering system.

### Immediate Objective

The next major original product capability is **Phase 6 — Optimization & Scale**.

Phase 5 has delivered monitoring of Validation/trust state, subscription products, public directory and discovery, public verification/product experiences, public website surfaces and ongoing lifecycle communication on top of the existing Evaluation, Validation, Expert, marketplace and community foundations. Phase 6 should now address calibration, quality measurement, operational optimization, automation, analytics, performance, reliability, observability and security hardening.

### Current Dependencies

- Preserve the existing Evaluation and Validation historical-integrity model.
- Reuse authoritative Findings, Reports, Validation and public snapshot data rather than creating competing sources of truth.
- Maintain server-side authorization and tenant isolation.
- Preserve Auditor and Expert independence and prevent commercial or community incentives from influencing validation outcomes.
- Keep technical issue numbering subordinate to the original product roadmap.

### Explicitly Deferred

- Production-scale optimization, analytics, observability and automation from Phase 6.
- Dedicated public API and external webhook platform.
- Dedicated production monitoring/alerting and disaster-recovery documentation.

### Next Product Milestone

Deliver the first complete **Optimization & Scale** capability, extending the now-complete product foundation with measured calibration, quality, performance, reliability, observability, security and operational improvements while preserving the independence and historical integrity of the core Validation system.

---

# License

Valid.guide is licensed under the MIT License.

---

# Maintainers / Ownership

The repository is maintained under the `deputy-proxy/valid-guide` project.

Product, domain and architecture decisions should be treated as project-level decisions and documented through the authoritative specifications and implementation decision records.

---

# Additional Documentation

- `docs/domain-and-database-specification.md` — domain, persistence, lifecycle, authorization, trust and commerce specification.
- `docs/validation-methodology-v1.md` — Validation Methodology v1.
- `docs/phase-1-decisions.md` — Phase 1 implementation decisions.
- `docs/implementation-decisions.md` — cumulative implementation decisions.
- `docs/phase-1-invariant-audit.md` — Phase 1 invariant regression audit.
- `docs/phase-2-invariant-audit.md` — Phase 2 domain/invariant audit.
- `docs/phase-2-ui-regression-audit.md` — Phase 2 UI regression/accessibility audit.
- `docs/phase-3-integration-audit.md` — technical Phase 3 integration/regression audit.
- `docs/phase-4-integration-audit.md` — Phase 4.7 Expert, marketplace and community integration audit.
- `docs/issue-31-public-verification-snapshot.md` — public verification snapshot specification.
- `docs/auditor-workspace-conventions.md` — Auditor workspace conventions.
- `docs/ui-conventions.md` — internal UI conventions.
- `docs/public-ui-conventions.md` — public UI conventions.
- `docs/refund-workflow.md` — refund workflow.
