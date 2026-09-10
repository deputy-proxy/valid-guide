# Implementation Decisions

## 2026-09-09 — Test database isolation and test classification

- Feature tests use Laravel's `RefreshDatabase` trait so the SQLite in-memory test database is migrated before database-backed tests execute and reset between tests.
- Tests that exercise Eloquent persistence and domain state-transition workflows are classified as Feature tests rather than Unit tests.
- The CI quality gate remains split into formatting, PHPStan/static analysis and the test suite so failures identify the failing quality layer directly.
- The PHPStan repair is retained without a baseline or global suppression. Static analysis must remain at zero errors.

## 2026-09-09 — Draft evaluation request completeness

- Draft evaluation requests may exist before commercial terms are frozen and before a specific product release is selected.
- `quoted_price` and `product_release_id` are therefore nullable at the persistence layer while the request is in draft.
- The payment transition remains responsible for enforcing frozen commercial terms before payment begins.
- When a product release is supplied, the model continues to enforce that it belongs to the requested product.
- Criterion guidance has an explicit persistence table because the model and relationship are part of the domain surface.

## 2026-09-09 — Lifecycle tests must use controlled transitions

- Tests and fixtures for lifecycle-controlled entities create records in their permitted initial state and use the corresponding domain service to establish later states.
- Where a test specifically needs an otherwise unreachable persisted state to exercise a downstream guard, the fixture may use a direct database update rather than weakening the production lifecycle invariant.
- Model instances are refreshed after service transitions before immutability assertions so tests observe the persisted state rather than stale in-memory attributes.

## 2026-09-09 — Controlled service writes and methodology rule validation

- Domain services may use locked query updates for lifecycle fields when the model intentionally rejects direct Eloquent mutation. This preserves the public invariant while keeping the controlled service path functional.
- An empty `weight_overrides` collection is valid methodology configuration; non-empty overrides must be associative and contain supported product types with non-negative numeric values.
- Runtime criterion applicability rejects malformed `product_types` collections instead of silently treating them as non-applicable.
- Evaluation decision rationale remains structured JSON containing the complete assessment, including blocker messages; consumers and tests should inspect the structured `blockers` field rather than treating the JSON as plain prose.

## 2026-09-09 — Persisted-state comparisons in lifecycle guards

- Lifecycle guards that compare enum-backed status fields use the raw persisted value so Eloquent enum casting cannot bypass immutability checks.
- Conflict declarations are immutable once determined.
- Clarification request identity and message content become immutable when the request is submitted; workflow fields such as response and resolution metadata remain controlled by the clarification workflow.
- These changes reinforce the already-decided lifecycle boundaries and do not alter the user-facing workflow states.

## 2026-09-09 — Remaining CI domain/fixture corrections

- `StandardVersion::standard()` explicitly uses `evaluation_standard_id`; the conventional Eloquent key inference would otherwise look for `standard_id` and leave the relationship unresolved.
- Conflict-declaration decisions remain restricted to platform administrators; the corresponding feature fixture now uses an admin actor rather than weakening the service authorization rule.
- Report lifecycle fields remain protected by the `Report` model. The delivery fixture establishes `current_version_id` through a direct database update because that state is required as setup for testing the separate `ReportDelivery` service and direct model mutation is intentionally forbidden.
- Criterion and criterion-guidance lifecycle guards resolve their owning `StandardVersion` through persisted identifiers and compare its persisted status. This keeps the immutability boundary tied to the actual domain state rather than to a potentially stale criterion-side attribute or cached status value.

## 2026-09-09 — Methodology content is immutable after scheduling

- Methodology content belonging to a `StandardVersion` is editable while that version is draft.
- Once the version is scheduled, its criteria and criterion guidance are immutable. They also cannot be added or deleted while the version is scheduled, effective or retired.
- `CriterionGuidance` enforces this invariant in its `save()` persistence boundary, rather than relying only on a particular Eloquent mutation event. The guard resolves the owning criterion and standard version from persisted identifiers and checks the persisted status before allowing the write.
- The standard-version lifecycle itself remains mutable through the controlled governance service (`draft → scheduled → effective → retired`); the immutability applies to methodology content, not to legitimate lifecycle transitions.
- Because `StandardVersion` casts `status` to `StandardVersionStatus`, guidance immutability checks that require a scalar persisted status read it directly from the database query builder. This avoids comparing a cast enum with a string return type and keeps the guard explicitly tied to persisted state.

## 2026-09-09 — Methodology assessment scale and versioned score anchors

