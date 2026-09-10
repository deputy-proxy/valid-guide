# Valid.guide

## Application Plan

> **Status:** Phase 1 in progress · Domain foundation implemented
> **Product:** Valid.guide Validation
> **Stack:** Laravel 13, Filament 5, Livewire 4, Flux 2, Tailwind CSS 4
>
> This document is the high-level application blueprint and development roadmap. It describes **how the application is being built**, not the order in which the business operates. The business rules themselves are defined in the authoritative specifications below.

### Development phase status

- **Phase 0 — Product Definition & Architecture:** Complete
- **Phase 1 — Application Foundation & Domain Integrity:** In progress
- **Phase 2 — Core Application Workflows:** Planned
- **Phase 3 — Role-Based Application Experiences:** Planned
- **Phase 4 — Public Trust & Discovery:** Planned
- **Phase 5 — Integrations, Automation & Operations:** Planned
- **Phase 6 — Production Readiness & Launch:** Planned

### Authoritative specifications

- [`docs/domain-and-database-specification.md`](docs/domain-and-database-specification.md) — authoritative domain model, persistence model, lifecycle rules, authorization boundaries, trust model, commerce rules and public verification architecture.
- [`docs/validation-methodology-v1.md`](docs/validation-methodology-v1.md) — authoritative Validation Methodology v1.0, including dimensions, criteria, scoring, blockers, Auditor rules, complexity and decision logic.
- [`docs/phase-1-decisions.md`](docs/phase-1-decisions.md) — implementation-significant decisions made while completing Phase 1.

### Decision-recording rule

Important decisions made during development must be reflected in the relevant repository documentation. The README remains the high-level project record; detailed decisions belong in the appropriate specification or decision document. Documentation must be updated whenever an architectural, product, methodology, workflow, policy, security, data or implementation-significant decision changes.

---

## 1. Product Definition

Valid.guide is an independent validation service for courses, guides, and other online learning products.

A creator or publisher submits a learning product for evaluation. Valid.guide evaluates it against a transparent quality standard using qualified Auditors and evidence. The creator receives a detailed evaluation report. If the product meets the defined standard, Valid.guide awards a public validation badge and publishes an appropriate public verification record.

### The fundamental promise

**Turn the quality of an online learning product into a credible, transparent signal that buyers can understand and creators can use to build trust.**

### What Valid.guide is not

- Not a generic review marketplace.
- Not a star-rating site.
- Not an affiliate catalogue whose incentives depend on recommending products.
- Not a pay-for-positive-review service.
- Not a guarantee that a learner will achieve a particular outcome.
- Not a substitute for a buyer's own judgement.

The creator pays for an **evaluation**, not for a positive result. Payment must never influence the evaluation outcome.

---

## 2. Product Principles

The application must encode these principles in UX, domain logic, persistence and security.

1. **Independence** — Auditors must be separated from the commercial transaction as far as practical.
2. **Evidence** — findings should be traceable to submitted material, observed product content, testing, or documented Auditor judgement.
3. **Transparency** — the evaluation standard and meaning of the badge must be understandable.
4. **Fairness** — creators must have a predictable process and an opportunity to provide relevant evidence or clarify factual errors.
5. **Usefulness** — reports should identify strengths and weaknesses and provide actionable findings.
6. **No guaranteed pass** — the system must never imply that purchasing an evaluation buys Validation.
7. **Auditability** — important decisions and changes must leave an immutable or append-only audit trail.
8. **Historical integrity** — published methodology, product releases, Auditor work, decisions, reports and Validation states must remain interpretable over time.
9. **Least privilege** — authorization must be enforced server-side and must not depend on UI visibility.
10. **Separation of concerns** — commercial administration, evaluation work, methodology, decision-making, publication and public presentation remain distinct domains.

---

## 3. Primary Actors

### 3.1 Creator

The person or organization submitting a product for evaluation.

Capabilities include managing products and releases, requesting evaluations, providing access/materials, tracking progress, receiving reports, responding to factual clarification requests, viewing Validation/badge information, and managing billing.

### 3.2 Auditor

An independent subject-matter expert who performs evaluation work.

