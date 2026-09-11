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

Public verification information is produced from an explicit public record, not reconstructed from mutable internal operational data.

## Independence & Anti-Pay-to-Play Architecture

The system must make the following properties enforceable:

- payment creates an Evaluation Request, never a result;
- commercial terms are fixed before payment;
- creators cannot choose their Auditor;
- annual and assignment-level conflict checks are mandatory;
- prior participation in the product is a conflict;
- direct competitors require disclosure and administrative determination;
- the Standard Version is locked when evaluation starts;
- Auditor work becomes immutable at its defined submission boundary;
- Evaluation Decision is a separate authorized step;
- administrative overrides are auditable and do not rewrite Auditor records;
- Badge and public verification follow Validation state;
- published Validation is not creator-editable;
- corrections use clarification/dispute workflows;
- compensation is independent of evaluation outcome.

## Public Website

The public website will provide:

- Home
- How Validation Works
- Evaluation Standard
- For Creators
- For Buyers / Learners
- Auditors / Auditor Board
- Validated Products
- Individual Validation page
- Verify a Badge
- About / Independence
- Pricing
- FAQ
- Contact
- Legal

## Creator Application

Creators will have a dedicated application experience, even if it is implemented within the same Laravel application.

The dashboard will provide access to:

- organizations;
- products;
- product releases;
- Evaluation Requests;
- active and completed evaluations;
- Validation status;
- invoices and payments;
- reports and permitted findings;
- badge assets;
- permitted actions.

The Evaluation Request intake is structured as:

1. Product
2. Scope / Product Release
3. Access / Materials
4. Claims / Audience
5. Review
6. Payment handoff

Creators see high-level progress and permitted results, not private Auditor deliberation by default.

## Internal Filament Application

The internal application will provide workflow-oriented interfaces for:

### Operations

- Evaluation Requests
- Evaluations
- Auditor Assignments
- Auditor Evaluations
- Findings
- Reports

### Methodology

- Standards
- Standard Versions
- Criteria
- Guidance
- Applicability / Scoring

### Directory

- Users
- Organizations
- Products
- Releases
- Auditors / Auditor Board

### Trust

- Validations
- Badges
- Public Verification Records
- Conflicts of Interest
- Evaluation Decisions
- Audit Log

### Commerce

- Service Packages
- Orders / Payments
- Refunds
- Invoices
- Compensation / Payouts

### System

- Notifications
- Settings
- Roles / Permissions

Not every model requires generic CRUD. Workflow-specific pages and domain services are preferred where uncontrolled editing could violate business invariants.

## Authorization & Security

Authorization is server-side, tenant-aware and policy-driven.

Organization roles:

- **Owner** — full organization administration
- **Admin** — operational administration within the organization
- **Editor** — product and intake operations
- **Billing** — commercial and billing operations

Platform administration is separate from organization membership.

Auditors can access only evaluations to which they are assigned and cleared. Internal evidence and deliberation remain private.

High-trust operations such as Evaluation Decisions, conflict determinations and Validation state changes require authorized platform administration at the domain-service boundary.

UI restrictions are not considered security boundaries.

## Reports, Clarifications & Disputes

Reports are historical and versioned. A substantive correction creates a new immutable Report Version.

Creators receive the full report. Public disclosure is controlled through the public verification model.

Clarifications are for factual clarification and correction. Formal disputes are limited to matters such as:

- procedural error;
- material factual error;
- conflict-of-interest issues;
- demonstrably flawed application of the methodology.

Disputes are not a negotiation mechanism for changing professional judgement simply because a creator dislikes the result.

Independent review is required where appropriate. If a process is materially flawed, a new Evaluation may be created while preserving the original historical record.

## Commerce

The commercial model is deliberately separated from validation authority.

Creators buy an evaluation, not a result.

The application uses:

- Service Packages;
- complexity-based pricing;
- deterministic quotes;
- frozen commercial snapshots;
- Order and Payment records;
- explicit Refund records;
- outcome-independent Auditor compensation;
- immutable compensation and payout history.