- Criterion assessments are a controlled `CriterionAssessment` enum with exactly six values: `exceeds`, `meets`, `partially_meets`, `does_not_meet`, `insufficient_evidence`, and `not_applicable`.
- Numerical scores remain distinct from categorical assessments. The applicable `StandardVersion` stores immutable `score_anchors` and `decision_thresholds` JSON configuration so the scoring policy is versioned with the methodology rather than hard-coded in the decision engine.
- The default v1 numerical anchors are Exceeds 90–100, Meets 75–89, Partially Meets 50–74, and Does Not Meet 0–49. Insufficient Evidence and Not Applicable do not accept numerical scores.
- A scheduled/effective/retired `StandardVersion` cannot have its scoring configuration changed through ordinary Eloquent mutation. New versions receive the default v1 configuration unless explicitly configured before scheduling.
- `MethodologyRuleValidator` requires complete assessment coverage, continuous 0–100 numerical coverage, and three numeric decision thresholds before a Standard Version can be scheduled.
- `CriterionResult` enforces the mapping at persistence time: scored assessments require a score inside the applicable versioned anchor, while non-scored assessments require a null score. This prevents categorical/numerical contradictions from entering the evaluation history.
- `EvaluationDecisionService` consumes the applicable Standard Version's mandatory, dimension and overall thresholds instead of hard-coding those values.

## 2026-09-10 — Validation decision gates require explicit Auditor conclusions

- An `AuditorEvaluation` records two explicit, immutable decision-gate conclusions: `evidence_sufficiency` and `audience_promise_coherence`.
- Evidence sufficiency uses the controlled values `sufficient`, `insufficient` and `unresolved`. Audience/promise coherence uses `coherent`, `incoherent` and `unresolved`.
- Auditor evaluation submission requires both conclusions. This prevents a missing conclusion from being interpreted as a positive result.
- When a Product has claimed outcomes, an Auditor Evaluation must also contain at least one evidence record before submission. The decision engine independently checks evidence presence so incomplete historical data cannot silently validate.
- The final decision blocks validation when any submitted Auditor evaluation does not establish sufficient evidence or coherence with the stated audience and promise. Multiple-Auditor majority semantics remain delegated to the methodology-controlled voting work in #27 rather than being duplicated here.
- These gate conclusions are immutable once the Auditor Evaluation is locked, preserving the historical basis of the final decision.

## 2026-09-10 — Auditor voting is methodology-controlled

- Collective criterion determination is controlled by `Criterion.voting_mode`, persisted and therefore versioned through the owning `StandardVersion`.
- The domain exposes two modes: `individual` and `majority`. Criteria default to `individual`, so the existence of multiple Auditors never implicitly creates a vote.
- `CriterionVote` records are created only for criteria explicitly configured for `majority` determination. Independent `CriterionResult` records remain the primary historical Auditor assessments.
- Majority criteria require an odd number of distinct Auditor votes and use simple majority aggregation. Minority vote counts remain in the aggregate and the underlying immutable Auditor results remain untouched.
- Non-voting criteria do not create `CriterionVote` records. During final decision assessment, their independent Auditor results must agree categorically; disagreement or a missing result leaves the criterion unresolved and blocks validation rather than inventing a voting rule.
- Final decision scoring for non-voting criteria averages the independent Auditor scores only after their categorical assessments agree. Majority criteria continue to score from the winning vote positions.
- The final decision path idempotently records methodology-controlled votes for every submitted Auditor evaluation before resolving collective criteria. This ensures legacy or programmatically-created submitted evaluations cannot bypass the required vote records, while `CriterionVoting::record()` remains idempotent and never creates votes for individual criteria.
- Feature coverage explicitly exercises one, three and five Auditor scenarios for voting and non-voting criteria.

## 2026-09-10 — Auditor staffing is methodology-controlled

- Evaluation complexity is a controlled `EvaluationComplexity` enum with exactly four values: `simple`, `standard`, `complex`, and `exceptional`.
- The required Auditor count is versioned with the applicable `StandardVersion` through `auditor_staffing_rules`, rather than hard-coded in assignment or decision workflows.
- The v1 staffing rule is Simple = 1, Standard = 1, Complex = 3, Exceptional = 5. All configured counts must be positive and odd.
- `EvaluationRequest` casts complexity to `EvaluationComplexity`; evaluation staffing resolves the immutable complexity from the evaluation request and the staffing rule from the evaluation's methodology version.
- `AuditorStaffing` is the single domain service for resolving the required count and validating staffed/submitted Auditor counts. Assignment creation, assignment acceptance and final decision assessment use the same rule rather than maintaining separate copies.
- Assignment creation is transactionally protected by the existing evaluation row lock and cannot create an assignment beyond the methodology-required panel size.
- An Auditor assignment cannot transition from offered to accepted until the complete required Auditor panel is staffed. This makes the staffing invariant hold before Auditor work begins.
- Final decision assessment requires exactly the methodology-defined number of distinct submitted and locked Auditor evaluations. The previous positive-odd check remains as a defensive invariant, but oddness alone is no longer sufficient.
- Standard evaluations remain a one-Auditor panel. The agreed second-review capability must not turn the primary evaluation panel into an even two-Auditor decision; review/replacement semantics are therefore separate from the required panel count.
- Staffing configuration is validated before use, including missing and even counts, so malformed methodology data fails closed instead of silently changing the evaluation standard.

## 2026-09-10 — Prior Product participation is an Auditor conflict

