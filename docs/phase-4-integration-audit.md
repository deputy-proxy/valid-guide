# Phase 4.7 Integration Audit

## Purpose

This audit closes the Phase 4 Expert lifecycle by verifying the integration boundaries between the Expert Board, public expertise, opportunities, community participation and marketplace activity.

The audit treats commercial activity, community activity and public projections as separate concerns. Validation, Evaluation Decision, Auditor assignment and public trust records remain authoritative and are not derived from marketplace or community prominence.

## Operating model

```text
Expert Board
    |
    v
Expert profile & expertise
    |
    +----> Expert opportunities / engagement
    |
    +----> Community participation / moderation
    |
    +----> Marketplace services / transactions
```

The branches above are independent projections/workflows. Eligibility is derived from the governed Expert lifecycle, while commercial and community records remain outside Validation outcomes.

## Integration matrix

| Area | Primary boundary | Required invariants | Regression coverage |
|---|---|---|---|
| Expert Board | `ExpertBoardGovernance` / membership records | Only governed Experts can progress; governance actions are server-authorized and auditable | `ExpertBoardGovernanceTest.php`, `ExpertBoardMembershipUiTest.php` |
| Expert profile & expertise | `ExpertPublicProfilePublication` / `PublicExpertDirectory` | Only approved, currently eligible and published profiles are public; private governance data is excluded | `AuditorProfileGovernanceTest.php`, `PublicExpertDirectoryTest.php` |
| Expert opportunities | opportunity governance and participation services | Eligibility, ownership, capacity, cancellation and recovery are enforced server-side | `ExpertOpportunityTest.php`, `ExpertOpportunityParticipationTest.php` |
| Community participation | `CommunityContributionGovernance` / `PublicCommunityDirectory` | Moderation is administrative, reports are private, unpublished/removed content is not public, community activity cannot affect Validation | `CommunityContributionTest.php` |
| Marketplace services | `MarketplaceServiceManagement` / `MarketplaceDiscovery` | Only eligible Experts publish; discovery uses public suitability/trust signals and not commercial prominence | `MarketplaceTest.php`, `MarketplaceUiTest.php` |
| Marketplace transactions | `MarketplaceTransactionService` | Buyer/provider authorization, frozen commercial snapshots and lifecycle integrity are enforced; Validation is untouched | `MarketplaceTest.php` |
| Commercial independence | `MarketplaceIndependence` / assignment creation | Any historical transaction with the evaluated Product organization is a fail-closed conflict; unrelated organizations do not block assignment | `MarketplaceGovernanceTest.php`, `AuditorAssignmentCreationTest.php` |
| Governance | dedicated Filament resources + domain services | Platform administration is server-authorized; status/history mutations remain behind domain workflows; governance is audited | `MarketplaceGovernanceTest.php`, `AuthorizationTest.php`, `AuditLogTest.php` |
| Notifications & action queues | workflow notification/action queue services | Notifications and actions derive from authoritative state and do not become an alternate mutation path | `WorkflowNotificationTest.php`, `ExpertOpportunityParticipationTest.php` |
| Public privacy & disclosure | public directory/controllers/views | Public projections contain only explicitly public profile/community/marketplace data and disclose commercial independence | `PublicExpertDirectoryTest.php`, `CommunityContributionTest.php`, `MarketplaceGovernanceTest.php`, `MarketplaceUiTest.php` |
| UI/accessibility | public/expert/internal surfaces | Authentication, authorization, empty/error states and core accessibility landmarks remain intact | `ExpertBoardMembershipUiTest.php`, `MarketplaceUiTest.php`, `PublicUiFoundationTest.php`, `Phase2UiAuditTest.php` |

## Phase 4 invariants

1. **Eligibility is authoritative.** Expert-facing participation requires the current governed Expert state. Client payloads cannot establish eligibility.
2. **Historical meaning is preserved.** Auditor assignment, evaluation, compensation and completed marketplace transaction records are not rewritten through later Phase 4 workflows.
3. **Commercial independence is fail-closed.** A historical marketplace relationship with the Product organization remains a conflict even after cancellation or refund.
4. **Commercial activity is independent of Validation.** Marketplace services and transactions do not create, score, publish, revoke or otherwise mutate Validation, Evaluation Decision, Auditor Evaluation or public trust records.
5. **Community activity is independent of Validation.** Community publication, reports, moderation and removal do not alter Validation outcomes or recommendation ranking.
6. **Discovery is not pay-to-prominence.** Marketplace discovery does not use price, transaction volume/value, commissions, sponsorship, Validation state or community activity as ranking inputs.
7. **Public projections are allowlisted.** Internal conflict records, moderation evidence, governance notes and audit metadata remain private.
8. **Governance is auditable.** Administrative moderation and independence decisions leave immutable audit evidence.
9. **Query boundaries are explicit.** Expert, community and marketplace public directories eager-load their displayed relationships and paginate the result set rather than traversing relationships per row.
10. **Domain workflows remain the mutation boundary.** Direct CRUD cannot bypass lifecycle rules for marketplace, community or Expert governance state.

## Query/performance review

The initial scope uses paginated public directories with eager-loaded relationships:

- `PublicExpertDirectory` eager-loads the Expert profile and board membership.
- `PublicCommunityDirectory` eager-loads the Expert public profile.
- `MarketplaceDiscovery` eager-loads the Expert public profile.

Discovery is ordered by stable public fields and paginated to 12 records. No transaction-volume or commercial-value query is used for marketplace ranking.

This is considered acceptable for the initial scope. If scale requires a search index or denormalized public projection later, it must preserve the same authorization, privacy and independence invariants.

## Failure, cancellation and recovery review

The covered workflows explicitly test rejected participation, moderation rejection/edit/republication, duplicate reports, report resolution/dismissal, marketplace cancellation/refund, transaction lifecycle transitions, administrator-only governance, commercial conflict failure and unrelated-organization success paths.

## Final audit result

Phase 4 implementation is considered integration-complete when this matrix remains backed by the listed feature tests, the source-level independence/query guards remain green, and the repository-wide lint, type, test and CI checks are green.

No Phase 4 workflow should introduce a second source of truth for Validation, trust, Expert eligibility or public disclosure.
