# Valid.guide

## Application Plan

> **Status:** Planning
> **Product:** Valid.guide Validation
> **Stack:** Laravel 13, Filament 5, Livewire 4, Flux 2, Tailwind CSS 4
>
> This document is the application blueprint. It describes what the product should become, the domain model, workflows, interfaces, rules, and implementation order. It is deliberately written before implementation so the codebase does not become a collection of database tables wearing a business model as a costume.

---

## 1. Product Definition

Valid.guide is an independent validation service for courses, guides, and other online learning products.

A creator or publisher submits a learning product for evaluation. Valid.guide evaluates it against a transparent quality standard using evidence gathered by qualified reviewers. The creator receives a detailed evaluation report. If the product meets the defined standard, Valid.guide awards a public validation badge and publishes an appropriate public record.

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

1. **Independence** — the person or people evaluating a product must be separated from the commercial transaction as far as practical.
2. **Evidence** — findings should be traceable to submitted material, observed product content, testing, or documented reviewer judgement.
3. **Transparency** — the evaluation standard and meaning of the badge must be understandable.
4. **Fairness** — creators must have a predictable process and an opportunity to provide relevant evidence or clarify factual errors.
5. **Usefulness** — the report should identify both strengths and weaknesses and give actionable findings.
6. **No guaranteed pass** — the system must never imply that purchasing an evaluation buys validation.
7. **Auditability** — important decisions and changes must leave an immutable or append-only audit trail.
8. **Separation of concerns** — commercial administration, reviewer work, evaluation methodology, and public presentation should be distinct domains.

---

## 3. Primary Actors

### 3.1 Creator

The person or organization submitting a product for evaluation.

Capabilities:

- Create and manage an organization/profile.
- Submit learning products.
- Purchase or request an evaluation.
- Provide product access and supporting evidence.
- Track evaluation progress.
- Receive and view reports.
- Respond to factual clarification requests.
- See validation status and badge information.
- Manage billing and invoices.

### 3.2 Reviewer / Auditor

An independent subject-matter expert who performs evaluation work.

Capabilities:

- Maintain reviewer profile and expertise.
- Declare conflicts of interest.
- Accept or decline assignments.
- Access only assigned evaluation materials.
- Complete structured evaluation criteria.
- Attach evidence and findings.
- Submit an evaluation for internal review.
- Record time/effort or compensation data as required internally.

The application should use the term **Auditor** in the public-facing program if that is the final brand decision, while the internal domain model may use `Reviewer` if that better describes the role technically. The terminology must be settled before implementation of authorization and UI.

### 3.3 Valid.guide Administrator

Internal staff operating the service.

Capabilities:

- Manage users, organizations, products and submissions.
- Manage evaluation standards and criteria.
- Assign reviewers.
- Review conflicts of interest.
- Monitor evaluation progress.
- Approve, reject, or request changes to evaluations.
- Publish/unpublish validation results.
- Manage badges.
- Manage payments, refunds and invoices.
- Manage reviewer compensation.
- Access audit logs.
- Manage public content and configuration.

### 3.4 Buyer / Learner

Primarily a consumer of public validation information, not necessarily an authenticated application user in the MVP.

Capabilities in the initial public product:

- Discover validated products.
- View validation status.
- Understand what was evaluated.
- See the evaluation date and scope.
- Understand what the badge means.
- Read appropriate public findings.
- Verify that a badge belongs to the stated product.

---

## 4. Core Domain Model

The application should be built around the following concepts.

### User

Authentication identity.

Important attributes:

- name
- email
- password/authentication credentials
- status
- email verification state
- timestamps

A user may participate in different capacities. Do not create separate authentication systems for creators, reviewers and administrators.

### Organization

A creator/publisher entity. A creator may be an individual organization of one or more people.

Important attributes:

- name
- slug
- description
- website
- contact details
- status

Relations:

- has many users/members
- has many products
- has many orders/evaluation requests

### Organization Membership

Defines a user's relationship with an organization.

Potential roles:

- owner
- admin
- editor
- billing

This should be authorization data, not a collection of booleans on the user table.

### Product

The learning product being evaluated.

Examples:

- online course
- cohort course
- guide
- ebook-based learning product
- workshop
- program
- other defined learning format

Important attributes:

- organization
- title
- slug
- product type
- description
- URL
- price/reference price where useful
- target audience
- claimed outcomes
- status

A product is the enduring entity. An evaluation is a separate historical event. This distinction is important because the same product may be evaluated more than once.

### Evaluation Request

The commercial/customer-facing request to have a product evaluated.

It represents the transaction and intake process, not the evaluation itself.

Important attributes:

- organization
- product
- requested service/package
- price
- currency
- payment status
- intake status
- submitted date
- requested completion window
- cancellation/refund status

### Evaluation

The actual independent assessment.

Important attributes:

- product
- evaluation request
- standard/version
- status
- assigned reviewers
- started/completed dates
- decision
- overall result
- publication status

An evaluation must snapshot the standard/version used. Changing the current standard must not silently change the meaning of historical evaluations.

### Evaluation Standard

The versioned framework against which products are assessed.

A standard has:

- name
- version
- description
- effective date
- retirement date
- status

The standard contains criteria/categories.

### Evaluation Criterion

A measurable or assessable part of the standard.

Examples of criterion categories:

- Promise clarity
- Subject-matter credibility
- Content quality
- Structure and coherence
- Learning design
- Practical applicability
- Evidence/support
- Accessibility/usability
- Originality/differentiation
- Value relative to stated promise
- Outcome realism

The exact criteria are a product/methodology decision and should be stored as data rather than hard-coded into Filament forms.

### Evaluation Criterion Result

A reviewer's assessment of one criterion for one evaluation.

Possible states should distinguish:

- not assessed
- insufficient evidence
- meets standard
- partially meets standard
- does not meet standard
- not applicable

A numerical score may be used internally if the methodology requires it, but the public product should not reduce the entire evaluation to a simplistic star rating.

### Evidence

A piece of material supporting an evaluation finding.

Evidence may include:

- product URL
- course/module reference
- document
- screenshot
- submitted creator material
- reviewer observation
- test/interaction record
- external source where methodology permits it

Evidence needs provenance and should be associated with the criterion/finding it supports.

### Finding

A substantive observation produced during evaluation.

Types:

- strength
- weakness
- risk
- recommendation
- factual clarification

Findings should have severity/importance where useful and must be traceable to criteria and evidence.

### Reviewer Assignment

Connects an evaluation to one or more reviewers.

Important attributes:

- reviewer
- evaluation
- role
- assigned date
- accepted/declined date
- completed date
- conflict-of-interest status
- compensation status

### Conflict of Interest Declaration

A reviewer must explicitly declare relevant conflicts before accessing or submitting substantive work.

Possible outcomes:

- no conflict
- potential conflict
- disqualified

Conflicts must be auditable.

### Evaluation Decision

The final internal decision.

MVP decisions:

- validated
- not validated
- needs revision / re-evaluation
- withdrawn

The application should separate **decision** from **publication status**. A product can have a decision that has not yet been published.

### Validation Badge

A public trust signal tied to a specific product and evaluation.

The badge must not simply be a permanent property of the product. It should be derived from a valid evaluation and have a lifecycle.

Important attributes:

- product
- evaluation
- public verification token/identifier
- issued date
- expiry/revalidation date if applicable
- status
- public URL

The verification page is the authoritative source. A badge image alone is not.

### Evaluation Report

The structured output delivered to the creator and potentially partially published publicly.

The report should contain:

- product information
- evaluation scope
- standard/version
- methodology
- executive summary
- strengths
- weaknesses
- criterion-level findings
- evidence references
- recommendations
- decision
- reviewer information where appropriate
- dates

Reports should be versioned rather than overwritten.

### Payment / Order

Commercial record for evaluation services.

Should support:

- payment status
- amount/currency
- provider reference
- refund status
- invoice data
- timestamps

Payment state must never be used as an evaluation result.

### Notification

System communications for creators, reviewers and administrators.

Examples:

- submission received
- payment received
- additional information requested
- reviewer assigned
- evaluation started
- evaluation completed
- result published
- badge expiring
- refund processed

### Audit Log

Append-only record of important actions.

At minimum:

- actor
- action
- subject type/id
- old state where appropriate
- new state where appropriate
- timestamp
- IP/request metadata where legally and operationally justified

---

## 5. Evaluation Lifecycle

The central workflow should be explicit and state-driven.

