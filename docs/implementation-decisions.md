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
