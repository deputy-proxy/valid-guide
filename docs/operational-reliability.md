# Operational reliability and observability

This document defines the reliability contract for the production workflows currently implemented by Valid.guide. It separates operational diagnostics from authoritative trust and evaluation history.

## Reliability targets

| Workflow | Trigger | Failure class | Recovery target |
| --- | --- | --- | --- |
| Validation trust monitoring | Laravel scheduler every 15 minutes, with monitor-specific `next_check_at` cadence | Invalid persisted provenance, transient runtime failure, scheduler staleness | Retry on the next due run; successful recovery returns the monitor to `active` |
| Public readiness | HTTP `GET /ready` | Application cannot establish a database connection | Return HTTP 503 without exposing exception details; recover automatically when the dependency is available |
| Scheduled monitoring command | `valid:monitor-trust` | Overlap or individual monitor failure | Scheduler overlap protection plus per-monitor persisted failure state |

These are application-level targets, not infrastructure SLAs. Production latency, process availability and external dependency uptime require deployment-specific monitoring.

## Failure classes

### Invalid state

The monitoring service cannot establish trustworthy provenance or a complete published verification snapshot. The monitor is marked `invalid`, the reason is persisted, and no trust outcome is changed.

### Runtime failure

An unexpected exception occurs while evaluating an otherwise valid monitor. The monitor is marked `failed`, with a failure fingerprint and a safe failure description. Exception class is available to operational logs without serializing the exception object or sensitive payloads.

### Stale state

An active monitor whose last successful check is more than twice its configured cadence behind the current time is considered stale by the operational diagnostics surface. Staleness does not itself mutate authoritative validation state. The next monitoring run remains the recovery path.

### Scheduler overlap

The monitoring command uses Laravel's `withoutOverlapping()` guard. Individual monitor execution also uses a database row lock, so two concurrent command invocations cannot process the same monitor simultaneously.

## Retry and idempotency

A monitoring invocation performs one deterministic attempt per due monitor. There is no unbounded in-process retry loop. Failed and invalid monitors receive a future `next_check_at` and are retried by the next scheduled execution.

Duplicate processing is controlled by:

- a row lock around each monitor execution;
- persisted `next_check_at` scheduling;
- deterministic event fingerprints;
- immutable monitor event records;
- the scheduler's `withoutOverlapping()` protection.

Repeating a check with unchanged state therefore does not create duplicate trust-state events.

## Operational logging

Scheduled monitoring establishes a per-run correlation identifier and writes structured log records with the `operation` and `run_id` context. Failure diagnostics include only safe identifiers and exception class information. Raw evidence, private evaluation material, credentials and secrets are not included.

Operational logs are diagnostic information. They do not replace the audit log and cannot be used to alter authoritative Validation, Evaluation, Decision, Report or Public Verification records.

## Readiness

`GET /ready` is a public readiness surface. It reports only:

- application readiness status;
- a database connectivity check;
- aggregate monitoring counts for due, failed, invalid and stale monitors;
- a timestamp.

It does not return organization identifiers, validation identifiers, failure messages, secrets, private evaluation data or audit payloads.

HTTP 200 means the application can establish its database dependency. HTTP 503 means the database dependency is unavailable. A healthy application can still contain failed or stale workflows, which remain visible through the aggregate monitoring diagnostics.

## Recovery procedure

1. Inspect `/ready` and the existing operational/admin monitoring surface.
2. If readiness is `unready`, restore the database dependency and allow the endpoint to recover without changing trust records.
3. For failed monitors, allow the next scheduled retry to run.
4. For invalid monitors, correct the underlying incomplete or inconsistent state through the authoritative domain workflow.
5. Confirm a subsequent monitor run returns to `active` and records a recovery event.
6. Cancel monitoring through its authorized service when continued observation is no longer required.

Manual mutation of trust, evaluation or audit records is not a recovery procedure.

## Known limitations

- `/ready` is an application readiness check, not a complete infrastructure health monitor.
- Scheduler execution depends on the deployment actually running Laravel's scheduler.
- Queue infrastructure is not assumed by the monitoring workflow because the current authoritative workflow is scheduler-based.
- Operational aggregate counts do not replace centralized log retention, alerting or production load monitoring.
