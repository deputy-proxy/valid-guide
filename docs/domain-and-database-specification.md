# Valid.guide Domain & Database Specification

> Status: Approved architecture baseline
> Date: 2026-09-08
> Product: Valid.guide Validation
> Stack: Laravel 13, Filament 5, Livewire 4, PHP 8.3+

This document translates the business decisions recorded in the project issues into an implementation-ready domain and persistence model.

It deliberately separates **commercial transaction**, **evaluation work**, **decision**, **publication**, and **trust verification**. Those concerns must not collapse into one `evaluations` table with a heroic collection of status columns. Humans have invented enough software like that already.

---

## 1. Architectural principles

1. **Payment buys an evaluation, never a result.**
2. **An evaluation is historical.** It must remain interpretable even after methodology changes.
3. **Methodology is versioned and immutable once an evaluation starts.**
4. **Auditor work is immutable after submission.** Administrative decisions may supersede it but must never silently rewrite it.
5. **Products are enduring entities; product releases/versions are the objects whose exact state is evaluated.**
6. **Validation is a public trust state derived from an evaluation, not a permanent boolean on Product.**
7. **A badge never expires automatically.** It can be revoked when the validated product materially changes or the validation is otherwise invalidated.
8. **Every important state transition is explicit, authorized and auditable.**
9. **Creator data is tenant-scoped by Organization.**
10. **Evidence is private by default.** Public exposure is an explicit publication decision.
11. **Published reports are versioned.** Corrections create new versions rather than mutating history.
12. **The public verification page is authoritative.** The badge embed is only a pointer to it.

---

## 2. Bounded contexts

### Identity & Access

- User
- Organization
- Organization Membership
- Roles / permissions

### Product & Intake

- Product
- Product Release
- Evaluation Request
- Submitted Material / Access

### Methodology

- Evaluation Standard
- Standard Version
- Criterion
- Criterion Guidance
- Product Type applicability

### Evaluation Operations

- Evaluation
- Auditor Assignment
- Conflict Declaration
- Auditor Evaluation
- Criterion Result
- Evidence
- Finding
- Vote

### Decision & Trust

- Evaluation Decision
- Validation
- Validation Badge
- Public Verification Record
- Report
- Report Version
- Clarification / Dispute

### Commerce

- Order
- Payment
- Refund
- Reviewer/Auditor Compensation
- Payout

### Platform

- Notification
- Audit Log
- Public Directory Entry / indexing projection

---

## 3. Core entities

### 3.1 User

Authentication identity shared across all application roles.

Fields:

- `id` ULID
- `name`
- `email`
- `email_verified_at`
- `password`
- `status` (`active`, `suspended`, `disabled`)
- timestamps

A user can be a creator, organization member, Auditor and/or administrator. Authorization determines what they may do.

### 3.2 Organization

The creator/publisher account boundary.

Fields:

- `id` ULID
- `name`
- `slug`
- `description`
- `website_url`
- contact fields as required
- `status`
- timestamps
- soft delete only where compatible with historical records

### 3.3 Organization Membership

Pivot entity, not booleans on User.

Fields:

- `id` ULID
- `organization_id`
- `user_id`
- `role` (`owner`, `admin`, `editor`, `billing`)
- timestamps

Unique constraint: `(organization_id, user_id)`.

### 3.4 Product

The enduring commercial/learning product.

Fields:

- `id` ULID
- `organization_id`
- `product_type_id`
- `title`
- `slug`
- `description`
- `canonical_url`
- `target_audience`
- `claimed_outcomes`
- `language`
- `status`
- timestamps

A Product may have many Product Releases and many Evaluations.

### 3.5 Product Release

The exact product state that can be validated. This is required because the user-defined badge policy is based on material product changes such as edition, version and publication date.

Fields:

- `id` ULID
- `product_id`
- `edition`
- `version`
- `published_at`
- `release_identifier` or canonical version label
- `product_url_snapshot`
- `title_snapshot`
- `quantitative_metadata` JSON
- `material_change_notes`
- `status` (`draft`, `current`, `superseded`, `withdrawn`)
- timestamps

