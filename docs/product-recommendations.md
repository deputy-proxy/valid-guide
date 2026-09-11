# Product recommendations

Valid.guide recommendations are a relevance aid built on the public directory projection. They are not a quality ranking, outcome prediction, endorsement, paid placement mechanism or guarantee of suitability.

## Eligibility

A recommendation candidate must:

- be visible in the public directory;
- have an active Validation status in the public directory projection; and
- expose only data already permitted by that public projection.

Suspended, revoked or superseded Validation records are excluded. A Validation for an older release does not make a newer current release eligible unless the public projection contains an active Validation for that current release.

## Ranking

Recommendations use only explicit public matching signals supplied by the discovery context. Each matching signal contributes one point:

1. audience;
2. use case/goal;
3. product type;
4. subject area;
5. language.

A product with more matching signals ranks above one with fewer matching signals. Ties are resolved by title and then verification identifier, both case-insensitively, so the same input produces stable ordering.

The numeric score is an internal relevance mechanism. It is not presented as a product-quality or Validation score.

A text search query narrows the candidate set to the same public title, creator or subject-area search fields used by the directory. Other supplied criteria contribute to recommendation relevance rather than silently changing Validation status.

## Explanations

Every recommendation includes reasons derived from the exact public signals that contributed to its score, plus the current validated release identifier.

Explanations must not claim guaranteed learner outcomes, universal suitability, superiority or product quality beyond the evidence represented by the public Validation record.

## Incomplete and ambiguous data

Missing audience, goal, subject-area or language metadata receives no match credit. Missing data does not cause an exception or create an inferred match.

If no matching signals are supplied, eligible validated products are still returned in deterministic order so the directory can degrade safely into public discovery. If no eligible products exist, the recommendation module is omitted and the directory's existing no-results state is shown.

## Commercial independence

Recommendation ranking does not use price, payment state, affiliate relationships, sponsorship, commercial relationships or other commercial prominence signals. Those values are not part of the recommendation result.

Recommendation relevance does not alter Validation status, trust claims or verification records.

## Privacy and analytics boundary

The recommendation result is built from `PublicDirectoryEntry`, which is the public information boundary established by the public directory. Private creator information and internal evaluation data are not loaded by the recommendation service.

The current application has no analytics subsystem for recommendation events. The recommendation implementation therefore does not introduce behavioral tracking, click-based ranking or any event that could become a hidden ranking incentive. If analytics are added later, recommendation events must remain observational and must not feed ranking or Validation decisions.
