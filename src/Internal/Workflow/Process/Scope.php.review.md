# Review: `src/Internal/Workflow/Process/Scope.php`

Deferred: Every coroutine — including pure-Generator workflows that never use
Fibers — is wrapped in `new \Fiber(...)` by `createFiberHandler()`. Pure
Generator workflows pay the cost of one Fiber allocation per scope (workflow,
per signal, per update, per `Workflow::async()` scope) plus per-yield bridge
overhead. The fix is to gate the fiber-wrap at workflow registration time —
only wrap if the workflow opted into Fibers (marker attribute, interface, or
runtime hint). That introduces a marker outside the Group B scope, so it is
not part of this round.

All Group B fixes from the original review are resolved:

- `destroy()`: `setFiberMode(false)` uses `?->` and is recorded in the psalm
  baseline alongside the existing `?->destroy()` entries.
- `createCoroutine()`: stale `$deferred` PHPDoc paragraph removed; method now
  has no leading docblock (parameters self-describe).
- `createFiberHandler()`: clarified docblock summarising the Fiber-to-Generator
  bridge.
- Imports normalised — `Facade` is imported via `use`; `Workflow::` references
  drop the leading slash.
- `defer()` rewrite from short-circuit `and` to `if` block — kept.