A Product Release is immutable once used by an Evaluation except through controlled corrections that create a new version/history record.

This allows the following legitimate situation:

`Product A / v1` remains available while `Product A / v2` is published.

The v1 validation can remain historically valid if v1 remains a distinct, identifiable product release. The badge and public record must always identify the validated release precisely.

---

## 4. Methodology model

### 4.1 Product Type

A controlled taxonomy supporting at least:

- course
- cohort course
- guide
- ebook learning product
- workshop
- program
- membership
- other approved learning format

Product type is data, not a hard-coded Filament form condition.

### 4.2 Evaluation Standard

Logical methodology family.

Fields:

- `id` ULID
- `name`
- `description`
- `status`

### 4.3 Standard Version

Immutable published version of a methodology.

Fields:

- `id` ULID
- `evaluation_standard_id`
- `version`
- `summary`
- `methodology_document`
- `status` (`draft`, `scheduled`, `effective`, `retired`)
- `effective_at`
- `retired_at`
- `approved_by`
- `approved_at`
- timestamps

Rules:

- Admins create/edit.
- Admins approve.
- A future version may be announced before its effective date.
- Every new evaluation automatically receives the Standard Version that is **in force when the evaluation formally starts**.
- Once an evaluation starts, its Standard Version is immutable.
- An in-progress evaluation never migrates to a newer Standard Version.
- Historical versions remain publicly accessible in full.

### 4.4 Criterion

A criterion belongs to a Standard Version.

Fields:

- `id` ULID
- `standard_version_id`
- `code`
- `name`
- `description`
- `category`
- `sequence`
- `weight` decimal
- `is_mandatory`
- `applicability_rules` JSON
- `scoring_rules` JSON where required
- timestamps

Criteria are version-specific. Editing a criterion after publication is prohibited. A methodology change creates a new Standard Version.

### 4.5 Criterion Guidance

Optional structured methodology instructions associated with a Criterion.

This is where examples, evidence expectations, interpretation guidance and scoring anchors belong. It prevents methodology from leaking into application code.

---

## 5. Evaluation model

### 5.1 Evaluation Request

Commercial/intake object. It is not the evaluation itself.

Fields:

- `id` ULID
- `organization_id`
- `product_id`
- `product_release_id`
- `service_package_id` or service snapshot
- `complexity_level`
- `quoted_amount_minor`
- `currency`
- `status`
- `submitted_at`
- `evaluation_started_at`
- `requested_completion_window`
- `cancelled_at`
- `cancellation_reason`
- timestamps

The complexity level and total price are fixed before payment.

### 5.2 Evaluation

The independent assessment instance.

Fields:

- `id` ULID
- `evaluation_request_id`
- `product_id`
- `product_release_id`
- `standard_version_id`
- `status`
- `started_at`
- `submitted_at`
- `internal_reviewed_at`
- `completed_at`
- `publication_status`
- timestamps

The foreign key to `standard_version_id` is the historical methodology snapshot. Never resolve historical methodology through a "current standard" relationship.

### 5.3 Auditor Assignment

Many-to-many relationship between Evaluation and Auditor/User, represented as an explicit entity.

Fields:

- `id` ULID
- `evaluation_id`
- `auditor_id`
- `sequence`
- `status` (`offered`, `accepted`, `declined`, `cleared`, `disqualified`, `completed`)
- `assigned_at`
- `accepted_at`
- `completed_at`
- `compensation_amount_minor`
- `compensation_currency`
- `compensation_status`
- timestamps

Rules:

- Number of assigned Auditors is determined by evaluation complexity.
- The number must always be odd for an active evaluation.
- Auditors evaluate independently before comparison.
- There is no lead Auditor.
- A criterion may use a vote among involved Auditors.
- Original Auditor results remain immutable.

### 5.4 Conflict Declaration

Fields:

