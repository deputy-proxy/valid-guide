# Issue #33 Implementation Decisions

## Marketplace and Validation independence

Marketplace services and transactions are commercial records. They do not create, update, score, publish, revoke or otherwise mutate Validation, Evaluation Decision, Auditor Evaluation or public trust records.

Marketplace lifecycle transitions remain inside `MarketplaceServiceManagement` and `MarketplaceTransactionService`. Validation lifecycle transitions remain in their existing domain services.

## Commercial conflict rule

An Auditor must not be assigned to an Evaluation when the Auditor has a recorded marketplace transaction with the organization that owns the evaluated Product.

The rule is enforced server-side by `MarketplaceIndependence` from the Auditor assignment creation path. It is based on persisted marketplace transactions and persisted Product organization identity rather than client-provided conflict declarations.

The conflict is fail-closed. A transaction remains evidence of a commercial relationship even if it was subsequently cancelled or refunded, because the historical relationship itself can compromise perceived independence.

A marketplace relationship with an unrelated organization does not block an assignment.

## Commercial influence and ranking

Marketplace discovery does not rank services using transaction volume, transaction value, commissions, price, sponsorship, Validation status, community activity or other commercial prominence signals.

Marketplace participation is not a Validation quality signal and does not change Auditor methodology eligibility, scoring or decision thresholds.

## Disclosure

Public marketplace pages explicitly describe marketplace activity as separate from Validation decisions, scoring, Auditor assignment, recommendations and public trust records.

The marketplace does not currently have a persisted sponsorship/promotional-placement model. If sponsored content is introduced later, sponsorship must be explicitly disclosed and must not influence Validation, matching, recommendation or trust outcomes.

Internal conflict records, governance notes and audit metadata remain private.

## Administrative governance

Marketplace governance actions are exposed to platform administrators through dedicated Filament resources. Server-side authorization uses the existing `isPlatformAdmin()` authority and existing marketplace domain services.

Marketplace service moderation is limited to controlled pause/archive transitions. Marketplace transaction refunds remain controlled by `MarketplaceTransactionService` and are administrator-authorized by the existing transaction policy.

Governance actions are audit logged through the existing `AuditLogger` path. No second audit mechanism is introduced.

## Abuse and fraud safeguards

Marketplace owners cannot purchase their own services. Organization purchases remain restricted to authorized organization roles. Marketplace transaction commercial snapshots remain immutable after completion, and transaction lifecycle changes remain controlled by `MarketplaceTransactionService`.

Administrative governance cannot directly edit marketplace status, transaction amount or other historical commercial fields through unrestricted CRUD.
