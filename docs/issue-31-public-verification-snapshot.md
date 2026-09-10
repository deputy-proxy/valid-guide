# Issue #31: Public Verification Snapshot

## Implementation decisions

- `PublicVerificationRecord.snapshot` is the canonical persisted public projection. The public verification page must not reconstruct its authoritative content by querying mutable internal records.
- Snapshot schema version `1` is explicit through `schema_version`, allowing future projection changes without ambiguity.
- Product identity is release-specific and includes the release identifier and version, using the immutable release title snapshot and creator organization name.
- Standard identity is pinned to the exact `StandardVersion` used by the Evaluation.
- Criterion-level public results are projected from immutable Auditor `CriterionResult` records. For collective criteria, the persisted criterion votes determine the public assessment while linked criterion-result scores are aggregated for the public score.
- The snapshot contains the evaluation scope through complexity and the complete criterion definitions required to explain what was evaluated.
- Strengths and weaknesses are persisted as first-class public arrays while the underlying findings remain represented in the snapshot.
- Auditor count is always persisted. Auditor identities and credentials are disclosed only for approved Auditor profiles, avoiding exposure of unapproved participants or private account fields such as email addresses.
- The report abstract comes from the current immutable Report Version rather than creator-supplied marketing copy. Full-report publication remains controlled by `PublicVerificationRecord.full_report_visible`.
- Verification status history is represented from the Validation lifecycle timestamps and status reason. Validation identity remains unchanged when status moves to suspended, revoked or superseded.
- `PublicVerificationPublication` owns snapshot publication and synchronization. `ValidationStateTransition` invokes that service so trust-state changes update the persisted public projection through the controlled workflow.
- Public record identity and publication provenance remain immutable. Directory and full-report visibility are controlled fields and are also persisted into the snapshot so the snapshot is self-contained.
- No new public disclosure database field was introduced because the current Auditor Profile schema has no explicit disclosure-consent field. Approved-profile disclosure is therefore the conservative policy boundary for this phase.

## Regression coverage

The Issue #31 tests cover complete snapshot structure, criterion results, findings, strengths, weaknesses, Auditor disclosure, full-report visibility synchronization, revoked publication rejection, and historical verification identity preservation across a controlled revocation.