- An Auditor is ineligible for a Product when they are an owner, administrator or editor of the Product's creator Organization, because those roles represent direct creator/contributor participation in the existing domain model.
- Prior Auditor participation is also a conflict when the same Auditor has previously been assigned to an Evaluation of the same enduring Product, including another Product Release.
- Billing-only organization membership does not constitute creator/contributor participation.
- The conflict check is performed by the domain-level Auditor assignment service during the existing transaction, after the Evaluation is reloaded under lock and before an assignment is created.
- Prior participation is resolved from persisted database state rather than loaded Eloquent relationship collections, so stale in-memory state cannot bypass the rule.
- A detected conflict is recorded in the audit log with the Evaluation, Auditor and determination actor before assignment is rejected. No assignment, assignment-level ConflictDeclaration or compensation record is created for the rejected Auditor.
- Feature coverage includes creator/contributor membership, billing-only membership, prior participation on the same Product, different Product participation, and stale relationship state.

## 2026-09-10 — Standard Version methodology completeness is validated before scheduling

- Methodology v1 defines seven fixed product-type weighting profiles: course, cohort course, guide, ebook, workshop, program and membership. `other` remains intentionally outside the fixed profiles because the methodology requires Admin to select and document an appropriate applicability profile for it.
- The canonical v1 profiles are represented as data in `MethodologyV1`; `MethodologyRuleValidator` consumes those profiles rather than embedding product-specific conditional branches.
- Criterion `weight` is treated as the base contribution to its methodology dimension. A product-specific `weight_overrides` value replaces that base contribution for the named product type. Effective criterion weights are summed by D1-D10 for each fixed v1 product type.
- Every fixed v1 product type must have positive coverage for all ten methodology dimensions, and its effective dimension weights must exactly match the approved v1 profile and total 100.
- A weight override cannot target a product type for which the criterion is not applicable. Mandatory product types cannot simultaneously be excluded, and the existing inclusion/must-be-applicable rule remains enforced.
- Criterion voting configuration remains versioned on the criterion. The validator accepts only the controlled `individual` and `majority` voting modes and does not duplicate the voting aggregation algorithm.
- These completeness checks run in `StandardVersionGovernance` immediately before `draft → scheduled`, so malformed methodology cannot be frozen into a published standard.
- Validation is version-specific: only criteria belonging to the Standard Version being scheduled contribute to its profile, and changing another Standard Version cannot affect the result.
- Feature coverage includes a complete valid v1 profile, missing D10 coverage, invalid product-type weights, invalid total weighting, and rejection at the scheduling boundary.

## 2026-09-10 — Phase 1 invariant regression audit completed

- Issue #33 is the final Phase 1 specification-to-code regression audit. The audit matrix is maintained in `docs/phase-1-invariant-audit.md` and is backed by the existing domain-specific Feature tests plus `Phase1InvariantAuditTest`.
- The audit verified that Phase 1 lifecycle, methodology, assessment, decision, voting, staffing, Auditor eligibility/conflict, evaluation history, reporting, refund, compensation, public verification and authorization boundaries have executable regression coverage at the application layer.
- Raw/bulk database mutations are treated as a controlled implementation boundary. The regression audit statically rejects `DB::table(...)->update/delete` and `DB::query(...)->update/delete` application paths outside `app/Services`, where controlled domain writes are intentionally implemented.
- Concurrency-sensitive workflows retain transaction and row-lock protections together with database uniqueness constraints. The SQLite in-memory test environment cannot faithfully reproduce every production database isolation/locking characteristic, so those database-specific semantics are documented rather than falsely represented as fully reproduced by the test suite.
- The audit confirmed that malformed methodology, unauthorized domain operations, invalid lifecycle states and incomplete decision prerequisites fail closed rather than silently producing trusted output.
- The Phase 1 quality gate remains formatting, PHPStan/static analysis and the complete test suite. The Issue #33 CI run passed all configured checks.
- No new Phase 1 architectural invariant was introduced by the audit. The purpose of Issue #33 was regression verification and documentation of the existing Phase 1 contract.

## 2026-09-10 — UI is a parallel workstream with separate presentation issues

- UI implementation is intentionally separated from the domain/application issues. Issues #41–#50 define and verify domain, application, commercial and workflow behavior; they should not become containers for detailed presentation implementation.
- UI issues may depend on completed domain/application capabilities and should implement the interface to those capabilities rather than duplicate lifecycle, tenancy, authorization, pricing, payment or refund rules.
- Filament is the primary internal application UI and should begin during Phase 2, incrementally as the corresponding backend capabilities stabilize. It is not deferred until Phase 2 is finished.
- External/public UI is developed as a separate surface from Filament. Its foundation may begin during Phase 2, while production verification rendering depends on the persisted Public Verification Snapshot and explicit public-read contracts.
- Public verification must render from the persisted snapshot rather than reconstructing historical public state from mutable internal records.
- Creator dashboard issue #49 defines the application-facing dashboard contract and state/action information; the actual Filament dashboard implementation is #55.
- The Phase 2 UI workstream is represented by #51–#58: UI foundation, Filament creator product/release management, Filament evaluation workflow, Filament commerce/refunds, creator dashboard presentation, external/public UI foundation, public verification experience and final UI regression/accessibility audit.
- The quality gate applies equally to UI work: Pint/lint, PHPStan and the relevant/full test suite must remain green. UI visibility is never treated as a substitute for server-side authorization.
