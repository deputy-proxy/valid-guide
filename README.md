# Valid.guide

## Application Plan

> **Status:** Phase 1 in progress · Domain foundation implemented
> **Product:** Valid.guide Validation
> **Stack:** Laravel 13, Filament 5, Livewire 4, Flux 2, Tailwind CSS 4
>
> This document is the high-level application blueprint and implementation roadmap. Important architectural and methodology decisions are recorded in the documents referenced below. Those documents are authoritative for their respective subjects.

### Phase status

- **Phase 0 — Product, domain, methodology and architecture:** Complete
- **Phase 1 — Application foundation and domain implementation:** In progress

### Authoritative specifications

- [`docs/domain-and-database-specification.md`](docs/domain-and-database-specification.md) — authoritative domain model, lifecycle rules, authorization boundaries, trust model, commerce rules and public verification architecture.
- [`docs/validation-methodology-v1.md`](docs/validation-methodology-v1.md) — authoritative Validation Methodology v1.0, including dimensions, criteria, scoring, blockers, Auditor rules, complexity and decision logic.

### Decision-recording rule

Important decisions made during development must be reflected in the relevant repository documentation. The README remains the high-level project record; detailed decisions belong in the appropriate specification document. Documentation must be updated when an architectural, product, methodology, workflow, policy or other implementation-significant decision changes.

---

## 1. Product Definition

Valid.guide is an independent validation service for courses, guides, and other online learning products.

A creator or publisher submits a learning product for evaluation. Valid.guide evaluates it against a transparent quality standard using qualified Auditors and evidence. The creator receives a detailed evaluation report. If the product meets the defined standard, Valid.guide awards a public validation badge and publishes an appropriate public record.

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

The application must encode these principles in both UX and data architecture.

1. **Independence** — Auditors must be separated from the commercial transaction as far as practical.
2. **Evidence** — findings should be traceable to submitted material, observed product content, testing, or documented Auditor judgement.
3. **Transparency** — the evaluation standard and meaning of the badge must be understandable.
4. **Fairness** — creators must have a predictable process and an opportunity to provide relevant evidence or clarify factual errors.
5. **Usefulness** — the report should identify both strengths and weaknesses and give actionable findings.
6. **No guaranteed pass** — the system must never imply that purchasing an evaluation buys Validation.
7. **Auditability** — important decisions and changes must leave an immutable or append-only audit trail.
8. **Separation of concerns** — commercial administration, Auditor work, evaluation methodology, and public presentation should be distinct domains.

---

## 3. Primary Actors

### 3.1 Creator

The person or organization submitting a product for evaluation.

Capabilities include managing products, requesting evaluations, providing access/materials, tracking progress, receiving reports, responding to factual clarification requests, viewing Validation/badge information, and managing billing.

### 3.2 Auditor

An independent subject-matter expert who performs evaluation work.

Public terminology is **Auditor**. Reviewer is not used as the product-facing role name.

Auditor eligibility requires relevant topic expertise and relevant teaching, training, or creator experience, plus the ability to evaluate the product format, methodology literacy, and appropriate language competence. Credentials are useful evidence but are not universally mandatory. Direct competitors are potential conflicts requiring disclosure and administrative determination rather than automatic disqualification. An Auditor may not evaluate a product they previously participated in.

The Auditor Board is the public program for established, qualified contributors who participate in evaluations and methodology improvement and are compensated independently of evaluation outcomes.

Auditor identity, profile, qualifications and criterion attribution may be publicly shown when the Auditor opts in during onboarding.

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

### Product & Intake

- Product — the enduring learning product.
- **Product Release** — the exact evaluated edition/version/state of a product. Validation attaches to a release, not vaguely to a product that may change underneath it.
- Evaluation Request — the commercial/intake request; it buys an evaluation, never a result.
- Submitted Material / Access

### Methodology

- Evaluation Standard
- Standard Version — versioned, approved and frozen for an evaluation once evaluation starts.
- Criterion
- Guidance / applicability rules

### Evaluation Operations

- Evaluation
- Auditor Assignment
- Conflict of Interest
- Auditor Evaluation — each Auditor's submitted work is immutable after submission/lock.
- Criterion Result
- Evidence
- Finding
- Vote

Multiple Auditors are assigned according to complexity and the number is always odd. Evaluations are independent before comparison. Certain criteria may be decided by simple-majority vote where the methodology requires it. There is no lead Auditor.

### Decision & Trust

- Evaluation Decision — the final internal decision, separate from Auditor work.
- Validation — the derived public trust state.
- Badge
- Public Verification Record
- Report / Report Version
- Clarification
- Formal Dispute / Appeal

