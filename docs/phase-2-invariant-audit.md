# Phase 2 Invariant Audit

> Project: Valid.guide Validation
> Issue: #50
> Dependencies: #41 through #49

## Purpose

This is the final Phase 2 specification-to-code regression matrix. Each Phase 2 trust-sensitive requirement is mapped to executable Feature coverage or to an explicit application-layer testing limitation.

## Audit matrix

| Area | Phase 2 invariant | Enforcement / regression coverage |
|---|---|---|
| Tenancy | Creator records are scoped to the authenticated Organization | `AuthorizationTest`, `CreatorEvaluationRequestWizardTest`, `CreatorDashboardTest`, `ProductManagementTest` |
| Authorization | Owner/Admin/Editor/Billing permissions are enforced server-side | `AuthorizationTest`, `ProductManagementTest`, `EvaluationRequestLifecycleIntegrityTest`, `CreatorDashboardTest`, `CreatorRefundServiceTest` |
| Product | Product lifecycle is controlled and historical Products are not destructively rewritten | `ProductManagementTest` |
| Product Release | Releases belong to the selected Product and remain immutable after leaving draft | `ProductReleaseLifecycleTest` |
| Product Release | Evaluation Requests retain the exact selected Product Release | `EvaluationRequestLifecycleIntegrityTest`, `CreatorEvaluationRequestWizardTest` |
| Evaluation Request | Lifecycle transitions reject invalid, unauthorized and cross-tenant operations | `EvaluationRequestLifecycleIntegrityTest`, `CreatorEvaluationRequestWizardTest` |
| Commercial snapshot | Package, complexity, quoted amount and currency are calculated server-side and frozen before payment | `EvaluationRequestCommercialTermsTest`, `EvaluationQuoteServiceTest`, `CreatorEvaluationRequestWizardTest` |
| Price integrity | Client input cannot replace the authoritative quote used for payment | `EvaluationRequestCommercialTermsTest`, `StripePaymentFlowTest` |
| Payment | Payment is separate from Evaluation creation and Validation | `StripePaymentFlowTest`, `EvaluationRequestLifecycleIntegrityTest` |
| Stripe | Webhook confirmation requires provider verification and is idempotent | `StripePaymentFlowTest` |
| Stripe | Duplicate or ineligible payment attempts cannot advance the request twice | `StripePaymentFlowTest`, `EvaluationRequestLifecycleIntegrityTest` |
| Refund | Refund eligibility ends at `reports.delivered_at`, not at evaluation outcome | `CreatorRefundServiceTest`, `ReportDeliveryTest` |
| Refund | Duplicate/concurrent refund attempts cannot create duplicate internal/provider refunds | `CreatorRefundServiceTest` |
| Refund | Provider failure does not falsely mark an internal refund as completed | `CreatorRefundServiceTest` |
| Submitted Material | Creator material submission is tenant-scoped, validated and provenance-aware | `EvaluationMaterialIntakeTest` |
| Submitted Material | Verified material is required for readiness | `EvaluationMaterialIntakeTest` |
| Submitted Material | Material records become immutable at the defined readiness boundary | `EvaluationMaterialIntakeTest` |
| Privacy | Creator surfaces do not expose private Auditor evidence, deliberation or internal decisions | `CreatorDashboardTest`, `CreatorEvaluationRequestWizardTest` |
| Dashboard | Dashboard state and permitted next actions are derived from backend contracts | `CreatorDashboardTest` |
| Wizard | Draft intake can resume without bypassing tenancy, release, commercial or readiness rules | `CreatorEvaluationRequestWizardTest` |
| Raw/bulk writes | Application workflows do not use uncontrolled raw/bulk mutation outside controlled services | `Phase2InvariantAuditTest` |
| Historical integrity | Later catalog/product changes do not rewrite frozen request terms or release identity | `EvaluationRequestLifecycleIntegrityTest`, `ProductReleaseLifecycleTest`, `EvaluationRequestCommercialTermsTest` |
| Concurrency | Locking and uniqueness protect payment/refund/readiness boundaries | Service-level transactions and database constraints; SQLite limitation documented below |
| Quality | Formatting, PHPStan and complete Pest suite are mandatory CI gates | `.github/workflows/tests.yml`, `composer.json` |

## Audit conclusions

### Organization tenancy and authorization

Creator application boundaries resolve organization context from authenticated membership and reject forged organization/resource identifiers. Billing members retain commercial permissions without gaining product or evaluation-content access. Platform-admin operations remain outside creator organization membership.

### Product and release history

Products are enduring entities and are archived rather than destructively removed. Product Releases identify the exact evaluated state and become immutable outside their controlled lifecycle. Evaluation Requests retain their selected release instead of following later mutable Product state.

### Evaluation Request and commercial integrity

The request lifecycle is controlled by domain/application services. Package selection, complexity and pricing are authoritative server-side values. The resulting amount/currency and relevant catalog terms are snapshotted before payment and are not accepted from the client as trusted payment values.

### Stripe payment integrity

Stripe is an integration, not the domain source of truth. Payment confirmation is handled through the verified callback/webhook path and duplicate delivery is idempotent. Successful payment does not itself create an Evaluation, start Auditor work or imply Validation.

### Refund integrity

The approved no-questions-asked refund boundary is report delivery. Before `reports.delivered_at`, eligible paid requests may be refunded; after delivery the entitlement is rejected. Internal refund state is kept separate from provider reversal state, with idempotency and recoverable failure handling.

### Intake and privacy

Submitted materials are controlled by explicit creator submission and administrator verification workflows. Readiness requires the verified evidence set. Creator-facing contracts expose only permitted state and actions and do not expose Auditor-private evidence, deliberation or platform-only decisions.

### Raw/bulk mutation audit

The Phase 2 regression test scans application code for direct `DB::table(...)->update/delete` and `DB::query(...)->update/delete` usage outside `app/Services`. Controlled service writes are permitted because they are the explicit domain/application boundary. This prevents accidental reintroduction of raw mutation into creator workflows.

## Concurrency testing limitation

The test suite uses SQLite in-memory isolation. It can exercise transaction and idempotency paths but cannot faithfully reproduce every production-database isolation and locking characteristic. Production safety therefore also depends on database uniqueness constraints and transaction/row-lock implementations. The application must not claim that SQLite proves every database-specific concurrency guarantee.

## Completion rule

A Phase 2 finding is closed only when the permitted path, invalid path, authorization boundary, historical/commercial boundary and applicable bypass/concurrency boundary are covered or explicitly documented. A passing happy path alone is insufficient.
