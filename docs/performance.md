# Performance targets and verification limits

This document records repository-level performance contracts that can be checked without pretending CI is a production load test. Human beings have invented latency targets, then discovered databases exist.

## Critical-path targets

| Path | Repository target | How it is verified |
| --- | --- | --- |
| Public directory search | One paginated `PublicDirectoryEntry` query shape, with only the public projection loaded | `PublicDirectory` service inspection and existing public-directory feature coverage |
| Public directory recommendations | One bounded candidate query, capped at 50 rows, with only fields required for scoring and rendering | `ProductRecommendations` service inspection and existing recommendation feature coverage |
| Public directory ordering | Filtering by visibility/status and deterministic title/verification ordering should use one composite index | Migration inspection; production query-plan verification remains deployment-specific |

The public directory uses Laravel pagination, so a paginated request consists of the count and page-select statements. The target is therefore about avoiding additional per-row queries or unbounded hydration, not forcing pagination into one SQL statement.

## Changes in this phase

- Public directory results now select only the fields required by the public projection.
- Recommendation candidates now select only the fields required by matching and explanation generation.
- Recommendation candidates remain bounded by the existing 50-row limit before in-memory scoring.
- The directory's existing `(directory_visible, validation_status)` index is replaced with a composite index that also covers deterministic ordering by `title` and `verification_identifier`.
- No caching was introduced. Public verification and directory visibility are mutable trust state backed by the persisted public snapshot, so caching them without an explicit invalidation contract would create a correctness risk.

## Residual verification limits

CI can validate migrations, static analysis and functional behavior against the repository's SQLite test database, but it cannot establish:

- production latency under representative traffic;
- the query planner's chosen index on the production database engine;
- memory behavior for production-scale directory datasets;
- cache or network effects outside the application process.

Those require deployment-specific query-plan inspection and load testing. They are deliberately not represented as completed by this phase.
