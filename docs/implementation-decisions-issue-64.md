# Issue #64: Evidence-driven product iteration

## Decision

Valid.guide adopts a documented evidence-to-change loop for material product, methodology, trust, workflow and policy changes. Quality, operational and product-usage evidence may identify candidate improvements, but those signals are advisory and cannot directly mutate Validation outcomes, public trust state or historical records.

Material changes must be classified by historical impact before implementation. Methodology and trust/public-record semantic changes require explicit versioning or compatibility treatment so completed evaluations, reports and public verification records retain the meaning established when they were created.

## Evidence inputs

The evidence stage may consume:

- calibration and quality-measurement outputs from Phase 6 methodology work;
- operational metrics and reliability observations from the Phase 6 operational work;
- product-usage observations that are appropriate for product improvement.

These inputs remain separate from authoritative Validation state. In particular, commercial performance, subscription/payment outcomes, popularity, traffic, engagement, conversion, retention and experiment assignment are not Validation inputs.

## Historical-impact classification

Every material candidate is classified as one of:

1. **Presentation-only** — does not alter authoritative record meaning.
2. **Operational** — changes internal workflow, monitoring, retries or diagnostics without rewriting domain history.
3. **Domain behavior** — changes lifecycle, validation, authorization or persistence semantics and therefore requires explicit review of completed records.
4. **Methodology** — changes criteria, weighting, scoring, thresholds, applicability, voting or staffing semantics and therefore requires a distinct `StandardVersion` when authoritative.
5. **Trust/public-record semantics** — changes Validation meaning, public verification identity, publication or disclosure semantics and therefore requires explicit compatibility/version treatment.

When classification is ambiguous, the more conservative classification is used.

## Historical compatibility

Completed Evaluations continue to use the Standard Version under which they were performed. A later methodology version does not reinterpret an earlier Evaluation or Decision.

Public verification remains based on the persisted public snapshot rather than reconstructing historical public meaning from mutable internal records. Changes to public semantics therefore require an explicit compatibility boundary instead of silently changing the interpretation of an existing public record.

Operational and presentation changes must not be used to rewrite completed authoritative records. Rollback of a methodology or trust change must establish the correct subsequent version/state rather than editing the historical version in place.

## Governance and authorization

The evidence-to-change process is a decision and change-control convention, not a second persistence workflow. Existing server-side domain boundaries remain authoritative.

- `StandardVersionGovernance` is the enforcement boundary for methodology lifecycle changes.
- Only platform administrators may govern Standard Version transitions.
- Scheduling validates the complete methodology before the version can become authoritative.
- Scheduled/effective/retired methodology content is immutable.
- Governance transitions record approval and an audit event.
- Existing Public Verification publication services control changes to persisted public projections.

No new governance model, migration or admin surface is introduced by Issue #64 because these existing mechanisms already provide the required authoritative enforcement.

## Experiments

Experiments may be used only for explicitly non-authoritative product behavior. Experiment membership must not determine an Evaluation result, Decision, Validation status or Public Verification identity.

An experiment may therefore inform product decisions through evidence collection, but it cannot become an implicit trust rule.

## Ownership and rollout

- The relevant quality, operational or product owner owns evidence collection.
- The affected product/domain owner records the decision and historical-compatibility treatment.
- Platform administration approves methodology or authoritative trust changes through the existing server-side governance boundary.
- The implementation owner verifies regression coverage and the complete CI pipeline before merge.

Each material decision records rollout signals and a rollback strategy. Rollback must preserve historical interpretation.

## Regression coverage

Issue #64 introduces documentation and change-control rules without changing executable application behavior. Existing regression coverage therefore remains the enforcement evidence rather than adding artificial tests for documentation.

Relevant existing coverage includes:

- `tests/Feature/StandardVersionGovernanceTest.php` for administrator authorization, methodology validation, lifecycle transitions, audit logging and immutability;
- Issue #31 public-verification regression coverage for persisted snapshot semantics and historical verification identity;
- operational-metrics coverage for read-only operational evidence boundaries.

The implementation must not weaken these existing tests or introduce a parallel analytics/trust data path.

## Consequence

Future material product changes must carry an explicit evidence and decision trail. Analytics and commercial signals remain evidence about the system rather than hidden authority over Validation. Historical trust meaning remains tied to the versioned and persisted records that established it.
