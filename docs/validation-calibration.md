# Validation calibration and quality measurement

## Purpose

Calibration measures the performance of the Validation methodology over completed evaluations without changing historical Evaluation, Decision, Report or Validation records.

All metrics are calculated independently for each Standard Version. A change in methodology version therefore creates a separate measurement population instead of mixing observations from different standards.

## Minimum sample and insufficient data

The minimum sample for rate-based interpretation is **3 completed evaluations**.

- Fewer than 3 completed evaluations: the version is reported as `insufficient_data` and rate metrics are not manufactured from the small sample.
- Three or more completed evaluations: aggregate rates may be interpreted as calibration signals.
- Auditor agreement requires at least 3 comparable evaluation/criterion groups, each containing at least two locked Auditor results.
- Score variance is reported only when a comparable group contains at least two numeric scores.

A single Auditor evaluation does not create an inter-Auditor agreement observation. It remains part of the evaluation count but not the agreement denominator.

## Quality indicators

### Auditor agreement

For each completed evaluation and criterion, locked Auditor results form one comparison group. The group is unanimous when all available categorical assessments are identical.

`agreement rate = unanimous comparable groups / comparable groups × 100`

The rate is shown only when at least three comparable groups exist.

### Score variance

For comparable groups with at least two valid numeric scores, population variance is calculated from the Auditor scores. The displayed methodology-version value is the arithmetic mean of those group variances.

Invalid or missing numeric scores are excluded from variance and counted as anomalous data.

### Insufficient-evidence frequency

All persisted criterion results are considered. `Insufficient Evidence` results form the numerator and all criterion results form the denominator.

The percentage is shown only when at least three criterion results exist.

### Decision outcomes

Completed Evaluation decisions are counted by their persisted decision value. Counts are shown without inferring learner outcomes or causal quality claims.

### Recurring criterion disagreement

A criterion becomes a recurring calibration signal when it has at least two comparable evaluation groups and at least two of those groups contain Auditor disagreement. The page reports the criterion name, comparable evaluation count, disagreement count and disagreement rate.

## Anomalous data

The calibration service treats a scored Criterion Result with a missing, non-numeric or out-of-range score as anomalous. Such records are not used to manufacture a variance value and are surfaced through the administrator review flags.

Calibration does not repair anomalous records. Historical records remain authoritative and immutable.

## Privacy boundary

Calibration output contains only aggregate counts, rates, variance, methodology-version identifiers, criterion names and review flags. It does not expose evidence, evidence content, Auditor rationale, deliberation, creator material or individual Auditor identities.

## Governance

Only platform administrators may access the calibration page or record a calibration review. A material review is recorded through the existing `AuditLogger` as `calibration.reviewed` against the relevant Standard Version.

Calibration findings are advisory. They do not directly alter trust state, historical decisions, reports or validations. Methodology changes resulting from calibration must be introduced through a new Standard Version under the existing methodology-governance process.