```text
Draft
  ↓
Submitted
  ↓
Awaiting Payment
  ↓
Paid / Intake
  ↓
Intake Review
  ├── Needs Information → Awaiting Creator
  └── Ready
        ↓
Reviewer Assignment
        ↓
Conflict Check
        ├── Conflict → Reassign
        └── Clear
              ↓
Evaluation In Progress
              ↓
Internal Review
        ├── Needs More Work → Evaluation In Progress
        └── Ready for Decision
              ↓
Decision
  ├── Validated
  │     ↓
  │   Publication
  │     ↓
  │   Badge Active
  │
  └── Not Validated
        ↓
  Report Delivered

Validated products may later enter:

Active → Expiring → Revalidation → Active / Expired / Withdrawn
```

Every transition should have:

- an explicit actor
- a timestamp
- validation rules
- an audit entry
- appropriate notifications

Do not allow arbitrary status changes from generic CRUD forms.

---

## 6. Creator Workflow

### Step 1: Account

- Register/login.
- Verify email.
- Create or join an organization.

### Step 2: Product

Creator enters:

- product details
- product URL/access instructions
- intended audience
- claimed outcomes
- format
- pricing/context
- supporting documentation

### Step 3: Evaluation Request

Creator selects the validation service and sees:

- price
- what is included
- evaluation scope
- approximate process
- what is not guaranteed
- refund/cancellation terms

The creator explicitly acknowledges that payment purchases an evaluation, not a passing result.

### Step 4: Payment

Payment is processed before substantive evaluation work begins, subject to the final commercial policy.

### Step 5: Intake

Valid.guide checks whether enough information exists to begin.

### Step 6: Evaluation

Creator can see high-level progress, but should not see reviewer deliberation that could compromise independence.

### Step 7: Result

Creator receives the report and decision.

If validated:

- badge becomes available
- verification page becomes public
- creator receives embed/link assets

If not validated:

- report explains the findings
- recommendations may identify improvements
- no badge is issued

---

## 7. Reviewer / Auditor Workflow

Reviewer onboarding should be deliberate because reviewer credibility is part of the product.

### Onboarding

- Profile
- Expertise
- Relevant experience
- Qualifications/credentials where applicable
- Portfolio/public profile
- Languages
- Domains of expertise
- Conflict-of-interest declaration
- Agreement to reviewer standards
- Compensation terms

### Assignment

Reviewer sees only the information needed to decide whether to accept an assignment.

After acceptance and conflict clearance, detailed materials become available.

### Evaluation workspace

The reviewer should work through the versioned evaluation standard:

1. Criterion
2. Evidence
3. Assessment
4. Finding
5. Recommendation
6. Confidence/notes where methodology requires it

Autosave should be used for long evaluations.

### Submission

Reviewer submits the evaluation for internal review. After submission, changes should be controlled and versioned.

---

## 8. Independence and Anti-Pay-to-Play Architecture

This is the most important non-functional requirement in the entire application.

The application must make it structurally difficult to turn payment into a positive outcome.

### Rules

- Payment creates an evaluation request, never a validation result.
- The creator cannot choose the reviewer.
- Reviewer identity may be hidden from the creator until policy permits disclosure.
- Reviewer conflict declarations are mandatory.
- Evaluation standard/version is locked when evaluation begins.
- Decision requires an authorized internal transition.
- Badge creation requires a validated evaluation.
- Published validation cannot be edited by the creator.
- Creator corrections should enter a controlled clarification process.
- Important decision changes are audited.

### Potential future safeguard

For higher-value or disputed evaluations, use two reviewers or an independent second-review process.

Do not build a complicated double-blind academic journal system into the MVP unless the business actually needs it. Bureaucracy is not credibility.

---

## 9. Public Website

The public site should be simple and trust-oriented.

### Core pages

- Home
- How Validation Works
- Evaluation Standard
- For Creators
- For Buyers/Learners
- Auditors
- Validated Products
- Individual Product Validation page
- Verify a Badge
- About / Independence
- Pricing
- FAQ
- Contact
- Legal pages

### Public product validation page

Each validated product should have a canonical page containing:

- Product name
- Creator/publisher
- Product type
- Validation status
- Date evaluated
- Standard/version
- Scope of evaluation
- High-level strengths
- High-level limitations/findings where appropriate
- Badge
- Verification identifier
- Link to methodology