- `id` ULID
- `evaluation_id`
- `auditor_assignment_id`
- `declaration_type`
- `disclosure`
- `outcome` (`no_conflict`, `potential_conflict`, `disqualified`)
- `determined_by`
- `determined_at`
- timestamps

Declarations are required annually and per assignment.

Direct commercial competitors are treated as potential conflicts requiring disclosure and administrative determination, not automatic disqualification.

### 5.5 Auditor Evaluation

A submitted Auditor's complete independent assessment.

Fields:

- `id` ULID
- `evaluation_id`
- `auditor_assignment_id`
- `version`
- `status` (`draft`, `submitted`, `returned`, `locked`)
- `submitted_at`
- `locked_at`
- timestamps

Each submission owns its criterion results, findings and evidence references.

Once submitted/locked, the record is immutable. A correction is represented by a new version, preserving the previous version.

### 5.6 Criterion Result

One Auditor's assessment of one criterion.

Fields:

- `id` ULID
- `auditor_evaluation_id`
- `criterion_id`
- `assessment` (`meets`, `partially_meets`, `does_not_meet`, `insufficient_evidence`, `not_applicable`)
- `score` nullable decimal
- `rationale`
- `confidence` nullable
- `submitted_at`
- timestamps

A unique constraint should prevent duplicate results for the same Auditor Evaluation and Criterion within a version.

### 5.7 Criterion Vote

Used when methodology specifies that a criterion is decided by Auditor vote.

Fields:

- `id` ULID
- `evaluation_id`
- `criterion_id`
- `status`
- `decision`
- `decided_at`
- timestamps

Individual votes reference the immutable Criterion Results rather than replacing them.

Rule: simple majority wins. Minority positions remain visible in the internal historical record.

### 5.8 Evidence

Fields:

- `id` ULID
- `evaluation_id`
- `auditor_evaluation_id` nullable
- `criterion_result_id` nullable
- `finding_id` nullable
- `type`
- `title`
- `description`
- `source_url` nullable
- `storage_path` nullable
- `provenance`
- `captured_at`
- `visibility` (`private`, `creator`, `public`)
- timestamps

Evidence files are stored privately. Access is authorization-controlled and uses temporary signed URLs where appropriate.

### 5.9 Finding

Fields:

- `id` ULID
- `evaluation_id`
- `criterion_id`
- `auditor_evaluation_id` nullable
- `type` (`strength`, `weakness`, `risk`, `recommendation`, `factual_clarification`)
- `severity`
- `title`
- `description`
- `status`
- timestamps

Findings are not directly editable after publication. Corrections create report versions.

---

## 6. Decision and validation

### 6.1 Evaluation Decision

The final authorized internal outcome, distinct from Auditor submissions.

Fields:

- `id` ULID
- `evaluation_id`
- `decision` (`validated`, `not_validated`, `needs_re_evaluation`, `withdrawn`)
- `rationale`
- `decided_by`
- `decided_at`
- `supersedes_decision_id` nullable
- timestamps

Administrative override is implemented here, never by editing an Auditor result.

Every override must identify the actor, reason and evidence basis in the audit trail.

### 6.2 Validation

A public trust state attached to a specific validated Evaluation/Product Release.

Fields:

- `id` ULID
- `evaluation_id`
- `product_id`
- `product_release_id`
- `status` (`active`, `suspended`, `revoked`, `superseded`)
- `issued_at`
- `suspended_at` nullable
- `revoked_at` nullable
- `revocation_reason` nullable
- `superseded_by_validation_id` nullable
- timestamps

There is **no expiry date**.

Suspension is temporary and exists for detected unannounced changes pending review. Revocation is permanent for that validation record.

### 6.3 Validation Badge

The externally embedded trust signal.

Fields:

- `id` ULID
- `validation_id`
- `verification_identifier` unique, non-guessable
- `status`
- `issued_at`
- `embed_version`
- timestamps

The badge's displayed state is derived from the authoritative Validation record. The creator may leave an old embed installed, but the badge must visibly reflect the current status.

### 6.4 Public Verification Record

Canonical public page/record for a validation.

