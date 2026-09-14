# Operational metrics

Valid Guide exposes operational metrics through `App\Services\OperationalMetrics` and the platform admin **Operational Metrics** page.

## Scope and authorization

- Platform administrators can request platform-wide metrics or a specific organization scope.
- Non-platform users must provide an organization scope and must belong to that organization.
- Organization-scoped audit metrics filter on the persisted `metadata.organization_id` value. Events without an organization identifier are intentionally excluded from tenant-scoped audit results rather than being attributed to a tenant by inference.
- Organization-scoped notification metrics filter database notifications through users who belong to the requested organization.
- The platform action queue metric is intentionally platform-scoped. Tenant-scoped results return `null` for that metric rather than aggregating a user's queues across multiple organizations.
- The reporting surface contains aggregate operational signals only. It does not expose raw audit payloads, notification titles/bodies, private evaluation reasoning, secrets, credentials, or actor-level activity.

## Metric semantics

The service is intentionally built on the existing persisted audit log, database notification store and role action queue rather than introducing a second telemetry store.

| Metric | Semantics |
| --- | --- |
| `total_events` | Number of audit events in the requested interval and scope. |
| `events_by_type` | Deterministic audit event-name distribution. |
| `evaluation_events` | Audit events whose name starts with `evaluation.` or `evaluation_request.`. |
| `auditor_events` | Audit events whose name starts with `auditor.` or `auditor_`. |
| `validation_events` | Audit events whose name starts with `validation.`. |
| `subscription_events` | Audit events under `subscription.` or `billing.subscription.`. |
| `public_discovery_events` | Audit events under `public_verification.`, `public_directory.`, `discovery.`, `public.discovery.`, or `directory.` where such events exist. |
| `notification_events` | Persisted database notifications addressed to users in the requested scope during the interval. |
| `notification_unread` | Notifications in the same scope and interval with no `read_at` timestamp. |
| `notification_failure_events` | Persisted notifications for trust-monitoring or subscription-payment failures. |
| `notification_recovery_events` | Persisted notifications for trust-monitoring or subscription recovery. |
| `action_queue_visible_items` | Current platform-admin action-queue items returned by the existing `RoleActionQueue`. This is a visible backlog signal, not a historical event count, and inherits the existing per-category query limits. Tenant-scoped results return `null`. |
| `monitoring_events` | Audit events under `validation_trust_monitor.` or the existing monitoring prefixes. |
| `failure_events` | Audit events ending in `failed`, `failure`, or `error`. |
| `retry_events` | Audit events whose name contains `retry`. |
| `lifecycle_durations` | Duration statistics only for matching `<workflow>.started` events followed by `<workflow>.completed` or `<workflow>.failed` for the same auditable record. |
| `duplicate_events` | Repeated observations with the same event, auditable type, auditable ID, and timestamp. Duplicates are reported, not silently discarded. |
| `out_of_order_events` | Lifecycle terminal events without a preceding matching start, or lifecycle timestamps that cannot form a valid positive duration. |

The repository currently records many important lifecycles as state transitions such as `evaluation.status_changed` and `validation.status_changed`, rather than paired timing events. Those transitions are counted for throughput and failure analysis, but the service does not invent latency values where an explicit deterministic timing source is absent.

## Data quality and interpretation

Audit logs are treated as the authoritative operational evidence. Counts therefore include duplicate records, while the duplicate count makes that data-quality condition visible. Lifecycle pairing consumes starts in timestamp order and flags terminal events without a usable start. This prevents missing or out-of-order observations from silently becoming plausible-looking throughput or latency numbers.

A zero category can mean either no activity or no persisted signal for that category during the requested interval. The service deliberately does not manufacture telemetry for workflows that do not record a suitable source event or state.

## Privacy and retention

The analytics layer is read-only. It does not mutate Evaluation, Decision, Validation, ranking or recommendation state, and it does not create a parallel analytics database. Notification aggregation reads only notification metadata needed for counts and does not return notification payloads.

No separate analytics retention policy is introduced by this phase. Metrics inherit the retention behavior of their authoritative source records, primarily audit logs and database notifications. Because the metrics are computed on demand and not persisted separately, deleting or expiring source records also removes them from future metric calculations.

## Ownership

`OperationalMetrics` owns aggregation and scope enforcement. Existing domain services remain responsible for state mutation and authoritative event/state recording. The Filament page is a presentation layer only and is server-side restricted to platform administrators.