The refund boundary is based on report delivery. A paid evaluation may receive the promised refund before the defined report-delivery boundary; after report delivery, the refund entitlement ends according to the approved policy.

External payment providers such as Stripe are integrations, not sources of truth for Valid.guide's domain lifecycle.

## Application Development Roadmap

The phases below describe the **technical construction of the application**. They deliberately do not mirror the business lifecycle. A feature may participate in several business workflows while being implemented in a different development phase.

### Phase 0 — Product Definition & Architecture

**Status: Complete**

Define what Valid.guide is and establish the architecture before implementation.

This phase establishes the product positioning, business model, actors, bounded contexts, persistence architecture, methodology, trust model, commerce model, authorization model and critical business invariants.

**Exit criterion:** the product and architecture are defined sufficiently that implementation does not need to invent core business rules in code.

### Phase 1 — Application Foundation & Domain Integrity

**Status: Complete**

Build the technical foundation and make the domain trustworthy before substantial user workflows are layered on top.

Focus areas:

- Laravel structure and conventions;
- enums and value representations;
- migrations, foreign keys, indexes and database constraints;
- Eloquent models and relationships;
- tenancy and authentication;
- platform-admin separation;
- domain/state-transition services;
- audit logging;
- immutable and historical boundaries;
- methodology persistence and governance;
- Product, Product Release, Evaluation Request and Evaluation foundations;
- Auditor profiles, eligibility and conflicts;
- Evaluation Decisions and Validation;
- Badge and Public Verification foundations;
- Report versioning;
- Clarification and Formal Dispute foundations;
- commerce ledger foundations;
- concurrency controls;
- negative-path and authorization tests;
- lint, PHPStan and CI quality gates.

**Exit criterion:** the architecture is faithfully represented in code and persistence, critical invariants are controlled, regression coverage exists, documentation is synchronized and CI is green.

### Phase 2 — Core Application Workflows

**Status: Complete**

Turn the stable domain foundation into complete end-to-end application workflows.

Focus areas:

- organization context;
- Product management;
- Product Releases;
- Evaluation Request lifecycle;
- package, complexity and quote handling;
- intake;
- payment handoff;
- material submission;
- evaluation creation;
- Standard Version freezing;
- Auditor assignment and conflict clearance;
- Auditor workspace foundations;
- evidence and findings foundations;
- criterion assessment and voting;
- Evaluation Decision;
- report generation and versioning;
- Validation issuance and lifecycle;
- creator dashboard application contract;
- Phase 2 invariant regression audit.

**Exit criterion:** a complete Phase 2 workflow can move through the application without manual database intervention, trust-sensitive boundaries are covered by regression tests, and the Phase 2 audit is green.

### Phase 3 — Role-Based Application Experiences

**Status: Planned**

Build polished role-specific interfaces on top of the stable workflows.

Focus areas:

- creator dashboard and intake experience;
- product and release management;
- payment and request experience;
- reports and Validation results;
- Auditor onboarding and profile;
- Auditor assignment and evaluation workspace;
- administrator operations and governance interfaces;
- notifications and action queues.

UI must consume domain services and authorization policies rather than duplicate business rules.

**Exit criterion:** each primary actor can perform permitted actions through a coherent application experience without internal data leakage.

### Phase 4 — Public Trust & Discovery

**Status: Planned**

Expose the Validation system as a trustworthy public layer.

Focus areas:

- public website;
- methodology pages;
- creator-facing marketing pages;
- Auditor Board;
- public Validation pages;
- canonical Verification Page;
- badge embedding;
- verification identifier lookup;
- status history;
- report abstract and controlled full-report publication;
- public directory;
- discovery filters;
- structured metadata and SEO;
- public privacy controls.

**Exit criterion:** a third party can independently verify what was evaluated, against which Standard Version, for which Product Release and what the current Validation status is.

### Phase 5 — Integrations, Automation & Operations

**Status: Planned**

Connect external systems and automate repetitive work without moving domain authority into integrations.

Focus areas:

