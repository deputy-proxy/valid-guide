# Security and privacy review: issue #63

## Review scope

This review covers the implemented authentication and authorization boundaries, organization membership checks, platform-admin gates, route binding, request validation, public verification/directory projections, sensitive mutations, audit logging, and public abuse controls.

The repository's existing domain services and policies remain authoritative for business authorization. No dependency, infrastructure, or CI configuration change was required.

## Findings and decisions

### 1. Public verification endpoints had no application-level abuse control

The public verification lookup and identifier display routes are intentionally unauthenticated. They resolve persisted public records and therefore need to remain publicly reachable, including for verification identifiers that are not listed in the public directory.

Before this review, neither route had a route-level rate limit. Because the identifier space is externally addressable, unrestricted automated lookup could be used for request amplification, enumeration attempts, or unnecessary database load.

**Decision:** apply Laravel's existing `throttle` middleware at 30 requests per minute to both verification routes. This does not change disclosure semantics or require a new limiter implementation.

### 2. Authenticated community reporting had no application-level abuse control

Community reporting is authenticated and verified, and the domain service already prevents authors from reporting their own published contributions and prevents multiple open reports by the same reporter for the same contribution. The route did not, however, limit request volume.

An authenticated account could therefore generate repeated report attempts across many contributions, creating avoidable moderation workload and database/audit-log pressure.

**Decision:** apply Laravel's existing `throttle` middleware at 10 requests per minute to the reporting route. Existing domain validation remains unchanged.

## Authorization and disclosure audit conclusions

- Organization resource access is based on the authenticated user's organization membership and role through existing policies.
- Creator report access resolves the evaluation's authoritative organization and verifies membership before exposing creator report data.
- Sensitive creator-action mutations are re-authorized by the domain workflow using the action's authoritative organization, rather than trusting client-supplied organization identifiers.
- Auditor evidence mutations resolve the authoritative auditor evaluation and verify assignment ownership and substantive-work access before mutation.
- Public directory entries require directory visibility and an active validation state.
- Public verification pages use persisted snapshots rather than reconstructing public meaning from mutable internal records.
- Full report content is included in a public snapshot only when the platform-admin-controlled `full_report_visible` flag is enabled.
- Public auditor disclosure is limited to approved auditor profiles and published public verification snapshots do not include auditor email addresses or other private user fields.
- Stripe webhook processing requires a valid signed payload and a timestamp within the configured tolerance before processing.
- Audit logging records explicit governance metadata rather than serializing complete model payloads.

## Residual risks

This application-level review does not replace external penetration testing, infrastructure/WAF review, credential compromise controls, dependency vulnerability scanning, or production-specific observability and rate-limit tuning.

The verification rate limit is intentionally conservative and may need adjustment based on real verification traffic. The community-report limit should likewise be reviewed against legitimate moderation activity. Both limits use Laravel's existing middleware and can be tuned without changing the authorization model.
