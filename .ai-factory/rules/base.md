# Project Base Rules

> Auto-detected conventions from codebase analysis. Edit as needed.

## Naming Conventions

- Files: PascalCase (e.g., `WorkflowClient.php`, `RetryOptions.php`)
- Variables: camelCase (e.g., `$workflowClient`, `$retryOptions`)
- Functions/Methods: camelCase (e.g., `startWorkflow()`, `getResult()`)
- Classes: PascalCase (e.g., `WorkflowClient`, `DataConverter`)
- Interfaces: PascalCase with `Interface` suffix (e.g., `WorkflowClientInterface`)
- Traits: PascalCase with `Trait` suffix
- Constants: UPPER_SNAKE_CASE
- No informal abbreviations — use full words

## Module Structure

- `src/` — main SDK source, mirrors namespace `Temporal\`
- `src/Internal/` — non-public implementation (not part of public API)
- `src/Nexus/` — Nexus RPC handler-side library
- `testing/` — testing framework (`Temporal\Testing\`)
- `tests/Unit/` — unit tests (no external deps)
- `tests/Functional/` — functional tests (needs Temporal server)
- `tests/Acceptance/` — E2E acceptance tests
- `tests/Nexus/` — Nexus subsystem tests (picked up by Unit suite)
- `tests/Arch/` — architecture constraint tests

## Error Handling

- Custom exception hierarchy under `src/Exception/`
- Failure types: `FailureConverter` for Temporal failure payloads
- Strict types — type errors caught at compile time

## Code Style

- php-cs-fixer with `Spiral\CodeStyle\Builder`
- `declare(strict_types=1)` required in all files
- PHP 8 attributes for declarations
- Prefer `if`-statements over short-circuit side effects
- `readonly` properties + named arguments in newer code
- Default: no comments unless capturing hidden constraints

## Testing Patterns

- PHPUnit 10.5 with `--testdox` output
- Unit tests: `*TestCase.php` suffix
- Acceptance tests: `*Test.php` suffix
- Nexus unit tests: `*Test.php` suffix (under `tests/Nexus/Unit/`)
- Tests must exercise real behaviour, no tautologies
- Never `markTestIncomplete`/`markTestSkipped` to dodge a failing test when the task is to write or fix tests; see CLAUDE.md "Tests must exercise real behaviour" for the long form.