Public terminology is **Auditor**. Reviewer is not used as the product-facing role name.

Auditor eligibility requires relevant topic expertise and relevant teaching, training, research, application or creator experience, plus the ability to evaluate the product format, methodology literacy, appropriate language competence and current conflict-of-interest clearance. Credentials are useful evidence but are not universally mandatory. Direct competitors are potential conflicts requiring disclosure and administrative determination rather than automatic disqualification. An Auditor may not evaluate a product they previously participated in.

The Auditor Board is the public program for established, qualified contributors who participate in evaluations and methodology improvement and are compensated independently of evaluation outcomes.

Auditor identity, profile, qualifications and criterion attribution may be publicly shown when the Auditor opts in.

### 3.3 Valid.guide Administrator

Internal staff operating the service. Administrators manage users, organizations, products, intake, standards, Auditor assignments, conflicts, evaluations, decisions, publication, badges, commerce, compensation, reports, disputes and audit logs.

An Administrator may override or amend the **final Evaluation Decision**, with an auditable reason, but must never silently rewrite immutable Auditor work.

### 3.4 Buyer / Learner

Primarily a consumer of public Validation information, not necessarily an authenticated application user in the MVP.

---

## 4. Core Domain Model

The application is built around the following bounded concepts. The complete domain/database specification is authoritative.

### Identity & Access

- User
- Organization
- Organization Membership
- roles: owner, admin, editor, billing
- platform administration role

Organization membership is tenant-scoped. Platform administration is deliberately separate from organization membership.

### Product & Intake

- Product — the enduring learning product.
- **Product Release** — the exact evaluated edition/version/state of a product.
- Evaluation Request — the commercial/intake request; it buys an evaluation, never a result.
- Submitted Material / Access

A Product Release is the historical object against which an Evaluation and Validation are anchored.

### Methodology

- Evaluation Standard
- Standard Version — versioned, approved and frozen for an evaluation once evaluation starts.
- Criterion
- Criterion Guidance
- Applicability rules
- Scoring rules and assessment anchors

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

Auditor work is independent and historical. Multiple Auditors are assigned according to complexity and methodology rules. There is no lead Auditor.

### Decision & Trust

- Evaluation Decision — the final internal decision, separate from Auditor work.
- Validation — the public trust state attached to a specific Evaluation/Product Release.
- Badge
- Public Verification Record
- Report / Report Version
- Clarification Request
- Formal Dispute / Independent Review

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

The architecture intentionally avoids collapsing these concepts into one large `evaluations` table with a heroic collection of status columns. Civilization has suffered enough from that pattern.

---

## 5. Core Business Lifecycle

The business lifecycle is defined here for orientation. **It is not the development roadmap.** The application is built in technical/development phases described in Section 15.

```text
Evaluation Request
  ↓
Intake
  ↓
Commercial terms fixed / payment
  ↓
Materials received and verified
  ↓
Evaluation formally starts
  ↓
Standard Version frozen
  ↓
Auditor Assignment + COI clearance
  ↓
Auditor work
  ↓
Internal review / vote where applicable
  ↓
Evaluation Decision
  ├── Validated → Report / Publication → Validation + Badge
  └── Not Validated → Report → Optional publication
```

Clarifications and formal disputes are controlled workflows. They do not silently mutate historical Auditor work or rewrite completed evaluations.

### Validation lifecycle

A badge **never expires automatically**.

```text
Active
  ├── Material change detected → Suspended / review
  ├── Validity permanently compromised → Revoked
  └── Later validated release replaces it → Superseded
```

Material product changes include edition/version/publish date changes and material changes to content, design, scope, audience, outcomes, identity, or relevant quantitative release metadata. Pricing, instructor changes and platform migration do not automatically require revalidation unless another material-change rule is triggered.

A creator must notify Valid.guide of material changes. An older release may retain its historical Validation if it remains available in parallel and is clearly distinguished.

Failed Validation is private by default. A creator may choose to publish it. Public status distinguishes not validated, withdrawn, suspended, revoked and superseded states where applicable. Attempts are retained historically.

---

## 6. Methodology

Valid.guide uses a common core methodology with product-type modules rather than unrelated standards for each format.

