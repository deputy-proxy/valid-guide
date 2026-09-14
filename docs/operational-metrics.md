# Operational metrics

Valid Guide exposes operational metrics through `App\Services\OperationalMetrics` and the platform admin **Operational Metrics** page.

## Scope and authorization

- Platform administrators can request platform-wide metrics or a specific organization scope.
- Non-platform users must provide an organization scope and must belong to that organization.
- Organization-scoped queries filter on the persisted `metadata.organization_id` value of audit events. Events without an organization identifier are intentionally excluded from tenant-scoped results rather than being attributed to a tenant by inference.
- The reporting surface contains aggregate operational signals only. It does not expose raw audit payloads, private evaluation reasoning, secrets, credentials, or actor-level activity.

## Metric semantics

The service is intentionally built on the existing immutable audit log rather than introducing a second telemetry store.

| Metric | Semantics |
| --- | --- |
| `total_events` | Number of audit events in the requested interval and scope. |
| `events_by_type` | Deterministic event-name distribution. |
| `evaluation_events` | Events whose name starts with `evaluation.`. |
| `auditor_events` | Events whose name starts with `auditor.`. |
| `validation_events` | Events whose name starts with `validation.`. |
| `subscription_events` | Events under `subscription.` or `billing.subscription.`. |
| `public_discovery_events` | Events under `discovery.`, `public.discovery.`, or `directory.`. |
| `notification_events` | Events under `notification.`. |
| `action_queue_events` | Events under `action_queue.`. |
| `monitoring_events` | Events under `monitoring.` or `validation.monitor`. |
| `failure_events` | Events ending in `failed`, `failure`, or `error`. |
| `retry_events` | Events whose name contains `retry`. |
| `lifecycle_durations` | Duration statistics for matching `<workflow>.started` events followed by `<workflow>.completed` or `<workflow>.failed` for the same auditable record. |
| `duplicate_events` | Repeated observations with the same event, auditable type, auditable ID, and timestamp. Duplicates are reported, not silently discarded. |
| `out_of_order_events` | Lifecycle terminal events without a preceding matching start, or lifecycle timestamps that cannot form a valid positive duration. |

Lifecycle duration is only calculated where the audit data provides an explicit start and terminal event. The service does not infer timings from unrelated events, and it does not invent values for missing data.

## Data quality and interpretation

Audit logs are treated as the authoritative operational evidence. Counts therefore include duplicate records, while the duplicate count makes that data-quality condition visible. Lifecycle pairing consumes starts in timestamp order and flags terminal events without a usable start. This prevents missing or out-of-order observations from silently becoming plausible-looking throughput or latency numbers.

The service does not claim that an event family exists when the application has not recorded that family. A zero for a category can therefore mean either no activity or no persisted audit signal for that category during the requested interval.
