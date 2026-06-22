# Plan: Delete dead `DeferredFiber` and fix misleading `CoroutineInterface` docblock

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** fast
**Type:** cleanup / dead-code removal
**Source finding:** `.ai-factory/research/fibers-review.md` — M2 (MEDIUM, lines 287–293) and open question #1 (line 442)

## Settings

- Testing: no new tests (deletion only) — existing suites act as regression gate
- Docs: no
- Logging: minimal — pure dead-code removal, no runtime behaviour changes
- Roadmap linkage: none (Phase 7 — Cleanup, per `.ai-factory/research/fibers-review.md` action plan, lines 497–501)

## Scope statement

**Option A (chosen):** touches `src/Experiments/Fibers/DeferredFiber.php` (delete) and `src/Internal/Workflow/Process/CoroutineInterface.php` (docblock) — safe to parallelize with every other Fiber-fix plan.

**Option B (rejected):** touches `src/Internal/Workflow/Process/Scope.php` — overlaps with the Scope.php cluster (C6, C7, C8, M1, M3, M4, H9, L2) and **cannot parallelize** with any plan in that cluster.

## Finding

`src/Experiments/Fibers/DeferredFiber.php` is dead. It declares a Fiber-based `CoroutineInterface` implementation, but `grep -rn "DeferredFiber" src/ tests/` reports exactly two hits — the class declaration itself and the docblock reference in `CoroutineInterface`:

```
src/Experiments/Fibers/DeferredFiber.php:19:final class DeferredFiber implements CoroutineInterface
src/Internal/Workflow/Process/CoroutineInterface.php:17: * Both {@see DeferredGenerator} and {@see DeferredFiber} implement this interface,
```

No `new DeferredFiber`, no `DeferredFiber::` static call, no factory hook, no test. The bridge in `Scope::createFiberHandler` (`src/Internal/Workflow/Process/Scope.php:479-518`) always wraps the Fiber inside an anonymous `\Generator` and routes it through `DeferredGenerator::fromHandler($fiberHandler, $values)` (line 475). The Fiber-as-coroutine path that `DeferredFiber` was written to support is never taken at runtime.

The docblock at `src/Internal/Workflow/Process/CoroutineInterface.php:17` claims both implementations are wired, which actively misleads readers about how coroutine execution flows on this branch.

## Decision: Option A — delete

### Why not Option B

Option B would replace `DeferredGenerator::fromHandler($fiberHandler, $values)` at `Scope.php:475` with a direct `new DeferredFiber($fiber, $suspendedValue)` and remove the Generator-bridge closure at lines 500–516. Reasons not to take it now:

1. **Uncertain benefit.** The Generator bridge adds one extra hop per resume; on workflow timescales (network-bound activities, timer waits) this is invisible. There is no measured or stated motivation in the branch commits.
2. **Subtle behaviour mismatch.** `DeferredFiber::handleException` (lines 120–137) **silently swallows catcher exceptions** (`catch (\Throwable) { /* Do nothing. */ }` at lines 130–132). The current Generator-bridge re-raises caller exceptions through the surrounding `try/finally` that calls `setFiberMode(false)`. Wiring `DeferredFiber` without first auditing the catcher contract would change error propagation in subtle ways and require fresh tests (see below).
3. **Conflicts with the in-flight Scope.php cluster.** Plans P6-equivalent (C6 + C7 + C8 + M1 + M3 + M4 + H9 + L2) all touch `src/Internal/Workflow/Process/Scope.php`. Wiring `DeferredFiber` here would block parallelization of every one of them.
4. **Sequence sense.** If we later choose Option B for genuine reasons (e.g. M3 — skip Fiber wrap for Generator workflows), we will resurrect the class from `git history` — that is cheaper than carrying dead, misleading code today.

Therefore: delete now, revisit only if a future plan documents a concrete reason.

### What gets done (Option A)

1. **Delete** `src/Experiments/Fibers/DeferredFiber.php` (entire file, 138 lines).
2. **Edit** `src/Internal/Workflow/Process/CoroutineInterface.php` — rewrite the class-level docblock (lines 14–22) to drop the `DeferredFiber` reference. Suggested replacement (keep file/license header intact):

```php
/**
 * Coroutine driver contract used by {@see Scope} to advance a workflow handler.
 *
 * Implemented by {@see \Temporal\Internal\Workflow\Process\DeferredGenerator}.
 *
 * @internal
 * @psalm-internal Temporal\Internal
 */
```

No other text in the interface changes (per-method docblocks already describe the contract abstractly and do not name `DeferredFiber`).

### Files touched

| Path | Change |
|---|---|
| `src/Experiments/Fibers/DeferredFiber.php` | **delete** (138 lines) |
| `src/Internal/Workflow/Process/CoroutineInterface.php` | rewrite class docblock — drop `DeferredFiber` mention (≈4 lines changed) |

No production code path references the deleted class. No autoloader entry to update — Composer PSR-4 resolves the namespace from the file's presence on disk.

## Verification

Run, in order:

1. `composer cs:diff` — ensure docblock change is style-clean.
2. `composer psalm` — confirm no dangling `@see` references (Psalm warns on unresolved `@see` targets at error level 2). Expected: clean run, no new issues.
3. `composer test:unit` — full Unit suite must pass.
4. `composer test:accept-fast` — fast acceptance suite must pass (this is the suite that actually exercises the Fiber bridge end-to-end through `Scope::createFiberHandler`).

Acceptance: all four commands exit zero with no behavioural diff vs. `master..HEAD` baseline on this branch.

## Out of scope

- Option B (wiring `DeferredFiber` into `createCoroutine`) — explicitly deferred. If a future plan resurrects the class, it **must** include a unit test on `DeferredFiber::handleException` covering the silent-swallow behaviour (`catch (\Throwable) { /* Do nothing. */ }` at `DeferredFiber.php:130–132`), because that branch has zero current coverage and changes observable error propagation.
- Any unrelated Scope.php cleanup (M3, M4, H9, L2) — owned by the Scope.php cluster plan.

## Cross-references

- Review: `.ai-factory/research/fibers-review.md`
  - M2 (lines 287–293) — primary finding
  - Open question #1 (line 442) — `DeferredFiber.php` ship-or-delete
  - Phase 7 (lines 497–501) — action-plan slot
- Bridge code: `src/Internal/Workflow/Process/Scope.php:470–518` (`createCoroutine`, `createFiberHandler`)
- Dead-code search: `grep -rn "DeferredFiber" src/ tests/` (2 hits, both inside the dead file's own ecosystem)

## Implementation-readiness checklist

- [x] Single decision made (Option A, justified above)
- [x] Files and exact line ranges identified
- [x] Replacement docblock text provided verbatim
- [x] Verification commands listed (`cs:diff`, `psalm`, `test:unit`, `test:accept-fast`)
- [x] Parallelization claim explicit (safe vs. P6 cluster)
- [x] Subtle behaviour of `handleException` documented as a constraint for any future Option B revisit