A Validation is awarded only when the applicable methodology requirements are satisfied. A public score is secondary to the Validation status and is never presented as a guarantee or ranking.

### Commerce

- Order
- Payment
- Refund
- Auditor Compensation
- Payout

Pricing and complexity are fixed before payment. The commercial policy provides a 100% refund before evaluation starts. Once substantive evaluation work starts, the evaluation cannot be cancelled for a refund and failure to validate does not create a refund entitlement. Auditor compensation is fixed, outcome-independent, and paid by Valid.guide.

### Platform

- Notification
- Audit Log
- Public directory projection

---

## 5. Core Lifecycle Rules

The workflow is explicit and state-driven. Important transitions require an authorized actor, validation rules, timestamps, audit entries and appropriate notifications.

```text
Evaluation Request
  ↓
Intake
  ↓
Standard Version frozen
  ↓
Auditor Assignment + COI clearance
  ↓
Evaluation In Progress
  ↓
Auditor submission(s)
  ↓
Internal review / vote where applicable
  ↓
Evaluation Decision
  ├── Validated → Publication → Validation + Badge Active
  └── Not Validated → Report Delivered
```

Clarifications and formal disputes are controlled workflows and do not mutate historical Auditor work or silently rewrite historical decisions.

### Validation lifecycle

A badge **never expires automatically**.

```text
Active
  ├── Material change detected → Suspended / review
  ├── Validity permanently compromised → Revoked
  └── Product superseded by a later validated release → Superseded
```

Material product changes include edition/version/publish date changes and material changes to content, design, scope, audience, outcomes, identity, or relevant quantitative release metadata. Pricing, instructor changes and platform migration do not automatically require revalidation unless another material-change rule is triggered.

A creator must notify Valid.guide of material changes. An older release may retain its historical Validation if it remains available in parallel and is clearly distinguished.

Failed Validation is private by default. A creator may choose to publish it. Public status distinguishes not validated, withdrawn, suspended, revoked and superseded states where applicable. Attempts are retained historically and the attempt count may be public.

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

The standard is versioned. The latest effective Standard Version is automatically assigned when an evaluation starts and is then frozen. In-progress evaluations never migrate to a later standard. Historical standard versions remain publicly accessible.

---

## 7. Public Trust Model

The **Verification Page is authoritative**. The badge itself is only a trust signal pointing to that page.

Every validated release receives a unique, non-guessable verification identifier. The public record contains, at minimum:

- product/release identity
- creator/publisher
- product type
- Validation status
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
- optional full report if the creator enables it

All validated products are listed in the public directory by default. A creator may opt out of directory listing while retaining a verifiable badge. Recommended discovery filters are product type, subject/topic, audience level, Validation status, Validation date and language. Popularity, sales and star ratings are not quality signals in the directory.

The public abstract is generated from the Valid.guide report, not supplied as creator marketing copy.

---

## 8. Independence & Anti-Pay-to-Play Architecture

This is the application's most important non-functional requirement.

- Payment creates an Evaluation Request, never a Validation result.
- Complexity and price are fixed before payment.
- The creator cannot choose the Auditor.
- COI checks are mandatory.
- Standard Version is locked at evaluation start.
- Auditor work is immutable after submission/lock.
- Evaluation Decision is a separate authorized step.
- Administrator overrides are auditable and do not rewrite Auditor records.
- Badge creation requires a validated Evaluation.
- Published Validation cannot be edited by the creator.
- Factual corrections use controlled clarification/dispute workflows.
- Important changes are audited.

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

The creator-facing application is a separate experience from the internal operations interface, even if both are implemented in the same Laravel application.

The dashboard covers products, evaluation requests, active evaluations, completed evaluations, Validation status, invoices/payment status and actions requiring attention.

Evaluation intake is wizard-style:

1. Product
2. Scope
3. Access/materials
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
- Findings
- Reports

### Methodology

- Standards
- Standard Versions
- Criteria
- Guidance

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
- Audit Log

### Commerce

- Orders / Payments
- Refunds
- Invoices
- Auditor Compensation / Payouts

### System

- Notifications
- Settings
- Roles / Permissions

Not every model needs a generic CRUD resource. Workflow-specific pages should be used where uncontrolled editing would violate domain rules.

---

## 12. Authorization & Security

Authorization must be policy-driven and tenant-aware.

Creator data is scoped by Organization. Organization roles are owner, admin, editor and billing, with least-privilege access.

Auditors access only evaluations to which they are assigned and cleared. Internal evidence and deliberation are private by default.

Administrators have broader operational access, but sensitive transitions remain explicit and auditable.

Do not rely on hidden UI controls as the security boundary. Authorization must be enforced server-side.

---