Fields:

- `id` ULID
- `validation_id`
- `public_slug` or verification identifier
- `directory_visible`
- `full_report_visible`
- `published_at`
- timestamps

The verification URL must remain resolvable even after revocation.

Minimum public data:

- product and validated release identity
- creator/publisher
- product type
- current validation status
- validation date
- Standard Version
- overall result
- criterion-level results and scores/ratings
- strengths
- weaknesses
- number of Auditors
- Auditor identities/credentials where opted in
- evaluation scope
- verification identifier
- status history and revocation reason where relevant
- report abstract
- optional full report

---

## 7. Reports, clarification and dispute

### 7.1 Report

Logical report for an Evaluation.

Fields:

- `id` ULID
- `evaluation_id`
- `current_version_id`
- `creator_visible_at`
- `public_visible_at`
- timestamps

### 7.2 Report Version

Immutable snapshot of report content.

Fields:

- `id` ULID
- `report_id`
- `version_number`
- `content_structure` JSON
- `abstract`
- `decision_snapshot`
- `standard_version_snapshot`
- `published_at`
- `created_by`
- `change_reason`
- timestamps

Creator reports contain the full agreed report. Public reports default to the abstract plus the explicitly approved public result data. The creator may enable full report disclosure on the badge/verification page.

The public abstract should be generated from Valid.guide's report, not supplied as marketing copy by the creator.

### 7.3 Clarification Request

A controlled request for explanation or factual correction.

Fields:

- `id` ULID
- `evaluation_id`
- `organization_id`
- `type` (`clarification`, `factual_error`)
- `message`
- `status`
- `submitted_at`
- `resolved_at`
- timestamps

### 7.4 Dispute / Appeal Review

Separate from the original Evaluation.

Allowed grounds:

- procedural error
- material factual error
- conflict-of-interest concern
- demonstrably flawed application of the published methodology

Not allowed as a mechanism to negotiate away an unfavorable but properly supported judgement.

Fields:

- `id` ULID
- `evaluation_id`
- `type`
- `grounds`
- `status`
- `submitted_at`
- `resolved_at`
- `outcome`
- `decision_rationale`
- timestamps

Reviewers/Auditors assigned to the dispute must not have participated in the initial evaluation.

If a dispute establishes that the original evaluation was flawed, a new Evaluation may be created. The original Evaluation and decision remain historically immutable; a new validation may supersede the previous public state where justified.

---

## 8. Commerce

### 8.1 Service Package

Defines what a creator is buying.

Fields should support:

- name
- description
- product-type applicability
- complexity applicability
- price
- currency
- included scope
- active status

The price quoted to the creator is fixed before payment.

### 8.2 Order

Commercial transaction record.

Fields:

- `id` ULID
- `organization_id`
- `evaluation_request_id`
- `amount_minor`
- `currency`
- `status`
- `provider`
- `provider_reference`
- timestamps

### 8.3 Payment

Provider-level payment record.

Fields:

- `id` ULID
- `order_id`
- `provider`
- `provider_payment_id`
- `amount_minor`
- `currency`
- `status`
- `paid_at`
- raw provider metadata where operationally justified
- timestamps

Webhook processing must be idempotent.

### 8.4 Refund

Separate record. Refunds never erase evaluation history.

Fields:

- `id` ULID
- `order_id`
- `amount_minor`
- `currency`
- `reason`
- `status`
- `provider_refund_id`
- `requested_at`
- `completed_at`
- timestamps

Commercial policy:

- Creator may cancel before evaluation starts.
- 100% no-questions-asked refund is available after payment until the report is delivered.
- Full refund if Valid.guide cannot complete the evaluation.
- No refund merely because the creator fails validation.
- A new edition/version is a new paid evaluation.

### 8.5 Auditor Compensation

Separate from creator payment.

Fields:

- `id` ULID
- `auditor_assignment_id`
- `amount_minor`
- `currency`
- `status`
- `eligible_at`
- `paid_at`
- `payout_reference`
- timestamps