The public page should clearly state that validation evaluates the product against Valid.guide's defined standard and does not guarantee an individual learner's results.

---

## 10. Creator Application

The creator-facing application should be a separate experience from the internal operations interface, even if both are implemented in the same Laravel application.

### Dashboard

Show:

- products
- active evaluation requests
- evaluations in progress
- completed evaluations
- validation status
- invoices/payment status
- actions requiring attention

### Product management

CRUD plus controlled lifecycle actions.

### Evaluation request

Wizard-style intake rather than one enormous form.

Suggested steps:

1. Product
2. Scope
3. Access/materials
4. Claims and audience
5. Review
6. Payment

### Evaluation detail

Show:

- status
- timeline
- requested information
- submitted materials
- report when available
- result
- badge when available

Do not expose internal reviewer notes or deliberation by default.

---

## 11. Internal Filament Application

Filament should be the primary operations back office.

### Navigation groups

#### Operations

- Dashboard
- Evaluation Requests
- Evaluations
- Reviewer Assignments
- Findings
- Reports

#### Methodology

- Standards
- Criteria
- Criterion Templates / Guidance

#### Directory

- Users
- Organizations
- Products
- Reviewers / Auditors

#### Trust

- Validations
- Badges
- Public Pages
- Conflicts of Interest
- Audit Log

#### Commerce

- Orders / Payments
- Refunds
- Invoices
- Reviewer Compensation

#### System

- Notifications
- Settings
- Roles / Permissions

Not every model needs a public CRUD resource. Some should only be exposed through workflow-specific pages/actions.

---

## 12. Filament Resources and Custom Pages

Use standard Filament Resources for relatively stable entities:

- Organization
- Product
- User
- Reviewer
- Standard
- Criterion
- Payment/Order
- Badge

Use custom Filament Pages or workflow actions for stateful processes:

- Evaluation Intake
- Evaluation Assignment
- Reviewer Evaluation Workspace
- Internal Decision Review
- Report Approval
- Badge Publication
- Conflict Review

The application should not reduce complex business workflows to a giant `EditEvaluation` form. Humans have suffered enough from those.

---

## 13. Authorization

Use Laravel policies/gates and Filament authorization rather than scattered role checks.

Suggested authorization layers:

- Platform administrator
- Operations staff
- Methodology manager
- Reviewer
- Organization owner
- Organization admin
- Organization editor
- Organization billing user

Principle of least privilege:

- Creators access only their organization's data.
- Reviewers access only assigned evaluations.
- Reviewers cannot modify commercial records.
- Billing users can access billing data without evaluation internals.
- Methodology managers can manage standards but should not automatically gain access to payment data.
- Public users access only explicitly published validation information.

---

## 14. Data and Persistence Strategy

Use Eloquent models and migrations as the canonical application model.

### Important relational rules

- Foreign keys everywhere appropriate.
- UUID/ULID strategy should be selected early and used consistently.
- Slugs for public resources.
- Soft deletion only where it has a legitimate business meaning.
- Historical evaluation records should generally not be deleted.
- Money should be stored as integer minor units plus currency, not floating point.
- Statuses that drive workflows should be represented by enums or strongly controlled values.
- Versioned methodology data must never be mutated in a way that changes historical meaning.

### Suggested first-class tables

```text
users
organizations
organization_user
products
orders
payments
refunds
evaluation_requests
evaluation_standards
evaluation_criteria
evaluations
evaluation_reviewers
conflict_declarations
evaluation_criterion_results
evidence
findings
reports
report_versions
validation_badges
notifications
audit_logs
```

The exact schema should be refined during implementation based on real workflow requirements. Avoid creating every imaginable table before the first workflow exists.

---

## 15. File and Evidence Handling

Evaluation materials may be sensitive and commercially valuable.

Requirements:

- Private storage by default.
- Authorization checks before every download/access path.
- Signed temporary URLs where appropriate.
- Metadata for uploaded evidence.
- Virus/malware scanning if file uploads become substantial.
- Clear retention policy.
- Do not expose raw creator materials through public URLs.
- Public reports should contain only intentionally published information.

Storage abstraction should remain provider-independent so S3 or another object store can be introduced without rewriting the domain layer.

---

## 16. Payments and Commercial Model

