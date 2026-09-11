# Auditor Workspace Conventions

The Auditor application uses a dedicated Filament panel and keeps workflow state authoritative in the existing domain/application layer.

## Access boundary

Assignment lists and assignment detail pages are restricted server-side to an authenticated user with an approved Auditor profile, current annual conflict clearance, and a cleared assignment-level conflict declaration. Assignment identifiers are resolved through this same authorized query boundary, so UI or Livewire payload manipulation cannot widen access.

## Presentation boundary

Auditor pages may present product/release identity, evaluation scope, Standard Version, assignment status and permitted readiness information. Private evidence, other Auditors' work and platform-only information are not included in the assignment workspace.

## Action boundary

Available actions are derived from backend authorization and assignment state. Presentation code must not implement lifecycle transitions or authorization rules. Subsequent Auditor workflow issues should invoke their existing application/domain services.

## UI states

Auditor workflow pages should provide explicit loading, empty, validation, failure and recovery states. Status must remain understandable without relying on colour alone.

## Accessibility and responsive baseline

Pages use semantic headings and definition lists, meaningful action labels, keyboard-accessible controls, responsive layouts and readable status text. Subsequent Auditor pages should follow the same conventions.
