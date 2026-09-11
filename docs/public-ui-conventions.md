# Public UI Conventions

The external Valid.guide experience is a separate presentation surface from the internal Filament application.

## Architecture

Public Blade views live under `resources/views/public` and use the `x-layouts.public` layout. Public presentation must not depend on Filament components, navigation or internal admin UI.

Public routes may expose application-facing entry points, marketing content and verification entry points, but business-critical behavior remains owned by application/domain services.

## Public data boundary

Public verification rendering must consume an explicit public-read contract backed by the persisted Public Verification Snapshot. It must not reconstruct historical public state by traversing mutable Product, Evaluation, Auditor or Report records.

Only fields deliberately included in the public projection may reach public views.

## Layout and accessibility

The public layout provides:

- a semantic header and primary navigation;
- a skip-to-content link;
- a single main content landmark;
- a semantic footer navigation landmark;
- visible keyboard focus states;
- responsive content widths and spacing;
- status components whose meaning is communicated with text rather than color alone.

Reusable components belong under `resources/views/components/public` when they represent stable public presentation patterns shared by multiple pages.

## Metadata and SEO

Public pages use the public layout's metadata contract for title, description, robots directives and canonical URL. Canonical URLs are derived from the current application URL unless a page explicitly supplies one.

Public verification lookup is `noindex` until the dedicated verification experience establishes the authoritative public verification route and its lookup behavior.

## Public states

Public resources use deliberate presentation for unavailable or not-yet-published content. Unknown public identifiers must not reveal whether corresponding private or internal records exist. Verification-specific lookup and status behavior belongs to the dedicated public verification implementation.

## Scope of this foundation

The public UI foundation establishes the shell, reusable presentation primitives, route architecture, metadata behavior and representative accessibility tests. It does not implement verification-detail business logic or reconstruct public verification records.
