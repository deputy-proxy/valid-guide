# AI Development Rules

This document is the project-level CI and implementation contract for AI-assisted development in Valid.guide.

The authoritative sources are, in order of priority:

1. The actual GitHub Actions workflow configuration in `.github/workflows/`.
2. `composer.json` and the dependency lockfile.
3. Tool configuration files such as `pint.json`, `phpstan.neon`, `phpunit.xml`, and framework configuration.
4. Existing production code and tests, when they establish repository conventions.
5. This document, which summarizes the rules above for repeatable AI-assisted implementation.

This document must not be used to override the actual CI configuration. When the repository configuration changes, this document must be updated accordingly.

## CI workflow contract

The repository currently has one GitHub Actions workflow: `.github/workflows/test.yml`.

It runs for:

- pushes to `main`;
- every pull request.

The CI job runs on `ubuntu-latest` and performs the following steps, in this order:

1. Checkout the repository.
2. Set up PHP `8.4` with Composer v2 and no coverage collection.
3. Set up Node.js `22`.
4. Run `composer setup`.
5. Run `composer lint:check`.
6. Run `composer types:check`.
7. Run `php artisan test`.

Therefore, a pull request is not CI-safe merely because the PHP code is logically correct. The implementation must be compatible with the complete setup, formatting, static-analysis and test sequence above.

The CI workflow is authoritative. If it differs from this document, follow the workflow and update this document.

## Runtime and framework contract

The application currently declares:

- PHP: `^8.4`.
- Laravel Framework: `^13.17`.
- Filament: `^5.0`.
- Livewire: `^4.1`.
- Pest: `^5.1` with `pest-plugin-laravel` `^5.0`.
- Laravel Pint: `^1.27`.
- Larastan: `^3.9`.

CI additionally provisions Node.js `22` and runs the application's `composer setup` script, which installs dependencies, creates `.env` when needed, generates the application key, migrates the database, installs npm dependencies and builds frontend assets.

Use only APIs and language features available in the versions actually declared by the project.

Do not assume APIs from older or newer framework versions merely because they are familiar.

## Required quality checks

The exact CI quality gates are:

- `composer lint:check` runs `pint --parallel --test` and must pass without modifying files.
- `composer types:check` runs `phpstan analyse` using the repository's `phpstan.neon` configuration.
- `php artisan test` runs the Laravel/Pest test suite configured by the project.

The Composer script `test` is also available and runs configuration clearing, lint checking, static analysis and `php artisan test`, but CI currently invokes the individual commands directly rather than `composer test`.

`composer lint` is the formatter command and is not itself the CI command.

`composer ci:check` is available as a Composer helper but is not currently invoked by the GitHub Actions workflow.

Never claim that a quality check passed unless it was actually executed and its result is available. Static review can predict failures, but it does not establish a passing result.

## Code style and lint contract

Formatting is governed by Laravel Pint using the repository's `pint.json` configuration:

```json
{
    "preset": "laravel"
}
```

The CI command is `composer lint:check`, which runs Pint in test mode.

Before committing:

- Match the Laravel Pint style used by the repository.
- Keep imports correct and ordered according to project conventions.
- Do not introduce unused imports, variables, parameters, properties, methods or classes.
- Avoid unreachable code and redundant branches.
- Match the surrounding code's naming, spacing, visibility and declaration conventions.
- Review every changed PHP file, not only the lines that implement the issue.
- Do not rely on Pint to silently repair the implementation after it has been committed.

Do not modify formatting configuration merely to make an implementation pass lint.

## PHPStan / Larastan contract

The CI command `composer types:check` runs `phpstan analyse`.

PHPStan is configured at **level 7** and analyzes:

- `app/`
- `bootstrap/app.php`
- `config/`
- `database/`
- `routes/`

Larastan and Carbon extension configuration is loaded through `phpstan.neon`.

Before committing, perform a static analysis review of every changed file and its relevant call sites. Pay particular attention to:

- missing or incorrect return types;
- nullable values used as non-nullable values;
- incorrect property and method types;
- undefined methods or properties;
- incorrect method arguments;
- array and collection value types;
- generic collection assumptions;
- model relationship types;
- factory return types;
- request/input values treated as strongly typed without validation;
- incorrect interface or contract implementations;
- impossible comparisons and unreachable branches;
- incorrect namespace/import references;
- framework APIs whose signatures differ between Laravel versions;
- test doubles whose types do not match the real dependency.