Compensation is fixed per evaluation and independent of the outcome. No positive-result incentive is permitted.

A separate payout provider abstraction should be used. Creator payment processing and Auditor payout processing should not be coupled merely because both happen to involve money. Stripe can support the relevant flows, but the domain should not depend directly on Stripe APIs.

---

## 9. Complexity and pricing

Complexity is a first-class concept because it controls both pricing and Auditor allocation.

Recommended initial tiers:

| Tier | Typical scope | Auditor count | Indicative price |
|---|---|---:|---:|
| Essential | Short guide/ebook, limited scope, straightforward methodology | 1 | €249–€349 |
| Standard | Typical course/workshop/program | 1 | €499–€699 |
| Comprehensive | Large course/program, multiple components or significant evidence burden | 3 | €999–€1,499 |
| Complex | High-risk, highly technical, multi-format or unusually extensive product | 5 | €1,999+ |

These are **initial commercial hypotheses**, not hard-coded prices. The eventual price should be based on estimated Auditor workload, operational overhead and desired gross margin. Pricing must never be tied to the eventual score or decision.

Complexity should be determined and quoted before payment.

The methodology should define objective complexity inputs such as:

- volume of material
- number of modules/components
- delivery modes
- audience breadth
- subject complexity
- evidence burden
- interaction/testing burden
- number of applicable criteria
- risk/sensitivity of claims

The admin assigns the final complexity level before the payment request is issued.

---

## 10. Reviewer/Auditor eligibility

Eligibility is topic-specific and complexity-aware.

Required model:

1. Demonstrated expertise in the subject.
2. Relevant teaching, training or creator experience where the product format requires it.
3. Ability to evaluate the product format.
4. Methodological literacy and completion of Valid.guide Auditor onboarding.
5. Credentials are supporting evidence, not a universal mandatory degree requirement.
6. Portfolio/references may strengthen eligibility.

An Auditor cannot evaluate a product in which they previously participated.

Potential conflicts are disclosed annually and per assignment. The Admin makes the final conflict determination.

---

## 11. Public directory

Validated products are listed publicly by default. Creators may opt out of directory listing while retaining a verifiable badge.

MVP filters:

- Product type
- Subject/topic
- Audience level
- Validation status
- Validation date / most recent validation
- Language

Do not rank by sales, popularity, commercial performance or star ratings. Valid.guide is a validation service, not Amazon wearing a tweed jacket.

---

## 12. Product change and validation rules

A material product change normally requires revalidation.

Material changes include:

- new edition
- new version
- new publication/release date
- product rename/rebrand
- changes to substantive content or learning design
- changes to claimed outcomes
- changes to the validated scope
- other quantitative metadata explicitly designated as validation identity metadata

The following do **not automatically** require revalidation:

- price changes
- instructor changes
- platform migration

However, the methodology may identify exceptions where such a change materially affects a criterion. The system must allow an Admin to flag a change for review.

The creator has a duty to notify Valid.guide of material changes.

If an unannounced material change is detected:

`Active → Suspended → review → Active or Revoked`

If the change invalidates the validated identity or scope, the existing Validation is revoked. Revocation is permanent for that validation.

If an old validated release remains independently available, its historical validation may remain active, subject to Auditor Board/admin review and precise release identification.

---

## 13. Authorization boundaries

### Creator organization members

- May only access records belonging to their Organization.
- Owner/Admin can manage organization and products.
- Editor can manage products/intake but not billing or organizational ownership.
- Billing can manage orders/payments/invoices.

### Auditors

- May access only accepted and cleared assignments.
- May not access unrelated evaluations.
- May not edit another Auditor's work.
- May not modify methodology.
- May not decide final publication.

### Administrators

Role permissions should distinguish at least:

- operations
- methodology
- trust/publication
- commerce
- system administration

Administrative power to override a decision must not imply permission to rewrite immutable Auditor records.

---

## 14. Status model

### Evaluation Request

`draft → submitted → awaiting_payment → paid → intake → awaiting_creator → ready_for_assignment → cancelled`

