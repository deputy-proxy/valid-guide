# Implementation Decisions

## 2026-09-09 — Test database isolation and test classification

- Feature tests use Laravel's `RefreshDatabase` trait so the SQLite in-memory test database is migrated before database-backed tests execute and reset between tests.
- Tests that exercise Eloquent persistence and domain state-transition workflows are classified as Feature tests rather than Unit tests.
- The CI quality gate remains split into formatting, PHPStan/static analysis and the test suite so failures identify the failing quality layer directly.
- The PHPStan repair is retained without a baseline or global suppression. Static analysis must remain at zero errors.

## 2026-09-09 — Draft evaluation request completeness

- Draft evaluation requests may exist before commercial terms are frozen and before a specific product release is selected.
- `quoted_price` and `product_release_id` are therefore nullable at the persistence layer while the request is in draft.
- The payment transition remains responsible for enforcing frozen commercial terms before payment begins.
- When a product release is supplied, the model continues to enforce that it belongs to the requested product.
- Criterion guidance has an explicit persistence table because the model and relationship are part of the domain surface.
