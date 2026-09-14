# Operational automation

Valid.guide automates the concrete repetitive operation already supported by the Phase 6.2 operational evidence: scheduled Validation trust monitoring. The automation reuses the existing `ValidationTrustMonitoring` service and does not introduce a second workflow engine, telemetry store or external orchestration dependency.

## Automated workflow

### Trigger

The `valid:monitor-trust` Artisan command is scheduled every fifteen minutes and uses Laravel's scheduler with `withoutOverlapping()`. The scheduler frequency is a polling interval, not the monitoring cadence. Each monitor controls its own next execution time through `next_check_at`.

### Preconditions

A monitor is processed only when:

- it is not cancelled;
- `next_check_at` is due;
- its persisted validation can be resolved;
- validation provenance resolves to the monitor's organization;
- a published public verification snapshot exists and is complete.

Terminal validations cannot be configured for monitoring. Automated execution does not accept a client-supplied tenant or workflow identifier as authority. Organization ownership is resolved from persisted validation provenance and compared with the monitor's persisted organization.

### Authoritative state transition

The scheduler calls `ValidationTrustMonitoring::runDue()`, which delegates each due monitor to `ValidationTrustMonitoring::runOne()`. The service remains the authority for monitoring state transitions. The scheduler does not reproduce validation, publication or trust rules.

### Side effects

A successful check:

- records or refreshes the observed fingerprint;
- schedules the next check according to the monitor cadence;
- records a baseline event when no baseline exists;
- records a trust-state-change event when the observed fingerprint changes;
- records recovery when a previously failed, stale or invalid monitor becomes healthy again;
- emits the existing audit/workflow path used by lifecycle notifications.

Monitoring never rewrites historical Evaluation, Evaluation Decision or public verification history directly.

## Idempotency and duplicate handling

A monitor row is locked during execution so concurrent scheduler invocations cannot process the same monitor simultaneously. The next check time is persisted as part of the same transaction.

Monitoring events use a deterministic fingerprint and event type. Existing events with the same monitor, event type and fingerprint are not recorded again. Re-running a due check with unchanged observed state therefore produces no duplicate trust-state event.

The scheduler's `withoutOverlapping()` guard additionally prevents a second local scheduler invocation from starting while an earlier command is still running.

## Retry and recovery

A failed check remains represented by the monitor itself. The service records `failed` or `invalid` status, a failure fingerprint and failure reason, and sets a future `next_check_at` using the configured cadence. No trusted output is produced from the failed run.

The next scheduled execution retries the same monitor. A successful retry returns the monitor to `active` and records a recovery event. This provides bounded, deterministic retry behavior without adding a queue dependency or speculative retry infrastructure.

A stale monitor is also recoverable through the same scheduled execution path. Cancellation is terminal for the monitor until an authorized creator explicitly configures it again.

## Failure and cancellation behavior

Malformed or incomplete trust provenance is treated as an invalid monitoring state. Unexpected runtime failures are recorded as failed monitoring state. Both outcomes remain visible through the existing audit and notification infrastructure and leave the monitor eligible for its next scheduled retry.

Cancellation is performed only through the authorized monitoring service and requires a non-empty reason. A cancelled monitor is ignored by `runDue()` and cannot be processed by a later scheduler invocation.

## Authorization and tenancy

Configuration and cancellation remain server-side authorized through `ValidationTrustMonitorPolicy`. Automated execution has no user-controlled authorization context. Tenant identity comes from persisted monitor and validation relationships, and a mismatch is treated as an invalid provenance condition.

Platform administrators can operate within the existing platform boundary; organization users retain only their organization-scoped management authority.

## Audit and notification path

Monitoring state changes are written through the existing audit logger. The repository's existing workflow notification listener translates monitoring audit events into the established database notification workflow for applicable lifecycle events. Operational telemetry remains separate from authoritative business audit meaning.

No raw evidence, private Auditor deliberation, credentials or secrets are included in monitoring payloads.

## Operational ownership

- **Workflow owner:** `App\Services\ValidationTrustMonitoring`.
- **Trigger owner:** `routes/console.php` and Laravel's scheduler.
- **Authoritative state:** `ValidationTrustMonitor` and immutable `ValidationTrustMonitorEvent` records.
- **Governance/audit:** existing `AuditLogger` and workflow notification infrastructure.
- **Operational reporting:** `App\Services\OperationalMetrics` and the existing administrator reporting surface.

## Recovery procedure

1. Inspect the monitor's persisted status, failure reason and next check time through the existing operational/admin surfaces.
2. Allow the scheduled retry to execute when the failure is transient or externally recoverable.
3. Correct incomplete validation/public verification provenance through the appropriate authoritative domain workflow when the monitor is `invalid`.
4. Confirm that a subsequent run returns the monitor to `active` and records recovery.
5. Cancel monitoring when continued observation is no longer required. Cancellation itself is auditable.

No manual database mutation is part of the recovery procedure.
