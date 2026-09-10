# Creator Refund Workflow

Creator refunds are permitted for an eligible paid Evaluation Request until an associated Report has `delivered_at` set.

The workflow creates one immutable Refund record per Payment. The refund stores the actor, frozen amount and currency, provider, reason, timestamps and provider refund identifier. Stripe requests use a deterministic idempotency key derived from the Refund ID.

A provider failure leaves the Refund recoverable while Payment, Order and Evaluation Request remain paid. A successful provider refund changes the Refund to `succeeded`, the Payment and Order to `refunded`, and the Evaluation Request to `refunded` in one controlled database transaction.

Report delivery is blocked while a refund is pending, processing or succeeded. This prevents a race between refund initiation and the historical report-delivery boundary. Failed refunds release that block and can be retried.