Supported formats include:

- Course
- Cohort course
- Guide
- Ebook learning product
- Workshop
- Program
- Membership
- Other approved learning format

The ten core dimensions are:

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

The complete criteria, weights, assessment states, evidence model, validation gates, blockers, complexity rules and calibration/QC protocol are defined in [`docs/validation-methodology-v1.md`](docs/validation-methodology-v1.md).

The standard is versioned. The effective Standard Version at the formal start of an Evaluation is frozen for that Evaluation. In-progress Evaluations never migrate to a later standard. Historical versions remain available.

---

## 7. Public Trust Model

The **Verification Page is authoritative**. The badge itself is only a trust signal pointing to that page.

Every Validation receives a unique, non-guessable verification identifier. The persisted Public Verification Record contains the public-facing snapshot required to render the verification state without reconstructing it from mutable internal data.

At minimum, the public record can contain:

- product and exact release identity
- creator/publisher
- product type
- current Validation status
- Validation date
- Standard Version
- evaluation scope
- overall result
- criterion-level results/scores where published
- strengths and weaknesses
- number of Auditors
- Auditor identities/credentials when opted in
- verification identifier
- relevant status history and revocation reason
- Valid.guide-generated report abstract
- optional full report where enabled

The public verification record remains resolvable after suspension, revocation or supersession. Its identity is not replaced merely because the trust state changes.

All validated products are listed in the public directory by default. A creator may opt out of directory listing while retaining a verifiable badge. Discovery filters can include product type, subject/topic, audience level, Validation status, Validation date and language. Popularity, sales and star ratings are not quality signals.

The public abstract is generated from the Valid.guide report, not supplied as creator marketing copy.

---

## 8. Independence & Anti-Pay-to-Play Architecture

This is the application's most important non-functional requirement.

- Payment creates an Evaluation Request, never a Validation result.
- Commercial terms are frozen before the payment boundary.
- The creator cannot choose the Auditor.
- Annual and assignment-specific COI checks are mandatory.
- Prior participation in the product is a conflict condition.
- Standard Version is locked at evaluation start.
- Auditor work is immutable after submission/lock.
- Evaluation Decision is a separate authorized step.
- Administrator overrides are auditable and do not rewrite Auditor records.
- Badge/public verification creation follows the Validation state.
- Published Validation cannot be edited by the creator.
- Factual corrections use controlled clarification/dispute workflows.
- Important changes are audited.
- Auditor compensation is independent of whether the product validates.

The architecture intentionally avoids unnecessary bureaucracy. Higher-risk or disputed evaluations can use additional independent Auditors according to complexity and methodology rules.

---

## 9. Public Website

Core pages:

- Home
- How Validation Works
- Evaluation Standard
- For Creators
- For Buyers/Learners
- Auditors / Auditor Board
- Validated Products
- Individual Product Validation page
- Verify a Badge
- About / Independence
- Pricing
- FAQ
- Contact
- Legal pages

Each validated product has a canonical public page. The page states exactly what was evaluated and that Validation does not guarantee an individual learner's results.

---

## 10. Creator Application

The creator-facing application is a dedicated experience from the internal operations interface, even if both are implemented in the same Laravel application.

The dashboard covers organizations, products, Product Releases, evaluation requests, active evaluations, completed evaluations, Validation status, invoices/payment status and actions requiring attention.

Evaluation intake is wizard-style:

1. Product
2. Scope / Product Release
3. Access and materials
4. Claims and audience
5. Review
6. Payment

Creators can track high-level progress and access reports, findings, decisions and badge assets when available. Internal Auditor deliberation is not exposed by default.

---

## 11. Internal Filament Application

Filament is the primary operations back office.

### Operations

- Dashboard
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
- Applicability / scoring configuration

### Directory

- Users
- Organizations
- Products
- Product Releases
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
- Auditor Compensation / Payouts

### System

- Notifications
- Settings
- Roles / Permissions

Not every model needs a generic CRUD resource. Workflow-specific pages and domain services must be used where uncontrolled editing would violate domain rules.

---

## 12. Authorization & Security

Authorization must be policy-driven, tenant-aware and enforced server-side.

