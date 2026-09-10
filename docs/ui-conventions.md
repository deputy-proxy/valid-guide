# Valid.guide UI Conventions

> Issue: #51
> Scope: Phase 3 UI foundation
> Stack: Laravel 13, Filament 5, Livewire 4

## Purpose

This document defines the shared presentation contract for creator, platform-administrator and Auditor application surfaces. It deliberately does not define business rules. UI code presents state and invokes authorized application/domain capabilities; it does not become a second domain layer.

## 1. Surface boundaries

### Creator

Creator surfaces are organization-scoped and may expose:

- organizations and permitted organization context;
- products and product releases;
- evaluation-request intake and progress;
- commercial information permitted to the organization;
- reports, Validation results and permitted next actions.

Creator surfaces must not expose private Auditor evidence, deliberation, internal governance decisions or unrelated organizations.

### Platform Admin

Platform administration is separate from organization membership. Administrative surfaces may expose governance, methodology, assignments, decisions, trust, commerce and audit operations only where the authenticated platform role is authorized.

### Auditor

Auditor surfaces are assignment-scoped. An Auditor may access their own profile and evaluations to which they are assigned and cleared. Internal evidence and deliberation belonging to other evaluations remain private.

## 2. Authorization boundary

Navigation visibility, disabled controls and hidden actions are presentation concerns. They are never the security boundary.

Every state-changing operation must ultimately cross the existing server-side application/domain authorization boundary. A hidden Filament action must remain unauthorized when invoked directly.

UI code must not:

- trust client-supplied organization identifiers;
- implement tenancy checks as a substitute for backend authorization;
- calculate authoritative prices;
- transition lifecycle state by directly mutating protected model attributes;
- decide refund eligibility;
- issue Validation or alter trust state directly;
- expose internal evidence merely because an Eloquent relationship exists.

## 3. State presentation

Domain state is authoritative. UI code must consume existing enums or application read projections rather than introduce competing state machines.

A displayed state should provide, where appropriate:

- a human-readable label;
- a semantic presentation color;
- an icon;
- optional explanatory text.

The presentation mapping may be shared through `App\Support\Ui\UiState`, but it must never determine whether a transition is allowed.

Unknown states must fail safely and should not be silently interpreted as a trusted or positive state.

## 4. Action presentation

Permitted actions are derived from backend state and authorization. An action can be:

- visible;
- enabled;
- disabled with an explanatory reason;
- unavailable.

The UI may make a permitted action easier to discover, but the application/domain service remains authoritative.

High-impact operations require explicit confirmation where appropriate, including archival, submission, payment, refund, evaluation submission, trust-state changes and administrative overrides.

## 5. Filament resources and pages

Use standard Filament resources for straightforward reference-data CRUD only.

Workflow-sensitive or historical aggregates should use dedicated pages/actions that invoke application/domain services. Do not expose unrestricted `EditAction`, `DeleteAction` or bulk mutation merely because Filament can generate them.

Resources should follow this order of responsibility:

1. authorization and visibility through policies/capabilities;
2. presentation schema;
3. table/list presentation;
4. actions that delegate to application services;
5. notifications and navigation.

Domain calculations, lifecycle guards, tenancy decisions and historical-integrity rules do not belong in resource classes.

## 6. Forms

Forms should:

- use domain terminology;
- make required/optional fields explicit;
- provide actionable validation feedback;
- keep immutable historical values read-only;
- avoid duplicating server-side validation rules that protect trust boundaries;
- submit through the appropriate application boundary.

Client-side validation improves usability but is not authoritative.

## 7. Tables

Tables should use consistent representations for:

- statuses;
- dates and timestamps;
- monetary amounts;
- identifiers;
- relationships;
- empty and filtered states.

Use domain names consistently. For example, use **Product Release** rather than inventing a UI-specific synonym such as “Version” where the domain distinction matters.

## 8. Notifications and errors

Notifications must distinguish successful operations from validation, authorization and domain-state failures.

Never expose internal exception messages, stack traces, credentials, private evidence or other sensitive implementation details to users.

Unexpected failures should be handled by the application's normal error boundary and logged through the established observability path.

## 9. Loading and empty states

Every interactive surface should define a useful state for:

- initial loading;
- action processing;
- no records;
- no records matching the current filters;
- unavailable or unauthorized content where the distinction is user-relevant.

Empty states must not reveal information that the actor is not authorized to know exists.

## 10. Responsive and accessibility baseline

New UI must be usable across supported desktop and mobile widths.

Baseline requirements:

- keyboard-operable controls;
- visible focus indication;
- labels associated with form controls;
- errors associated with the affected input where possible;
- status communicated with text or another non-color-only indicator;
- usable responsive layouts for forms and tables;
- accessible dialogs and confirmation flows;
- adequate touch targets;
- meaningful heading and landmark structure;
- no critical information conveyed by color alone.

Accessibility regressions in reusable UI primitives should receive focused tests where the behavior is testable at the application layer.

## 11. Testing conventions

UI Feature tests should cover both presentation and the backend boundary.

At minimum, representative tests should demonstrate:

- authorized page access;
- unauthorized page/action rejection;
- organization-tenancy rejection;
- action visibility based on backend capability/state;
- direct invocation of a hidden action still being rejected when unauthorized;
- invalid lifecycle actions producing deterministic feedback;
- private information not appearing in creator/Auditor-facing projections;
- representative responsive/accessibility behavior where the framework permits reliable assertions.

Pure presentation value objects may use Unit tests. Livewire/Filament interactions and database-backed authorization belong in Feature tests.

## 12. Quality gates

Every UI change must satisfy:

```text
composer lint:check
composer types:check
php artisan test
```

The CI workflow remains the final authority. A UI change is not complete while Pint/lint, PHPStan, the relevant test suite or CI is failing.

## 13. Definition of a reusable UI foundation

A new UI abstraction belongs here only when it is shared by multiple upcoming surfaces or prevents a known class of inconsistent implementation.

Do not add speculative design-system components, complete public pages or full role workflows under this issue. Feature-specific presentation belongs to the corresponding Phase 3 issue.