The MVP should support the core commercial transaction without allowing commerce to contaminate evaluation logic.

### Initial flow

```text
Evaluation Request
      ↓
Order
      ↓
Payment
      ↓
Paid
      ↓
Evaluation Intake
```

### Payment provider

Use a dedicated payment integration, likely Stripe, but keep provider-specific code behind an application service/interface.

Do not spread Stripe IDs and webhook logic across models and Filament resources.

### Refunds

The commercial refund policy must be configurable and documented before launch.

If a 100% no-questions-asked refund policy is adopted, the application must still distinguish:

- customer cancellation
- operational inability to evaluate
- refund after payment
- refund after evaluation delivery

Refunds must not alter or erase historical evaluation records.

---

## 17. Validation Badge System

The badge is a trust mechanism, not decoration.

### Requirements

- Unique verification ID.
- Canonical verification URL.
- Product-specific association.
- Evaluation-specific association.
- Issue date.
- Current status.
- Expiration/revalidation policy if adopted.
- Revocation mechanism.
- Public verification page.

### Statuses

At minimum:

- active
- expired
- suspended
- revoked

A badge must never remain apparently valid after its underlying validation has been revoked or expired.

---

## 18. Revalidation / Recurring Revenue

Recurring validation should be designed into the domain without making it an MVP requirement.

A product may eventually require periodic revalidation because:

- content changes
- claims change
- delivery changes
- links/resources disappear
- the product becomes outdated
- methodology standards evolve

Future model:

```text
Validation
   ↓
Active
   ↓
Approaching Expiry
   ↓
Revalidation Request
   ↓
New Evaluation
   ↓
New Validation Period
```

Do not overwrite the previous evaluation. Each revalidation is a new evaluation with its own standard snapshot, evidence, findings and decision.

---

## 19. Reviewer Compensation

The auditor board is part of the credibility and supply side of the business.

The application should eventually support:

- reviewer profile
- expertise areas
- onboarding status
- availability
- assignments
- accepted/declined assignments
- completed evaluations
- compensation amount
- compensation status
- payout records

Compensation must be completely independent from whether an evaluation results in validation.

Do not reward reviewers for positive decisions.

---

## 20. Notifications

Use Laravel notifications/events where appropriate.

Events should represent meaningful domain changes, for example:

- EvaluationRequestSubmitted
- PaymentCompleted
- IntakeInformationRequested
- EvaluationAssigned
- ReviewerAcceptedAssignment
- ConflictDeclared
- EvaluationSubmitted
- EvaluationReturnedForRevision
- EvaluationDecisionMade
- ValidationPublished
- BadgeExpiring
- BadgeExpired
- RefundCompleted

Notifications should be subscribers to domain events rather than being hard-coded into every controller/action.

Channels for MVP:

- email
- in-app notifications

Future:

- webhook
- other integrations

---

## 21. Auditability

The following actions should always be auditable:

- evaluation assignment
- reviewer acceptance/decline
- conflict declarations
- criterion result changes after submission
- evaluation decision
- validation publication
- badge issue/revocation
- refunds
- changes to evaluation standards
- report publication
- privileged access to sensitive evaluation materials where practical

Audit records should be append-only from the application's normal interface.

---

## 22. Reporting

Reports should be generated from structured evaluation data, not assembled manually as arbitrary HTML stored in a single text field.

### Internal report structure

```text
Cover / Product
Evaluation scope
Methodology and standard
Executive summary
Overall assessment
Criterion results
Strengths
Weaknesses
Risks
Recommendations
Evidence references
Decision
Reviewer information
Publication information
```

### Creator report

Detailed and actionable.

### Public report

Shorter and trust-oriented. It should expose enough information to justify the validation without publishing sensitive creator material or internal reviewer deliberations.

Reports should have versions so corrections create a new version rather than destroying the previous record.

PDF generation can be added after the structured report model is stable.

---

## 23. Search and Discovery

The public site should eventually support discovery of validated products.

MVP:

- product title search
- creator search
- product type filter
- validation status filter
- topic/category filter if categories are defined

Later:

- full-text search
- recommendation/discovery
- comparison

Do not build a sophisticated recommendation engine before there is enough validated product data to make it useful.

---

## 24. Public Trust and Transparency Pages

