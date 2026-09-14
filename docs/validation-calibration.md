# Validation calibration operating model

## Purpose

Calibration measures the consistency and quality signals of completed Validation work without changing historical Evaluation, Decision, Report or Validation records. It is an operational review capability, not a second trust engine.

All aggregates are grouped by the immutable `Standard Version` recorded on each completed Evaluation. A later methodology version therefore produces a separate calibration population rather than reinterpreting older work.

## Measures

- **Completed evaluations**: count of completed Evaluations for the Standard Version.
- **Auditor evaluations**: count of submitted and locked Auditor Evaluations belonging to those completed Evaluations.
- **Insufficient-evidence rate**: proportion of submitted Auditor Evaluations whose evidence sufficiency is not `sufficient`.
- **Audience/promise coherence rate**: proportion of submitted Auditor Evaluations marked `coherent`.
- **Criterion agreement rate**: mean modal-assessment share for criterion observations with multiple submitted Auditor results. A single-Auditor observation is not treated as inter-Auditor agreement.
- **Overall-score mean**: arithmetic mean of persisted Evaluation overall scores.
- **Overall-score variance**: population variance of persisted Evaluation overall scores.
- **Validated rate**: proportion of completed Evaluations whose persisted decision is `validated`.
- **Decision outcomes**: counts by persisted Evaluation decision, including `unresolved` when legacy or anomalous data lacks a decision.
- **Recurring criterion disagreement**: per-criterion count of Evaluation/criterion observations where Auditor assessments differ, with a rate shown only when at least five observations exist.

## Insufficient-data rule

Five observations is the minimum reporting sample. Rates and score aggregates that require a population are returned as `null` below that threshold rather than being presented as percentages with false precision. Review flags identify insufficient evaluation, Auditor or agreement samples.

Criterion disagreement is considered a recurring review signal at a rate of at least 25% once at least five Evaluation/criterion observations are available.

These thresholds are operational review rules. They do not alter Validation decision thresholds and cannot change historical outcomes.

## Privacy and governance

The calibration surface exposes aggregate values, Standard Version identity and criterion codes only. It does not expose evidence, Auditor rationale, deliberation, private creator material or other restricted evaluation content.

Calibration review is restricted to platform administrators and is recorded through the existing `AuditLogger` as `validation.calibration_reviewed`. The audit entry stores the aggregate snapshot used for the review. Calibration observations do not mutate authoritative trust records.

## Interpretation

Calibration signals identify populations that warrant methodological or operational review. They are not Auditor performance scores, creator rankings, learner-outcome measurements or automated trust decisions.

Methodology changes are interpreted through their Standard Version boundary. Any material methodology change must use a new Standard Version rather than silently changing the meaning of historical observations.

## Ownership

Platform administrators own calibration review and governance decisions. Findings may inform a future methodology or product change, but any such change follows the governed product-iteration process and does not rewrite historical records.
