# Issue #32 Implementation Decisions

## 2026-09-10 — Historical Evaluation Product identity

- `Evaluation` now persists `product_id` in addition to `product_release_id` and `standard_version_id`.
- `product_id` is populated from the persisted Product Release when an Evaluation is created without an explicit Product ID.
- The Product and Product Release must agree at Evaluation creation time.
- `product_id`, `product_release_id`, `standard_version_id` and `evaluation_request_id` are immutable after Evaluation creation.
- Existing records are backfilled from their Product Release during migration.
- The database column remains nullable for migration compatibility, but the Evaluation model rejects creation without a resolvable Product. This keeps the trust invariant at the domain boundary while allowing the migration to operate safely on existing data.

## 2026-09-10 — Evaluation lifecycle timestamps

- The approved Evaluation lifecycle timestamps are persisted as `started_at`, `submitted_at`, `internal_reviewed_at` and `completed_at`.
- `EvaluationStateTransition` is the controlled write path for these timestamps.
- Entering `in_progress` records `started_at`.
- Entering `internal_review` records `submitted_at`.
- Entering `ready_for_decision` records `internal_reviewed_at`.
- Completion records `completed_at`.
- These timestamps cannot be changed through ordinary Eloquent mutation because the Evaluation lifecycle is domain-controlled.

## 2026-09-10 — Evaluation publication representation

- The approved specification names `publication_status`, while the current implementation uses `published_at` on Evaluation and explicit publication state on Report/Public Verification Record.
- This is an intentional bounded-context deviation rather than a second Evaluation publication state machine.
- Evaluation completion and public publication are separate concerns. Report visibility and the persisted Public Verification Record remain the authoritative publication mechanisms for externally visible evaluation results.
- `published_at` is therefore retained for compatibility with the existing implementation and is not expanded into a new uncontrolled Evaluation publication workflow in issue #32.

## 2026-09-10 — Evaluation decision projection

- The approved specification models `EvaluationDecision` as the historical final decision record.
- The existing Evaluation model also retains `decision`, `overall_score` and `decision_rationale` as denormalized operational projections.
- These fields remain intentionally because the Phase 1 architecture already defines `EvaluationDecision` as the immutable source record and the parent Evaluation fields as a query projection.
- Decision history must never be reconstructed from mutable Auditor relationships.

## 2026-09-10 — Product Type representation

- The approved specification describes Product Type as controlled data. The current v1 implementation represents the fixed methodology taxonomy with the backed `ProductType` enum.
- This is an intentional implementation choice for v1 because the supported taxonomy and methodology profiles are currently fixed and Admin-managed taxonomy creation is not a product requirement.
- Methodology rules consume the canonical enum values and `MethodologyV1` data rather than embedding product-type branches in UI code.
- `other` remains a controlled enum value and is intentionally outside the seven fixed v1 weighting profiles. Its applicability must be explicitly handled by methodology administration.
- Introducing a `product_types` table is deferred until dynamic taxonomy management becomes a real domain requirement rather than added solely to mirror terminology in the architecture document.

## 2026-09-10 — Product URL naming

- The approved specification calls the Product URL `canonical_url`; the current implementation persists the equivalent value in the `url` column.
- The `url` field is treated as the Product's canonical URL and is not a second mutable URL concept.
- Renaming the persisted column is deferred because no separate URL semantics exist and the current field is already established throughout the implementation. A future schema cleanup may rename it without changing domain meaning.

## 2026-09-10 — Historical trust-boundary rule

- Historical Evaluation identity must use persisted foreign keys for Product, Product Release and Standard Version.
- Current Product or Standard Version relationships may be used for presentational context, but they must not be used to reconstruct what was historically evaluated.
- Public Verification Record remains a persisted projection/snapshot and is not reconstructed from mutable internal state at render time.