Valid.guide's credibility depends partly on explaining how it works.

The application should provide structured content for:

- What Valid.guide evaluates.
- What it does not evaluate.
- How reviewers are selected.
- How conflicts are handled.
- How standards are created and versioned.
- What the badge means.
- What the badge does not mean.
- How creators can challenge factual errors.
- How validation can expire or be revoked.
- How the service is funded.

The commercial relationship must be explicit: creators pay for evaluation, but cannot purchase a passing decision.

---

## 25. Creator Clarification / Dispute Process

A creator should have a controlled way to challenge factual errors without turning the process into negotiation over the result.

Possible workflow:

```text
Report Delivered
      ↓
Creator Requests Clarification
      ↓
Internal Review
  ├── Factual Error → Corrected Report Version
  └── No Error → Clarification Closed
```

The creator should not be able to demand removal of an accurate negative finding simply because it is commercially inconvenient.

A more formal appeals process can be added later if demand warrants it.

---

## 26. Security Requirements

Because the application handles authentication, payments, private product materials and reviewer work, security is a first-class requirement.

Requirements:

- Laravel authentication/authorization primitives.
- Email verification.
- Strong password/session handling.
- CSRF protection through framework defaults.
- Validation and authorization on every mutation.
- Private evidence storage.
- No sensitive information in logs.
- Secure webhook verification.
- Rate limiting on authentication and public verification endpoints where appropriate.
- Strict tenant/organization data isolation.
- Secure file upload handling.
- Regular dependency updates.
- Security tests for authorization boundaries.

Never rely on hiding a Filament navigation item as an authorization mechanism.

---

## 27. Testing Strategy

The application should be test-first around business rules, not merely UI screenshots.

### Unit tests

- status transition rules
- scoring/decision logic
- standard versioning
- badge lifecycle
- money calculations
- authorization rules

### Feature tests

- creator submission flow
- payment webhook flow
- reviewer assignment
- conflict handling
- evaluation completion
- decision/publication
- badge verification
- refunds

### Authorization tests

Explicitly test that:

- creator A cannot access creator B's products/evaluations
- reviewer A cannot access reviewer B's assignments
- reviewer cannot alter commercial records
- creator cannot alter evaluation findings
- public user cannot access private evidence

### Browser tests

Use browser-level tests selectively for critical workflows:

- registration
- submission
- reviewer workspace
- internal decision workflow
- public badge verification

Do not attempt to test every Filament CRUD screen end-to-end.

---

## 28. Application Architecture

Keep the application idiomatic Laravel rather than inventing an enterprise architecture diagram large enough to require its own architect.

Suggested structure:

```text
app/
├── Actions/
├── Enums/
├── Events/
├── Filament/
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Listeners/
├── Mail/
├── Models/
├── Notifications/
├── Policies/
├── Services/
└── Support/
```

### Domain behavior

Use application/domain Actions or dedicated services for meaningful operations such as:

- SubmitEvaluationRequest
- CompletePayment
- StartIntake
- AssignReviewer
- AcceptReviewerAssignment
- DeclareConflict
- StartEvaluation
- SubmitEvaluation
- ReturnEvaluationForRevision
- DecideEvaluation
- PublishValidation
- IssueBadge
- RevokeBadge
- RequestClarification
- PublishReport
- ProcessRefund

Controllers and Filament actions should orchestrate these operations rather than contain the business rules themselves.

---

## 29. Events and Queues

Use queued jobs for work that is slow, external, or failure-prone.

Likely queued operations:

- email delivery
- PDF report generation
- file processing
- payment reconciliation
- reminder notifications
- badge expiry notifications
- future external integrations

Do not introduce queues everywhere merely because queues exist. Synchronous operations should remain simple where appropriate.

---

## 30. Configuration

Business rules that may change should be configurable where appropriate:

- evaluation pricing
- currencies
- evaluation duration targets
- badge duration
- revalidation period
- refund rules
- notification settings
- supported product types

Methodology data belongs in the database because it must be versioned and audited.

Infrastructure secrets belong in environment configuration, never in the database or repository.

---

## 31. MVP Scope

The first usable version should prove one complete commercial-to-validation loop.

### Must have

