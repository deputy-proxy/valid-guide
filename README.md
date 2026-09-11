# Valid.guide

Valid.guide is an independent validation service for courses, guides, and other online learning products.

It turns product quality into a credible, transparent signal that buyers can understand and creators can use to build trust.

## Product Definition

Valid.guide is:

- an independent validation service;
- a transparent quality-assessment system;
- a structured quality-assessment process performed by qualified independent Auditors;
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
- Owner, Admin, Editor and Billing membership roles
- Separate platform administration authority

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
- Report / Report Version
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

The business lifecycle is separate from the development roadmap.

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

Supported product formats include Course, Cohort course, Guide, Ebook learning product, Workshop, Program, Membership and other approved formats.

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

## Development Roadmap

The phases below are the **original Valid.guide development roadmap**. They are intentionally product-oriented. Later implementation work introduced more technical issue groupings, but those issue groupings are implementation slices, not a replacement for this roadmap.

### Phase 0 — Product & Architecture

**Status: Complete**

Define what Valid.guide is and establish the business, domain, methodology, trust and architecture decisions required before implementation.

Completed foundations include the product positioning, actor model, domain boundaries, Product Release model, evaluation and Validation lifecycles, Auditor eligibility and COI rules, methodology v1, trust/verification architecture, reports and disputes, commerce rules, tenancy/authorization principles and historical-integrity requirements.

### Phase 1 — Application Foundation & Domain Implementation

**Status: Complete**

Turn the approved architecture into a secure, persistent Laravel domain foundation.

Completed work includes the core Eloquent/domain model, migrations and constraints, organization tenancy and authorization, lifecycle transitions, auditability, methodology persistence and versioning, trust/public-verification persistence, historical-integrity protections, and regression coverage.

Phase 1 completion was formally audited through issue **#33**, including Pint/lint, PHPStan, the complete test suite and CI.

### Phase 2 — Evaluator & Action Plan

**Status: Substantially implemented; reconciliation required**

Build the actual evaluation engine and the structured work produced from an evaluation: evaluation intake, standards, Auditor work, evidence/findings, decisions, reports, Validation and the resulting action-oriented output.

The current repository contains substantial implementation of this scope across the former technical Phase 2 and Phase 3 issue groups, including Evaluation Requests, commerce/intake, Auditor onboarding, assignments, criterion assessment, evidence, findings, submission, evaluation finalization, decisions, reports and Validation workflows.

The work should be treated as implementation coverage of this original phase, not as a reason to renumber or redefine the roadmap.

### Phase 3 — Guides, Opportunities & Matching

**Status: Not yet implemented as a dedicated product capability**

Expand Valid.guide beyond validation results into actionable guidance and discovery: structured recommendations, improvement opportunities, matching validated products to relevant audiences/use cases, and the product experiences that make evaluation results useful after the report is delivered.

Existing findings, reports and recommendations are foundations for this phase, but they do not by themselves constitute the complete Guides, Opportunities & Matching product.

### Phase 4 — Experts, Marketplace & Community

**Status: Partially implemented; dedicated marketplace/community scope remains**

Build the broader expert ecosystem around Valid.guide: Auditor Board growth, expert profiles, discovery, community participation, expert opportunities and marketplace capabilities while preserving independence and avoiding pay-to-play incentives.

The repository already contains Auditor onboarding, eligibility, COI, assignment and operational workflows. Those are foundations for this phase, not evidence that the marketplace/community phase is complete.

### Phase 5 — Monitor, Subscription & Public Product

**Status: Partially implemented; major product scope remains**

Build the public-facing product around ongoing trust and user value: monitoring, subscriptions, public discovery, verification, directory experiences, public website surfaces and lifecycle notifications.

The repository already contains the persisted Public Verification Record and the public verification experience, plus role-based notifications and action queues. Public verification therefore does not need to be rebuilt. Monitoring, subscription products and the broader public product remain to be completed.

