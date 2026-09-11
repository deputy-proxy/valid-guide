# Public directory

The public directory is a discovery projection of persisted public verification records. It is not an alternative verification record and does not reconstruct historical trust state from mutable internal evaluation data.

## Eligibility

A directory entry is discoverable only when its persisted public verification snapshot is marked directory-visible and its validation status is `active`.

Revoked, suspended and superseded validation states are therefore not presented as currently validated products. The authoritative verification page remains available for public verification records and their status history.

## Discovery signals

The directory supports deterministic discovery using public data only:

- product title;
- creator name;
- subject area;
- product type;
- language;
- controlled audience metadata;
- controlled use-case metadata.

Search is case-insensitive for subject area and language and performs a text contains search across title, creator name and subject area. Multiple filters are combined with AND semantics.

Invalid enum filter values are treated as absent rather than creating an inferred match.

## Ordering

Results are ordered by product title and then verification identifier. Commercial state, pricing, payment, subscriptions, creator spend, affiliate value and other paid placement signals are not used.

## Public boundary

Only fields explicitly persisted into the public verification snapshot and directory projection are exposed. Private creator information, internal auditor deliberation, evidence and operational/payment data are not part of the directory projection.