1. Authentication.
2. Creator organization/account.
3. Product creation.
4. Evaluation request/intake.
5. Payment integration.
6. Versioned evaluation standard.
7. Reviewer onboarding/profile.
8. Reviewer assignment.
9. Conflict declaration.
10. Reviewer evaluation workspace.
11. Structured criterion results.
12. Evidence and findings.
13. Internal decision.
14. Creator report.
15. Validation badge.
16. Public verification page.
17. Audit trail for critical actions.
18. Email notifications.
19. Basic administrator dashboard.
20. Authorization isolation.

### Should have

- public validated-product directory
- creator clarification workflow
- reviewer compensation records
- report PDF
- badge embed code
- basic analytics

### Later

- recurring revalidation subscriptions
- advanced discovery/recommendations
- multiple-reviewer consensus
- formal appeals
- API
- third-party integrations
- buyer accounts and saved products
- learner outcome data
- advanced analytics

---

## 32. Deliberate Non-Goals for MVP

Do not build these until the core validation loop works:

- social network
- comments/reviews marketplace
- affiliate marketplace
- complex recommendation engine
- gamification
- creator community
- mobile apps
- elaborate CRM
- arbitrary custom report builder
- AI-generated evaluation decisions
- AI replacing independent reviewers

AI may assist with administrative work, summarization or evidence organization later, but the core validation decision should remain governed by the defined methodology and accountable human reviewers.

---

## 33. Implementation Phases

### Phase 0 — Foundation

- Confirm naming and terminology.
- Establish enums and core conventions.
- Establish UUID/ULID strategy.
- Configure Filament panel(s).
- Establish roles/permissions.
- Establish testing conventions.
- Establish audit infrastructure.

### Phase 1 — Creator and Product

- Users
- Organizations
- Memberships
- Products
- Creator dashboard
- Product CRUD
- Authorization

**Exit condition:** a creator can securely manage their products.

### Phase 2 — Commercial Intake

- Evaluation request
- Service/package definition
- Order/payment model
- Payment provider
- Webhooks
- Refund handling
- Intake workflow

**Exit condition:** a creator can submit and pay for an evaluation and Valid.guide can see a clean paid request.

### Phase 3 — Methodology

- Standards
- Standard versions
- Criteria
- Criterion guidance
- Evaluation creation

**Exit condition:** an evaluation can be instantiated against an immutable methodology version.

### Phase 4 — Reviewer Operations

- Reviewer profiles
- Expertise
- Onboarding
- Conflict declarations
- Assignments
- Reviewer workspace

**Exit condition:** an independent reviewer can complete a structured evaluation.

### Phase 5 — Decision and Report

- Findings
- Evidence
- Internal review
- Decision workflow
- Report generation
- Report versions
- Creator delivery

**Exit condition:** Valid.guide can produce a defensible evaluation report and decision.

### Phase 6 — Validation and Public Trust

- Badge issuance
- Verification IDs
- Public validation pages
- Badge verification endpoint/page
- Publication controls
- Expiry/revocation

**Exit condition:** a third party can verify whether a product is currently validated.

### Phase 7 — Hardening

- Security review
- Authorization audit
- Workflow edge cases
- Notification reliability
- Queue processing
- Backup/retention strategy
- Performance review
- Observability
- Legal/compliance review

**Exit condition:** the system can safely handle real paying customers.

---

## 34. Recommended Build Order Inside Each Phase

For each domain feature:

1. Define business rule.
2. Define model/schema.
3. Define enum/state machine where applicable.
4. Write policies.
5. Write tests for critical rules.
6. Implement Action/service.
7. Implement Filament UI.
8. Implement creator/public UI if required.
9. Add notifications/events.
10. Add audit entries.
11. Test the complete workflow.

This order keeps the application from becoming a pretty Filament admin panel with no coherent product underneath it.

---

## 35. Initial Model Dependency Graph

```text
User
 ├── Organization Membership ── Organization
 │                                └── Product
 │                                      └── Evaluation Request
 │                                             └── Order / Payment
 │                                                   
Evaluation Standard
 └── Criterion

Evaluation Request
 └── Evaluation
       ├── Reviewer Assignment ── Reviewer/User
       │                              └── Conflict Declaration
       ├── Criterion Results
       │      ├── Evidence
       │      └── Findings
       ├── Report Versions
       ├── Decision
       └── Validation Badge

Validation Badge
 └── Public Verification Page

All critical entities/actions
 └── Audit Log
```