Do not suppress PHPStan findings or weaken the analysis level merely to make CI pass.

Use repository-consistent typing and annotations. Add an annotation only when it accurately describes the runtime contract.

## Test contract

The CI command is `php artisan test`.

Tests are configured in `phpunit.xml` with two suites:

- `tests/Unit`
- `tests/Feature`

The test environment uses SQLite in-memory storage and sets the application environment to `testing`.

Before committing, review every changed behavior for appropriate test coverage.

Tests should:

- follow the existing Pest/Laravel testing style;
- reuse existing factories, helpers and fixtures;
- verify important success and failure paths;
- verify authorization and validation where applicable;
- verify relevant database state;
- be deterministic and isolated;
- avoid dependence on test execution order;
- avoid brittle implementation-detail assertions;
- use mocks only where they reflect actual application boundaries.

Do not change or weaken an existing test merely to accommodate an incorrect implementation.

When a schema, model, relationship or factory changes, inspect all directly affected tests and fixtures for compatibility.

Because CI runs `composer setup` before the test command, database migrations and frontend build/setup behavior are also part of the practical CI contract.

## Laravel implementation contract

Use the project's established Laravel architecture and existing patterns.

Before introducing a new abstraction, first determine whether an existing service, action, domain object, request, policy, model method, query scope, component or helper already provides the required behavior.

For every framework API used:

1. Check the version declared in `composer.json`.
2. Inspect existing repository usage of that API where possible.
3. Prefer patterns already used by the project.

Do not introduce behavior based solely on memory of a different Laravel version.

## Scope discipline

An issue implementation must be narrowly scoped.

Do not:

- refactor unrelated code;
- rename unrelated symbols;
- introduce speculative abstractions;
- add dependencies without necessity;
- alter CI configuration to conceal failures;
- perform unrelated cleanup;
- modify unrelated tests;
- broaden the issue beyond the approved implementation plan.

Prefer the smallest change that satisfies the issue while preserving existing behavior.

## Static CI simulation before commit

When local execution of lint, PHPStan or tests is unavailable, the implementation process must compensate with a static CI review.

Before committing, review the implementation in this exact order:

1. Compare the changed files against `.github/workflows/test.yml` and repository tool configuration.
2. Confirm the implementation does not depend on a runtime, PHP, Node, Laravel or package version different from CI.
3. Review every changed file for Pint violations.
4. Review every changed file against PHPStan level 7 expectations.
5. Review every changed or added test for Pest/Laravel and `phpunit.xml` compatibility.
6. Review migrations, factories, models and database assumptions together.
7. Review routes, controllers, requests, policies and authorization boundaries together.
8. Review imports, namespaces, method signatures and return types.
9. Review test isolation, fixtures and database state.
10. Review frontend/build-related changes against the Node.js `22` setup where applicable.
11. Inspect the complete final diff for accidental or unrelated changes.

Then perform an adversarial second pass:

> What is the most likely reason GitHub CI could reject this change even though the implementation appears functionally correct?

Consider separately:

- a formatting failure;
- a PHPStan level 7 failure;
- a test discovery or syntax failure;
- a failing assertion;
- a migration/schema/factory failure;
- a framework-version/API failure;
- a setup, asset-build or environment failure.

Fix every issue that can be identified statically before committing.

This review is a prediction exercise, not a substitute for CI.

## Commit and pull request contract

Before committing:

- ensure the branch is the issue-specific branch required by the project workflow;
- ensure the implementation is limited to the issue scope;
- complete the static CI simulation above;
- do not represent unexecuted checks as passing.

Pull requests for issue implementations should use the project's established naming convention.

After pushing:

- inspect the actual GitHub CI result;
- if CI fails, identify the root cause rather than patching only the first symptom;
- fix the smallest correct change;
- repeat the static CI simulation;
- push the fix and wait for CI again;
- do not stop until all required checks are green or the work is explicitly stopped.

## Change control

If the repository's CI workflow, tool configuration, framework version or test configuration changes, update this document in the same change whenever practical.

The actual repository configuration always wins over this summary.
