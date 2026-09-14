# Evidence-driven product iteration

## Purpose

Valid.guide uses a controlled product-iteration loop so measured quality, operational evidence and product-usage evidence can identify improvement opportunities without becoming hidden inputs to Validation outcomes or public trust state.

The iteration process is advisory until an explicit decision authorizes a change. Analytics, commercial performance and user popularity are evidence about the product and its operation, not authority to change what constitutes a valid evaluation.

## Evidence-to-change loop

The project uses the following sequence for material product decisions:

1. **Gather evidence** — collect quality, calibration, operational and product-usage observations from their authoritative sources.
2. **Frame the candidate** — describe the observed problem, affected surface, proposed change and evidence supporting it.
3. **Classify historical impact** — determine whether the candidate is presentation-only, operational, domain behavior, methodology, or trust/public-record semantics.
4. **Decide** — record the decision, rationale, owner, approval authority, compatibility treatment and rollout/rollback plan before implementation.
5. **Implement** — make only the approved change through the existing application/domain boundary.
6. **Verify** — review regression coverage and the complete CI pipeline. Material changes must also verify their historical-compatibility boundary.
7. **Roll out and observe** — release the approved change while continuing to treat operational and usage metrics as evidence rather than trust inputs.
8. **Record the outcome** — retain the decision and implementation references so the reasoning remains inspectable after rollout.

No step permits an analytics or commercial signal to mutate a Validation, Evaluation, Decision, Report or Public Verification record directly.

## Historical-impact classification

| Class | Examples | Historical rule |
| --- | --- | --- |
| Presentation-only | Copy, layout, non-authoritative navigation or display improvements | May change current presentation when it does not change the persisted meaning of an authoritative record. |
| Operational | Workflow scheduling, diagnostics, retries, monitoring or internal process changes | Must not rewrite completed domain records or make historical outcomes depend on current operational configuration. |
| Domain behavior | Lifecycle, authorization, validation or persistence behavior | Must preserve existing completed-record semantics. Changes that alter interpretation require explicit compatibility treatment and regression coverage. |
| Methodology | Criteria, weighting, scoring, thresholds, applicability, voting or staffing rules | Must be represented by a distinct methodology version before the changed rules become authoritative. Existing evaluations continue to point to the Standard Version under which they were performed. |
| Trust/public-record semantics | Validation meaning, public verification identity, publication or disclosure rules | Must use explicit versioning or compatibility treatment and must never silently reinterpret a completed public record. |

When classification is ambiguous, use the more conservative category and require explicit compatibility treatment.

## Decision record

Every material change receives a record in `docs/implementation-decisions.md`. At minimum, the record identifies:

- the date and issue/change being decided;
- the evidence and problem statement;
- the historical-impact class;
- the decision and rationale;
- the authoritative owner and required approval authority;
- whether a methodology, snapshot, schema or compatibility version is required;
- affected historical records and the rule used to preserve their interpretation;
- experiment boundaries, when applicable;
- rollout, monitoring and rollback considerations;
- the implementation and regression coverage that establish the decision in the application.

A decision record is not approval to change authoritative trust data by itself. The existing domain authorization and controlled state-transition services remain the enforcement boundary.

## Methodology and historical compatibility

Methodology changes are versioned through `StandardVersion`. A completed Evaluation remains associated with the Standard Version used for that evaluation; current methodology configuration must not be substituted when reading or explaining historical results.

A methodology change therefore follows this boundary:

1. document the proposed change and evidence;
2. determine whether the change affects methodology semantics;
3. create or configure a distinct Standard Version when semantics change;
4. validate and approve the version through the existing governance service;
5. preserve existing Evaluation and Decision references to their original version;
6. update public projections only through their controlled publication boundary.

Presentation improvements may explain an existing record differently only when the underlying authoritative meaning is unchanged. If a proposed presentation change would alter the interpretation of a historical result, it is a trust/public-record change and requires explicit compatibility treatment instead.

## Analytics, commercial signals and experimentation

Phase 6 calibration and operational analytics are inputs to the evidence-gathering stage. They are not runtime ranking, scoring, validation or public-trust signals.

The following are explicitly prohibited as direct Validation inputs:

- revenue, subscription or payment performance;
- product popularity, traffic or engagement;
- conversion or retention metrics;
- recommendation clicks or commercial outcomes;
- experiment assignment or treatment exposure.

Experiments must remain outside authoritative trust records. An experiment may change presentation, workflow ergonomics or other explicitly non-authoritative behavior, but experiment membership must not determine an Evaluation result, Decision, Validation status or Public Verification identity.

Operational metrics remain read-only evidence. The existing `OperationalMetrics` service aggregates persisted operational sources and does not mutate Evaluation, Decision, Validation, ranking or recommendation state.

## Ownership and approval

- **Evidence owner:** the team responsible for the relevant quality, operational or product signal records the evidence and candidate change.
- **Decision owner:** the owner of the affected product or domain surface records the decision and compatibility treatment.
- **Methodology/trust approver:** platform administration approves changes that freeze a new Standard Version or govern authoritative trust semantics through the existing server-side governance boundary.
- **Implementation owner:** the engineer implementing the approved issue is responsible for keeping the code, tests and documentation aligned with the decision.

Material changes must not be approved solely by the metric or stakeholder whose commercial or usage outcome would benefit from the change.

## Rollout and rollback

Before rollout, the decision record must identify:

- the authoritative records affected;
- the compatibility/version boundary;
- the observable signals used to detect an unsuccessful rollout;
- the safe rollback action.

Rollback must not restore a mutable configuration in a way that changes the historical meaning of completed records. If a methodology or public-record semantic change has already become authoritative, rollback means establishing the appropriate subsequent version/state rather than editing the historical version in place.

## Existing enforcement and regression coverage

This governance model deliberately reuses existing application boundaries instead of introducing a parallel governance datastore.

- `StandardVersionGovernance` restricts methodology lifecycle changes to platform administrators, validates methodology completeness before scheduling, records approval and writes an audit event for the state transition.
- Scheduled, effective and retired methodology content is protected by the existing immutability rules.
- Public Verification uses the persisted `PublicVerificationRecord.snapshot` as the canonical public projection and preserves its historical identity through controlled publication.
- Operational analytics is read-only and does not mutate authoritative Validation state.
- Existing Feature coverage in `StandardVersionGovernanceTest`, the public-verification snapshot tests and operational-metrics tests provides regression protection for these boundaries.

No new database model or migration is required for Issue #64 because the repository already has the durable audit, methodology-versioning and public-snapshot mechanisms needed to enforce the documented governance rules.

## Change-control checklist

Before implementation of a material change, confirm:

- [ ] Evidence sources are authoritative and documented.
- [ ] The historical-impact class is explicit.
- [ ] A decision record exists before implementation.
- [ ] Required approval authority is identified.
- [ ] Historical Evaluation, Decision, Report and Public Verification meaning is preserved.
- [ ] A new methodology or compatibility version exists when semantics change.
- [ ] Analytics, commercial and experiment signals are excluded from authoritative trust decisions.
- [ ] Rollout and rollback behavior are documented.
- [ ] Relevant regression coverage exists.
- [ ] The complete repository CI pipeline is green.