- Stripe;
- refunds and payouts;
- email and notifications;
- scheduled reminders;
- background jobs and queues;
- storage and signed access;
- monitoring and alerts;
- analytics;
- webhook idempotency;
- retry and failure handling.

External providers may trigger or report events but must not bypass domain services or become the source of trust authority.

**Exit criterion:** integrations are reliable, idempotent, observable and recoverable without corrupting domain or trust data.

### Phase 6 — Production Readiness & Launch

**Status: Planned**

Prepare the complete application for safe production operation.

Focus areas:

- production infrastructure;
- deployment pipeline;
- secrets management;
- database and storage backup/recovery;
- monitoring and error reporting;
- performance;
- security testing;
- privacy and retention;
- accessibility;
- browser and device compatibility;
- queue and webhook failure recovery;
- migration/recovery procedures;
- rate limiting and abuse protection;
- operational runbooks;
- incident response;
- smoke tests;
- end-to-end regression;
- launch checklist.

**Exit criterion:** the application can be deployed, operated, monitored, recovered and supported without undocumented manual intervention.

## Development Rules

### Domain authority

Business-critical state changes belong in controlled domain/application services and workflows.

### Historical integrity

Historical meaning is preserved through immutable records, snapshots and versioning.

### Authorization

Authorization is enforced server-side and is always tenant-aware where applicable.

### Database integrity

Foreign keys, uniqueness, indexes, nullability and other database constraints should enforce invariants wherever practical.

### Concurrency

Concurrent state changes must use transactions and appropriate locking or equivalent concurrency controls.

### Testing

Tests should cover normal behavior, invalid behavior, authorization failures, bypass attempts and important concurrency scenarios.

### Static quality

Every implementation must satisfy the repository's lint/formatting rules, PHPStan configuration and relevant automated tests before being considered complete.

### Documentation

When implementation changes an architectural or business decision, the authoritative documentation must be updated at the same time.

### No uncontrolled CRUD

Trust-sensitive aggregates must not become generic editable records merely because Filament can generate a form for them.

## Definition of Done

A development item is complete only when:

1. the intended behavior is implemented;
2. approved architecture and business decisions are respected;
3. authorization is enforced server-side;
4. historical and trust boundaries are preserved;
5. invalid and bypass paths have appropriate tests;
6. database constraints and relationships are correct;
7. relevant documentation is updated;
8. lint/formatting passes;
9. PHPStan passes;
10. relevant tests pass;
11. CI is green.

## Current Development State

```text
Phase 0 — Product Definition & Architecture                 COMPLETE
                │
                ▼
Phase 1 — Application Foundation & Domain Integrity          COMPLETE
                │
                ▼
Phase 2 — Core Application Workflows                         COMPLETE
                │
                ▼
Phase 3 — Role-Based Application Experiences                 PLANNED
                │
                ▼
Phase 4 — Public Trust & Discovery                           PLANNED
                │
                ▼
Phase 5 — Integrations, Automation & Operations              PLANNED
                │
                ▼
Phase 6 — Production Readiness & Launch                      PLANNED
```

Phase 2 is complete only because its implementation work and final invariant audit have both passed their defined exit criteria. The next development work is Phase 3 role-based application experiences, beginning with the presentation issues that build on the completed Phase 2 contracts.

## Documentation

The authoritative project documentation is organized as follows:

- `docs/domain-and-database-specification.md` — domain model and persistence specification
- `docs/validation-methodology-v1.md` — Validation Methodology v1
- `docs/phase-1-decisions.md` — approved Phase 1 decisions and invariants
- `docs/phase-1-invariant-audit.md` — Phase 1 specification-to-code regression matrix
- `docs/phase-2-invariant-audit.md` — Phase 2 specification-to-code regression matrix and completion audit
- `docs/phase-2-ui-regression-audit.md` — Phase 2 UI integration, accessibility and security regression audit
- `docs/implementation-decisions.md` — implementation-level architectural decisions and intentional deviations

The README provides the high-level product and development roadmap. Detailed rules belong in the appropriate specification or decision document.
