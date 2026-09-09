# Phase 1 Development Decisions

> Project: Valid.guide Validation
> Date started: 2026-09-08

This file records implementation-significant decisions made during Phase 1 that refine the approved domain architecture.

## 1. Public Verification Record is a first-class projection

The public verification page is authoritative for the external trust state. A `PublicVerificationRecord` is therefore persisted separately from `Validation` rather than rendering the public page directly from a collection of internal tables.

A record is created atomically when a Validation is issued. Its identity is tied permanently to the Validation and its public slug is derived from the non-guessable verification identifier.

## 2. Public verification content uses a persisted snapshot

The public record stores a JSON `snapshot` containing the public-facing identity and result data needed to render verification consistently. This prevents the public representation from becoming an accidental live query across mutable application data.

The snapshot is synchronized through a domain service when the Validation status changes. The record's identity (`validation_id`, `public_slug`, `published_at`) is immutable; controlled publication logic may update the snapshot and visibility flags.

## 3. Verification URLs remain meaningful after status changes

A public verification record is not deleted when a Validation is suspended, revoked or superseded. Its snapshot is updated to the current Validation status, allowing the verification URL and badge identifier to remain resolvable and to communicate the historical trust state.

## 4. Badge and public record share the Validation verification identifier

The Validation owns the canonical non-guessable verification identifier. The Badge and Public Verification Record use that identifier rather than generating independent public identities. This avoids ambiguous verification references and makes the badge a simple pointer to the authoritative record.

## 5. Public record creation is part of Validation issuance

Issuing a Validation, creating its Badge and creating its initial Public Verification Record are one transaction. A partial trust state must not be possible where a Validation exists but its badge or verification record does not.

## 6. Status propagation is domain-controlled

Validation status changes update the Badge and the Public Verification Record through `ValidationStateTransition`. Direct mutation/deletion of trust identities is blocked at the model layer. Bulk/raw database mutation remains outside these protections and must not be used for trust-domain writes.

## 7. Platform administration is separate from organization membership

Platform administration is represented by a dedicated nullable `users.platform_role` field and `PlatformRole::Admin`. Organization membership roles remain tenant-scoped and cannot grant platform authority.

High-trust operations are now explicitly guarded at the domain-service boundary. Recording an Evaluation Decision, determining a Conflict Declaration, issuing a Validation, and changing Validation status each require a platform administrator. The Filament admin panel uses the same platform-admin check, preventing an ordinary organization member from entering the platform administration surface.

This authorization is deliberately enforced in domain services rather than relying only on UI visibility. UI/policy layers may provide additional restrictions, but bypassing the UI must not bypass the platform trust boundary.

## 8. Reports are historical, versioned records

A Report belongs to one Evaluation and cannot be deleted. Report content is never edited in place after creation. Each correction or substantive revision creates a new immutable `ReportVersion`, with sequential numbering and a mandatory change reason. The Report points to its current version, while older versions remain available as historical records.

Initial report creation captures the evaluation decision and frozen Standard Version identity as snapshots. Revision workflows inherit those snapshots rather than reconstructing historical methodology state from mutable records.

## 9. Clarifications and formal disputes are different workflows

A Clarification Request is for questions about the published evaluation, report, methodology or process. It is not a mechanism for negotiating the outcome. Clarifications are submitted by authorized organization members after completion and answered/closed by platform administrators; submitted content becomes immutable once submitted, and answered requests cannot have their substantive content changed.

A Formal Dispute is a controlled challenge to the integrity of an evaluation. It is limited to four grounds: procedural error, material factual error, conflict of interest, and flawed methodology application. Commercial dissatisfaction, disagreement with the standard itself, or a request for a more favorable result are not dispute grounds.

Formal disputes are tenant-scoped, require an independent review before resolution, and cannot have an active duplicate for the same evaluation. Reviewers are assigned by platform administration and may not have participated in the original evaluation. Original participants include auditors and decision-makers, with auditor-linked findings also excluded from reviewer eligibility.

Resolved disputes are immutable. A `process_flawed` outcome never rewrites the original Evaluation, Auditor Evaluations, votes or decision. Instead, it creates a new Evaluation for the same product release and frozen Standard Version context, preserving the original evaluation as historical evidence.

## 10. An Evaluation Request can produce multiple Evaluation records

An Evaluation Request represents the commercial/intake request and is therefore not itself the historical evaluation record. Its relationship to `Evaluation` is one-to-many.

This is required because a materially flawed process may result in a new Evaluation while the original Evaluation remains immutable. It also provides a clean foundation for future re-evaluation workflows without overwriting the original assessment.

The request-level `evaluation_started_at` remains a lifecycle marker for the request's substantive work and is not treated as the unique start timestamp of every Evaluation record. Each Evaluation retains its own `started_at` and `completed_at` timestamps.

All high-trust dispute actions are enforced in the domain service, audited, and independent of UI permissions. Raw/bulk database writes must not be used to bypass these controls.

## 11. Standard Version governance freezes evaluative methodology content

A Standard Version has a one-way governance lifecycle: `draft` → `scheduled` → `effective` → `retired`.

Draft versions are editable. Scheduling requires a platform administrator and a future effective date and records approval. Once scheduled, the evaluative content of the version is frozen. This includes the version description and, through the related records, criteria, applicability rules, scoring rules and criterion guidance. Effective and retired versions remain frozen permanently.

Lifecycle metadata may change only through the governance service. A standard may have at most one effective version at a time; an effective version must be retired before a subsequent version can become effective. Every governance transition is audited.

This is a trust invariant, not a UI convention. Administrative screens must use `StandardVersionGovernance`; direct/bulk mutation must not be used to alter methodology state. Existing model-level protections cover ordinary Eloquent updates/deletes for frozen version content, while raw/bulk write hardening remains part of the broader Phase 1 invariant audit.

## 12. Evaluation Requests identify the exact Product Release being purchased for evaluation

An Evaluation Request now carries `product_release_id` in addition to `product_id`. The release is the exact product state for which the commercial request is made, matching the evaluation and validation model's release-specific trust boundary.

This avoids an ambiguity where a creator could purchase an evaluation for a Product while changing or selecting the actual release later. The Product remains the enduring entity; the Product Release is the concrete state being evaluated.

The implementation uses a foreign key to `product_releases` and exposes an `EvaluationRequest::productRelease()` relationship. The existing one-to-many relationship from Evaluation Request to Evaluation remains unchanged.

## 13. Product Releases become immutable once published

A Product Release is the concrete state against which an Evaluation and Validation are anchored. Therefore, once a release leaves `draft`, its identity and snapshot fields cannot be edited or deleted. Material changes require a new Product Release rather than rewriting the state behind an existing evaluation.

Publication and subsequent lifecycle changes are handled through `ProductReleaseStateTransition`, which records the transition and publication timestamp. Creator organization owners, admins and editors may perform these release lifecycle transitions; billing members cannot. The normal update/delete policy is restricted to draft releases.

An Evaluation Request must also reference a Product Release belonging to its selected Product. This invariant is enforced when the request is saved, preventing cross-product release references through ordinary Eloquent mutation.

As elsewhere in the trust domain, model-level protections do not defend against raw/bulk database writes. Those paths remain prohibited and are part of the broader invariant-hardening work.