### Phase 6 — Optimization & Scale

**Status: Not started**

Prepare Valid.guide for sustained operation and scale: calibration, quality measurement, operational optimization, automation, analytics, performance, reliability, observability, security hardening and product iteration based on real-world usage.

This phase begins only after the core public product and operating model are sufficiently mature.

## Current Reconciliation

The current repository should be understood against the roadmap above as follows:

| Original phase | Current status | Reconciliation |
| --- | --- | --- |
| **Phase 0 — Product & Architecture** | **Complete** | Architecture and product decisions are established and documented. |
| **Phase 1 — Application Foundation & Domain Implementation** | **Complete** | Foundation, domain integrity, tenancy, authorization, methodology and trust persistence are implemented and audited. |
| **Phase 2 — Evaluator & Action Plan** | **Substantially implemented** | Current former Phase 2 and Phase 3 work covers much of the evaluation engine and operational workflow. A dedicated original-phase completion audit is still appropriate before declaring the whole product capability complete. |
| **Phase 3 — Guides, Opportunities & Matching** | **Not started as a dedicated phase** | Findings/reports provide groundwork, but the broader guidance, opportunities and matching product is not yet a distinct implemented capability. |
| **Phase 4 — Experts, Marketplace & Community** | **Partially implemented** | Auditor infrastructure exists; marketplace and community capabilities remain. |
| **Phase 5 — Monitor, Subscription & Public Product** | **Partially implemented** | Public verification and notifications exist; monitoring, subscriptions and the complete public product remain. |
| **Phase 6 — Optimization & Scale** | **Not started** | Reserved for post-core-product optimization and operational scale. |

## Existing Implementation Milestones

The technical implementation history should remain visible because it explains how the original roadmap has been executed:

- **Phase 1 completion:** issue **#33** closed after final specification, invariant and CI audit.
- **Former technical Phase 2 completion:** issue **#50** closed after the creator intake, commerce, payment, refund and readiness audit.
- **Public verification:** issue **#57** implemented the public verification experience on top of the persisted trust snapshot.
- **Current Auditor/governance implementation:** issues **#92–#100** established and audited the main Auditor, Creator post-evaluation and Platform Administrator workflow surfaces.

These implementation milestones are subordinate to the original product roadmap. They should not be interpreted as a new phase numbering system.

## Authoritative Specifications

- `docs/domain-and-database-specification.md` — authoritative domain model, persistence model, lifecycle rules, authorization boundaries, trust model, commerce rules and public verification architecture.
- `docs/validation-methodology-v1.md` — authoritative Validation Methodology v1.0, including dimensions, criteria, scoring, blockers, Auditor rules, complexity and decision logic.
- `docs/phase-1-decisions.md` — implementation-significant decisions made while completing Phase 1.
- `docs/implementation-decisions.md` — implementation decisions and intentional deviations recorded during development.

## Development Rules

1. Important architectural, product, methodology, workflow, policy, security and data decisions must be documented.
2. Do not silently contradict an approved specification.
3. Preserve historical truth. Completed Auditor work, standards, decisions, reports and Validation records must remain interpretable at their defined boundaries.
4. Authorization must be enforced server-side. UI visibility is never the security boundary.
5. Trust-sensitive mutations must use controlled application/domain workflows rather than raw or bulk database writes.
6. Every implementation issue must account for Pint/lint, PHPStan and relevant automated tests.
7. CI must be green before an implementation issue is considered complete.

## Current Direction

The repository has completed the foundational application work and the major Auditor/governance workflow implementation. The roadmap now returns to the original product sequence rather than creating another parallel phase numbering system.

The next major product capability is therefore **Phase 3 — Guides, Opportunities & Matching**, while unfinished Phase 4 and Phase 5 capabilities should be tracked explicitly as dependencies or follow-on work rather than being silently absorbed into a renamed roadmap.