## 13. Reports, Clarifications & Disputes

Reports are versioned. The creator receives the full report. The public receives the controlled public record and Valid.guide-generated abstract; the creator may enable publication of the full report.

Creators may request factual clarification. Formal disputes/appeals are limited to procedural error, material factual error, conflict of interest, or demonstrably flawed methodology application. They are not negotiations over professional judgement.

Dispute reviewers must be independent of the original evaluation. If the original process is found materially flawed, a new Evaluation may be created. The original historical record remains immutable.

---

## 14. Commerce

The creator purchases an evaluation, not a result.

The final commercial implementation must preserve:

- complexity-based pricing
- price fixed before payment
- 100% refund before evaluation starts
- no refund after evaluation starts or because of a failed result
- re-evaluation for a new edition/version or materially changed release
- fixed Auditor compensation
- outcome-independent Auditor compensation
- no compensation for missed submission deadlines where the compensation policy specifies this
- separate compensation for revision work where applicable
- Stripe for creator payments, with a suitable payout abstraction for Auditor compensation

---

## 15. Implementation Roadmap

### Phase 0 — Product & Architecture

**Status: Complete.**

Completed:

- Product repositioning and business model
- Core promise and anti-pay-to-play principle
- Actor definitions and terminology
- Domain model and bounded contexts
- Product Release architecture
- Evaluation lifecycle and trust lifecycle
- Auditor eligibility and COI rules
- Multi-Auditor and voting rules
- Validation decision logic
- Product change/revalidation policy
- Badge and public verification architecture
- Report transparency model
- Clarification and dispute/appeal model
- Commerce/refund/compensation rules
- Authorization/tenant boundaries
- Standard versioning/governance
- Validation Methodology v1.0

Authoritative outputs:

- `docs/domain-and-database-specification.md`
- `docs/validation-methodology-v1.md`

### Phase 1 — Application Foundation & Domain Implementation

**Status: In progress.**

Implemented:

- domain enums and persistence for organization roles, product types, release status, standard versions, evaluation requests, evaluations and Validation
- organization tenancy and authorization policies
- explicit state-transition services with audit logging
- platform-admin separation for high-trust operations
- immutable Auditor submissions, criterion results and votes
- multi-Auditor criterion aggregation
- Evaluation Decision gates and Validation issuance
- Badge and Public Verification Record persistence/publication
- versioned immutable Reports
- Clarification and Formal Dispute workflows
- independent dispute reviewers and process-flaw re-evaluation path
- Evaluation Request one-to-many Evaluation relationship to preserve historical re-evaluations

Remaining Phase 1 work:

- reconcile the implementation with the full domain/database specification
- complete methodology guidance/applicability persistence and validation
- harden domain invariants against all mutation paths
- complete remaining lifecycle entities and relationships
- expand authorization, trust, lifecycle and methodology test coverage
- resolve the current CI/style and dependency-lock issues

### Phase 2 — Creator Intake & Commerce

- creator organization/product management
- Product Releases
- evaluation request wizard
- complexity/pricing
- Stripe payment flow
- refund handling
- intake workflow

### Phase 3 — Auditor Operations

- Auditor onboarding
- expertise and eligibility
- COI declarations
- assignment workflow
- evaluation workspace
- evidence/findings
- multi-Auditor evaluation and voting
- compensation/payouts

### Phase 4 — Decision, Reports & Trust

- Evaluation Decision workflow
- report generation/versioning
- clarification/dispute workflows
- Validation lifecycle
- badges
- verification pages
- public directory

### Phase 5 — Public Product & Launch Readiness

- public website
- creator-facing UX refinement
- accessibility
- security hardening
- observability
- calibration with real representative evaluations
- methodology/QC review
- launch readiness

---

## 16. Development Rules

1. Keep domain rules in explicit services/policies rather than relying on UI behavior.
2. Treat historical trust and evaluation records as immutable wherever specified.
3. Record important state changes and administrative actions in the audit log.
4. Never introduce a commercial mechanism that can reward a positive evaluation outcome.
5. Update the relevant documentation whenever an important product, architecture, methodology, workflow or security decision changes.
6. Prefer explicit domain workflows over generic CRUD when direct editing could violate an invariant.


## Static Analysis

Phase 1 development uses PHPStan as a CI quality gate. Eloquent models explicitly document relationship generics, factory generics, enum/date casts and relevant collection/value shapes so domain services receive concrete model types rather than generic `Model`/`Collection` unions. Single-record workflows use explicit single-record query operations where appropriate.

PHPStan failures are treated as implementation defects rather than suppressed globally. Temporary diagnostic and repair workflows may be used during development, but they are removed before a repair branch is considered complete.