### Evaluation

`pending → assigning → conflict_check → in_progress → auditor_submitted → internal_review → ready_for_decision → decided → report_ready → published`

Failure/exception states should be explicit rather than hidden in notes.

### Validation

`active → suspended → active`

or

`active → suspended → revoked`

A validation can also become `superseded` when a later independent validation replaces it for the same product/release relationship.

There is no automatic expiry.

### Report

`draft → internal_review → creator_released → public_released → superseded`

### Dispute

`submitted → intake_review → assigned → under_review → resolved`

---

## 15. Audit requirements

Audit log entries are required for at least:

- organization membership/role changes
- product release identity changes
- payment/refund events
- evaluation creation/start/completion
- standard approval/effective/retirement
- standard assignment to an evaluation
- Auditor assignment/acceptance/decline
- conflict declarations and determinations
- Auditor submission/locking
- votes and criterion decisions
- administrative overrides
- final decision
- report publication and revision
- badge issue/suspension/revocation
- directory publication changes
- dispute outcomes
- public validation changes

Audit records should be append-only from the application perspective.

Recommended fields:

- `id`
- actor type/id
- action
- subject type/id
- old values JSON where appropriate
- new values JSON where appropriate
- reason/context
- request metadata where legally justified
- timestamp

---

## 16. Database conventions

- Use ULIDs consistently for domain entities.
- Use foreign keys and explicit indexes.
- Use integer minor units for monetary amounts.
- Store ISO currency codes.
- Use PHP enums for stable application statuses while keeping methodology-defined states in data where appropriate.
- Prefer explicit status transition Actions over direct model mutation.
- Use soft deletes selectively. Never soft-delete historical evaluations in a way that makes published validation unverifiable.
- Use JSON only for genuinely variable methodology metadata, not as a substitute for relational structure.
- Use immutable/versioned records where historical truth matters.
- Add unique constraints for identity boundaries such as verification IDs, standard versions and organization membership.
- Index public lookup fields and foreign keys.

---

## 17. Recommended migration order

1. users / authentication support
2. organizations
3. organization memberships
4. product types
5. products
6. product releases
7. evaluation standards
8. standard versions
9. criteria
10. criterion guidance
11. service packages
12. evaluation requests
13. orders
14. payments
15. refunds
16. evaluations
17. Auditor profiles
18. Auditor assignments
19. conflict declarations
20. Auditor evaluations
21. criterion results
22. votes
23. findings
24. evidence
25. decisions
26. validations
27. badges
28. public verification records
29. reports
30. report versions
31. clarification requests
32. disputes
33. Auditor compensation / payouts
34. notifications
35. audit logs

The actual Laravel migration sequence may group tightly coupled tables, but foreign-key dependencies must be respected.

---

## 18. First implementation slice

The first vertical slice should prove the architectural principle rather than merely produce CRUD screens:

`Creator → Organization → Product → Product Release → Evaluation Request → Quote → Payment → Intake → Evaluation → Auditor Assignment → COI → Auditor Evaluation → Criterion Results → Decision → Report → Validation → Badge → Public Verification`

The slice must include at least one negative path:

`Evaluation → Not Validated → Report → no badge → creator receives full report`

It must also prove that:

- payment does not determine the decision
- Auditor results are immutable
- Admin decisions are separate
- Standard Version is frozen
- organization isolation works
- public verification works
- revocation changes badge status without destroying the verification page
- refund history does not erase evaluation history

---

## 19. Deliberately deferred methodology work

The persistence model is now sufficient to support the methodology without hard-coding its answers prematurely.

The next methodology design must define:

- comprehensive top-level criteria
- criterion hierarchy/subcriteria
- mandatory vs optional criteria
- scoring model
- threshold/pass logic
- disqualifying conditions
- product-type-specific criteria
- weights
- evidence standards
- applicability rules
- complexity calculation
- Auditor-count rules by complexity
- voting rules by criterion
- public score presentation

These should become versioned Standard data, not PHP constants scattered through the application.
