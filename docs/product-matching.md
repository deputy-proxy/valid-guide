# Product matching rules

Valid.guide matching connects products to explicitly requested audiences and use cases using deterministic, explainable rules.

## Suitability signals

The matching service may use these stored product attributes:

- product type, when explicitly requested;
- structured audience metadata;
- structured use-case metadata;
- subject area, when explicitly requested;
- language, when explicitly requested.

Level and format are not inferred because the current Product domain does not contain authoritative structured values for them.

## Validation gate

A product is eligible for an active validated match only when:

1. the product is active;
2. it has a current product release;
3. that current release has an active Validation.

Suspended, revoked and superseded validations are not active validated matches. Validation is evidence that the product/release passed the validation process. It is not evidence of universal suitability or guaranteed learner outcomes.

## Matching behavior

All requested criteria must match explicitly. Missing product metadata does not count as a match, and no suitability is inferred from free-form marketing claims or claimed outcomes.

Matching does not inspect pricing, payments, subscriptions, creator spend or paid placement. Commercial state therefore cannot change matching quality or validation trust claims.

The public match result contains only public product identity, current release identity, validation evidence and the reasons the explicit criteria matched. Private creator metadata is not included.
