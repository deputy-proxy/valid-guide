# Phase 1 Development Decisions

> Project: Valid.guide Validation
> Date started: 2026-09-08

This file records implementation-significant decisions made during Phase 1 that refine the approved domain architecture.

## 1. Public Verification Record is a first-class projection

The public verification page is authoritative for the external trust state. A `PublicVerificationRecord` is therefore persisted separately from `Validation` rather than rendering the public page directly from a collection of internal tables.

A record is created atomically when a Validation is issued. Its identity is tied permanently to the Validation and its public slug is derived from the non-guessable verification identifier.

## 2. Public verification content uses a persisted snapshot

The public record stores a JSON `snapshot` containing the public-facing identity and result data needed to render verification consistently. This prevents the public representation from becoming an accidental live query across mutable application data.

The snapshot is synchronized through a domain service when the Validation status changes. The record's identity (`validation_id`, `public_slug`, `published_at`) is immutable; controlled publication logic may update the snapshot and visibility flags.

## 3. Verification URLs remain meaningful after status changes

A public verification record is not deleted when a Validation is suspended, revoked or superseded. Its snapshot is updated to the current Validation status, allowing the verification URL and badge identifier to remain resolvable and to communicate the historical trust state.

## 4. Badge and public record share the Validation verification identifier

The Validation owns the canonical non-guessable verification identifier. The Badge and Public Verification Record use that identifier rather than generating independent public identities. This avoids ambiguous verification references and makes the badge a simple pointer to the authoritative record.

## 5. Public record creation is part of Validation issuance

Issuing a Validation, creating its Badge and creating its initial Public Verification Record are one transaction. A partial trust state must not be possible where a Validation exists but its badge or verification record does not.

## 6. Status propagation is domain-controlled

Validation status changes update the Badge and the Public Verification Record through `ValidationStateTransition`. Direct mutation/deletion of trust identities is blocked at the model layer. Bulk/raw database mutation remains outside these protections and must not be used for trust-domain writes.

## 7. High-trust authorization remains a Phase 1 follow-up

The current domain services enforce state and data invariants but do not yet constitute the complete platform-admin authorization boundary. Before exposing COI determinations, final Evaluation Decisions, Validation status changes or public publication controls through UI, explicit authorization policies for platform administrators must be implemented and tested.