---

## 36. Key Business Rules to Encode as Tests

These should become explicit automated tests before launch.

1. An evaluation cannot start without a valid evaluation request.
2. An unpaid request cannot enter substantive evaluation.
3. A reviewer cannot evaluate their own product.
4. A reviewer with a disqualifying conflict cannot access or submit the evaluation.
5. A creator cannot choose or replace a reviewer merely because they dislike the assignment.
6. The evaluation standard is fixed for the evaluation once work begins.
7. A reviewer cannot submit an incomplete required evaluation.
8. A validation badge can only be issued for a validated evaluation.
9. A revoked/expired evaluation cannot have an active badge.
10. A public badge must resolve to the canonical product validation record.
11. A creator cannot edit finalized findings or decisions.
12. Corrections create controlled report versions.
13. Historical evaluations retain their original standard/version.
14. Payment/refund state cannot directly change validation outcome.
15. Reviewer compensation cannot depend on validation outcome.
16. Organization members cannot access data outside their organization.
17. Public users cannot access private evidence or reviewer deliberation.
18. Critical status transitions create audit records.

---

## 37. Open Decisions Before Implementation

These are business decisions, not coding details, and should be resolved before the affected phase.

### Validation methodology

- Exact criteria.
- Required evidence per criterion.
- Pass threshold or decision logic.
- Whether numerical scoring exists internally.
- Whether all criteria are mandatory.
- How different product types use different criteria.

### Reviewer model

- Public identities or anonymous reviewers.
- One reviewer versus multiple reviewers.
- Required qualifications.
- Auditor board naming.
- Compensation model.
- Conflict-of-interest rules.

### Commercial model

- Exact evaluation price.
- Whether pricing varies by product complexity.
- Refund policy.
- Payment timing.
- Revalidation pricing.
- Whether revalidation is annual or event-based.

### Public transparency

- How much of the report becomes public.
- Whether negative evaluations are publicly listed.
- Whether creators can decline publication of a failed evaluation.
- How factual disputes are handled.
- Whether reviewer identities are public.

### Badge

- Exact wording.
- Visual design.
- Duration.
- Revalidation rules.
- Revocation rules.
- Verification URL format.

These decisions should not be hidden inside migrations or Filament form code. They belong in the product/methodology layer.

---

## 38. First Vertical Slice

Before implementing the entire platform, build one complete vertical slice:

```text
Creator registers
    ↓
Creates organization
    ↓
Creates product
    ↓
Requests validation
    ↓
Pays
    ↓
Admin confirms intake
    ↓
Admin assigns reviewer
    ↓
Reviewer declares no conflict
    ↓
Reviewer evaluates criteria
    ↓
Reviewer submits
    ↓
Admin reviews
    ↓
Admin decides VALIDATED
    ↓
Report generated
    ↓
Badge issued
    ↓
Public verification page works
```

If this slice is not clean, adding dashboards, directories, AI, analytics and other decorative machinery is just building a larger problem.

---

## 39. Definition of Done for MVP

The MVP is ready for the first real evaluation when:

- A real creator can register and submit a real product.
- Payment can be completed and reconciled.
- A real reviewer can be assigned without conflicts.
- The reviewer can complete the defined standard.
- Evidence and findings are captured.
- An authorized person can make a defensible decision.
- The creator receives a useful report.
- A validated product receives a verifiable badge.
- A third party can independently verify the badge.
- A failed validation does not accidentally receive a badge.
- Historical evaluation data cannot be silently rewritten.
- Authorization boundaries have automated tests.
- Critical business actions are auditable.
- The application can handle a refund without corrupting evaluation history.
- The public messaging accurately describes what Valid.guide has and has not established.

---

## 40. Architectural Principle to Preserve

The entire application should reinforce one simple distinction:

> **Valid.guide sells the evaluation process. It does not sell the outcome.**

Everything else follows from that.

Payment belongs to commerce.

Evaluation belongs to methodology and reviewers.

Decision belongs to the validation process.

Badge belongs to the resulting trust signal.

Public pages belong to transparency.

Audit logs connect the whole system without allowing one domain to corrupt another.

That separation is not academic architecture. It is the thing that determines whether the Valid.guide badge eventually means something or becomes another decorative rectangle on a landing page.