Creator data is scoped by Organization. Organization roles are:

- **Owner** — full organization administration.
- **Admin** — operational administration within the organization.
- **Editor** — product and evaluation-intake work within granted boundaries.
- **Billing** — commercial/billing work without access to evaluation content merely by virtue of the role.

Platform administration is separate from organization membership. An organization role must never grant platform authority.

Auditors access only evaluations to which they are assigned and cleared. Internal evidence and deliberation are private by default.

High-trust operations such as Evaluation Decision recording, Conflict determination, Validation issuance and Validation state changes require platform administration at the domain-service boundary.

Historical and trust-sensitive records use controlled write paths. Application workflows must not bypass those boundaries through bulk or raw database mutation.

Do not rely on hidden UI controls as the security boundary.

---

## 13. Reports, Clarifications & Disputes

Reports are historical, versioned records. A Report belongs to one Evaluation and each substantive correction creates a new immutable Report Version. Delivered reports cannot be rewritten in place.

The creator receives the full agreed report. The public receives the controlled public record and Valid.guide-generated abstract; the creator may enable publication of the full report where permitted.

Creators may request factual clarification. Formal disputes are limited to:

- procedural error
- material factual error
- conflict-of-interest concern
- demonstrably flawed methodology application

They are not negotiations over professional judgement or a mechanism for buying a more favorable outcome.

Dispute reviewers must be independent of the original evaluation. If the process is found materially flawed, a new Evaluation may be created for the same Product Release and frozen Standard Version context. The original historical record remains immutable.

---

## 14. Commerce

The creator purchases an evaluation, not a result.

The commercial implementation must preserve:

- service-package and complexity-based pricing
- deterministic quote calculation
- price and commercial terms fixed before payment
- commercial snapshots retained on historical requests
- 100% refund before the agreed refund boundary
- no refund after report delivery
- evaluation cancellation/refund rules defined by the approved domain lifecycle
- re-evaluation for a new edition/version or materially changed release
- fixed Auditor compensation
- outcome-independent Auditor compensation
- deadline-based compensation eligibility
- immutable compensation and payout history
- Stripe for creator payments, with a suitable payout abstraction for Auditor compensation

Commerce state must never become a hidden input into the Evaluation Decision.

---

## 15. Application Development Roadmap

The phases below describe the **technical construction of the application**. They deliberately do not mirror the business lifecycle. A feature may participate in several business workflows while being implemented in a different development phase.

### Phase 0 — Product Definition & Architecture

**Status: Complete.**

Purpose: decide what Valid.guide is and establish the architecture before implementation.

Completed:

- Product positioning and business model
- Core promise and anti-pay-to-play principle
- Actor definitions and terminology
- Bounded contexts and domain model
- Database/persistence architecture
- Product Release architecture
- Evaluation and Validation lifecycle design
- Auditor eligibility and COI rules
- Multi-Auditor and voting rules
- Validation decision logic
- Product change/revalidation policy
- Badge and public verification architecture
- Report transparency model
- Clarification and dispute/appeal model
- Commerce/refund/compensation rules
- Authorization/tenant boundaries
- Standard Version governance
- Validation Methodology v1.0

Authoritative outputs:

- `docs/domain-and-database-specification.md`
- `docs/validation-methodology-v1.md`

Exit criterion:

> The product, architecture, methodology and critical business invariants are sufficiently defined that implementation can proceed without inventing core rules inside application code.

### Phase 1 — Application Foundation & Domain Integrity

**Status: In progress.**

Purpose: build the technical foundation and make the domain model trustworthy before exposing substantial user workflows.

Work includes:

- Laravel application structure and conventions
- domain enums and value representations
- migrations, foreign keys, indexes and database constraints
- Eloquent models and relationships
- organization tenancy and authorization foundations
- platform-admin separation
- domain services and explicit state-transition services
- audit logging foundations
- immutable/historical boundaries
- methodology persistence and governance
- Product/Product Release/Evaluation Request/Evaluation foundations
- Auditor profile, eligibility and COI foundations
- Evaluation Decision and Validation foundations
- Badge and Public Verification Record foundations
- Report and Report Version foundations
- Clarification and Formal Dispute foundations
- commerce ledger foundations
- concurrency/locking where required to preserve invariants
- negative-path, authorization and historical-integrity tests
- Pint/lint, PHPStan and CI quality gates

Current Phase 1 completion issues:

- **#65** — Complete methodology guidance and applicability persistence
- **#66** — Harden trust-domain invariants against raw and bulk mutation
- **#67** — Complete Phase 1 lifecycle entities and domain relationships
- **#68** — Expand Phase 1 authorization, lifecycle and trust regression coverage
- **#69** — Resolve Phase 1 CI, static analysis and dependency-lock baseline
- **#33** — Final Phase 1 specification, invariant and CI audit

Exit criterion:

> The application foundation faithfully represents the approved architecture, all critical invariants are enforced through controlled application paths, regression coverage protects them, documentation is synchronized, and CI is green.

### Phase 2 — Core Application Workflows

**Status: Planned.**

Purpose: turn the stable domain foundation into complete end-to-end application workflows.

Work includes:

- creator organization context
- Product management
- Product Release management
- Evaluation Request creation and lifecycle
- service package selection and deterministic quoting
- creator intake workflow
- payment handoff
- material submission and verification
- evaluation creation from eligible requests
- Standard Version selection/freeze at the correct lifecycle boundary
- Auditor assignment workflow
- annual and assignment-specific COI workflow
- Auditor evaluation workspace
- evidence and finding capture
- criterion assessment and methodology-controlled voting
- Evaluation Decision workflow
- report creation and versioning
- Validation issuance and lifecycle controls

Exit criterion:

> A complete evaluation can move through the application using real domain workflows without requiring manual database intervention.

### Phase 3 — Role-Based Application Experiences

**Status: Planned.**

Purpose: build polished, role-specific interfaces on top of the completed core workflows.

Work includes:

- creator dashboard and navigation
- creator evaluation wizard and draft resumption
- creator product/release experience
- creator payment and request status experience
- creator report and Validation experience
- Auditor onboarding
- Auditor profile and competence management
- Auditor assignment inbox
- Auditor evaluation workspace
- Auditor evidence/finding experience
- Administrator operational dashboards
- methodology administration UI
- conflict/dispute administration UI
- compensation and payout administration UI
- notifications and attention/action queues

The UI must consume the domain services and policies established in earlier phases. It must not become a second implementation of business rules.

Exit criterion:

> Each primary actor can perform the actions permitted by their role through a coherent application experience, with server-side authorization and no accidental exposure of internal data.

### Phase 4 — Public Trust & Discovery

**Status: Planned.**

Purpose: build the public-facing layer that turns internal Validation data into a trustworthy external signal.

Work includes:

- public website
- methodology explanation pages
- creator-facing marketing pages
- Auditor Board pages
- public Validation pages
- canonical Verification Page
- badge rendering/embed experience
- verification identifier lookup
- public status history
- report abstract publication
- optional full-report publication
- public directory
- directory filtering and discovery
- structured metadata/SEO where appropriate
- public disclosure and privacy controls

The persisted Public Verification Record remains the authoritative public projection. Public pages must not reconstruct historical trust state from mutable operational tables.

Exit criterion:

> A third party can independently verify what was evaluated, against which Standard Version, for which Product Release, with what current Validation status, without needing access to internal application data.

### Phase 5 — Integrations, Automation & Operations

**Status: Planned.**

Purpose: connect the application to external systems and automate repetitive operational work without moving domain authority into integrations.

Work includes:

- Stripe payment integration
- refund integration
- payout integration
- email/notification delivery
- transactional communication
- scheduled operational reminders
- background jobs and queues
- document/file storage integration
- temporary signed access to private evidence where required
- monitoring and operational alerts
- audit-log operational tooling
- analytics and product instrumentation
- integration failure/retry handling
- webhook processing and idempotency

External providers may trigger or report events, but they must not bypass domain services or become the authoritative source for Valid.guide trust state.

Exit criterion:

> External integrations are reliable, idempotent, observable and isolated behind application/domain boundaries, with failures recoverable without corrupting historical trust data.

### Phase 6 — Production Readiness & Launch

**Status: Planned.**

Purpose: make the complete application safe, observable and maintainable in production.

Work includes:

- production infrastructure configuration
- deployment pipeline
- environment and secret management
- database backup and recovery strategy
- storage backup/recovery strategy
- monitoring and error reporting
- performance testing and optimization
- security review
- authorization penetration testing
- privacy/data-retention review
- accessibility review
- browser/device compatibility testing
- queue and webhook failure testing
- migration/recovery testing
- rate limiting and abuse protection
- operational runbooks
- incident response procedures
- production smoke tests
- final end-to-end regression suite
- launch checklist

Exit criterion:

> The application can be deployed, operated, monitored, recovered and supported in production without relying on undocumented manual intervention.

---

## 16. Development Rules

The following rules apply throughout every development phase.

### Domain authority

Business-critical state changes belong in domain services and controlled workflows. UI components, Filament resources and HTTP endpoints may initiate those workflows but must not independently reproduce or bypass their rules.

### Historical integrity

If a record represents a historical trust, methodology, evaluation, report or financial fact, prefer immutable records, snapshots or explicit versioning over destructive updates.

### Authorization

Every protected action must be authorized server-side. Tenant boundaries must be enforced even when IDs are supplied manually or when a request bypasses the expected UI.

### Database integrity

Use foreign keys, unique constraints, indexes and appropriate nullability to enforce invariants at the persistence layer wherever practical.

### Concurrency

Operations that can race must use transactions and row locking or equivalent concurrency controls where required. A stale in-memory model must not be allowed to overwrite a newer domain state.

### Testing

New domain behavior must include appropriate unit, feature and negative-path tests. Important invariants should have regression tests proving both the allowed path and prohibited bypasses.

### Static analysis and quality

Before an issue is considered complete:

- Pint/lint must pass.
- PHPStan must pass at the configured level.
- Relevant tests must pass.
- CI must be green.

Do not weaken static analysis, remove tests or suppress errors merely to make a build green.

### Documentation

When implementation changes an architectural or implementation-significant decision, update the relevant documentation in the same development cycle.

### No uncontrolled CRUD

A model does not automatically deserve a generic CRUD interface. If direct editing could violate a lifecycle, trust or historical invariant, expose a workflow instead.

---

## 17. Definition of Done

A development issue is not complete merely because its primary code path works.

The implementation is considered done only when:

1. The requested behavior is implemented.
2. The approved architecture and business decisions are respected.
3. Authorization is enforced server-side.
4. Historical and trust boundaries are preserved.
5. Invalid and bypass paths are tested.
6. Database constraints and relationships are correct.
7. Documentation is updated where necessary.
8. Pint/lint passes.
9. PHPStan passes.
10. Relevant tests pass.
11. CI is green.

For trust-sensitive work, manual confidence is not an acceptance criterion. The repository must provide executable evidence that the invariant holds.

---

## 18. Current Development State

The application has moved beyond architectural discovery and is now in the **Application Foundation & Domain Integrity** stage.

The major architectural decisions are already established. The immediate objective is therefore not to add every visible feature at once, but to finish the foundation cleanly so subsequent workflow and UI work can be built without repeatedly revisiting the domain model.

The current implementation backlog should be read in dependency order:

```text
Phase 0
  ↓
Phase 1 — Foundation & Integrity
  ├── #65 Methodology persistence
  ├── #66 Trust-domain mutation hardening
  ├── #67 Domain entities/relationships
  ├── #68 Regression coverage
  └── #69 CI/static-analysis baseline
          ↓
       #33 Final Phase 1 audit
          ↓
Phase 2 — Core application workflows
          ↓
Phase 3 — Role-based experiences
          ↓
Phase 4 — Public trust & discovery
          ↓
Phase 5 — Integrations & automation
          ↓
Phase 6 — Production readiness & launch
```

This sequencing is intentional: **build the foundation, prove the foundation, build the workflows, build the experiences, expose the trust layer, integrate external systems, then harden for production.**

The application should not advance to the next development phase merely because a feature can technically be demonstrated. The exit criteria for the current phase must be satisfied first.