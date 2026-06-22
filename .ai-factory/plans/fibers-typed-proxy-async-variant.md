# Plan: Add typed-async proxy for Fibers child-workflow/activity stubs (ChildWorkflow/Signal + Signal/ChildWorkflow)

**Branch:** fibers
**Date:** 2026-05-24
**Mode:** full
**Type:** new-api / fibers-experimental
**Source finding:** `.ai-factory/research/RESEARCH.md` — Subagents 2, 4, 5 converged on the same recommendation: typed `FiberProxy::__call` unconditionally auto-awaits, breaking the start→signal→await pattern in 2 acceptance tests.

## Settings

- Testing: yes — fix is validated when both `Harness/ChildWorkflow/Fibers/Signal/SignalTest::check` and `Harness/Signal/Fibers/ChildWorkflow/ChildWorkflowTest::check` run green AND the Generator-mode siblings stay green. Add at least one unit test exercising the new proxy directly.
- Docs: minimal — `Experiments\Fibers\*` is `@experimental @internal`, but a one-line note in `docs/runtime/fibers.md` (existing) under "Public API map" is reasonable so the new API surfaces.
- Logging: none — purely API surface addition.
- Roadmap linkage: closes the last 2 `Harness/.../Fibers/*` failures; together with `fibers-fix-scope-cancel-propagation.md` brings Fibers Harness to 47/47.

## Failing scenario (reference)

Both failing tests share the same workflow pattern (Generator-mode original):

```php
$wf = Workflow::newChildWorkflowStub(ChildWorkflow::class, $options);
$handle = $wf->run();                          // returns PromiseInterface (UNAWAITED)
yield $wf->mySignal('child-wf-arg');           // signal sent + yielded
return yield $handle;                          // wait for child result
```

In Fibers mode after mechanical yield-removal:
```php
$wf = Workflow::newChildWorkflowStub(ChildWorkflow::class, $options);   // wraps in FiberProxy
$handle = $wf->run();                          // FiberProxy::__call awaits → blocks parent fiber
$wf->mySignal('child-wf-arg');                 // never reached
return $handle;                                // never reached
```

Result: `TIMEOUT_TYPE_START_TO_CLOSE` on the parent workflow — parent blocked on `$wf->run()`, child blocked waiting for the signal that's never sent, parent's start-to-close timeout fires.

`FiberProxy::__call` source (`src/Experiments/Fibers/FiberProxy.php:25-37`):
```php
public function __call(string $method, array $args): mixed {
    $result = $this->inner->__call($method, $args);
    if ($result instanceof PromiseInterface) {
        return FiberHelper::await($result);   // unconditional await
    }
    throw new \LogicException(...);
}
```

## Cross-SDK precedent (from Subagent 4 survey)

Every official Temporal SDK provides an EXPLICIT start-without-await entry point for typed child workflows:

| SDK | API |
|-----|-----|
| Java | `Promise<T> p = Async.function(stub::method)` — `Async` wrapper around the stub method reference |
| Go | `childWfFuture := workflow.ExecuteChildWorkflow(...)` — direct `ChildWorkflowFuture` return; result fetched via `.Get(ctx, &out)` |
| TypeScript | `const handle = await startChild(...)` — `startChild` returns handle separate from `executeChild` (which awaits) |

PHP Fibers currently has parity for UNTYPED stubs only (`FiberChildWorkflowStub::startAsync()/signalAsync()/getResultAsync()`); typed stubs are the gap.

## Approach

**Chosen approach: `FiberAsyncProxy` — parallel typed proxy that does NOT auto-await.**

This is the same approach independently surfaced by Subagent 2 (Подход A — параллельный typed Fiber stub) and Subagent 5 (Подход 1 — FiberAsyncProxy). It mirrors the existing untyped `FiberChildWorkflowStub` / `FiberActivityStub` pattern: the untyped form has both blocking (`start()/getResult()/signal()`) and async (`startAsync()/getResultAsync()/signalAsync()`) sets; the typed form gets the async-only counterpart via a new factory + new proxy class.

### Why this approach over alternatives

| Approach | Pros | Cons | Why rejected |
|---|---|---|---|
| **A. FiberAsyncProxy (chosen)** | Type-safe, mirrors untyped pattern, explicit user intent | New file (~25 LOC), one new factory method per stub kind | — |
| B. Suffix-routing in `FiberProxy::__call` (`*Async` methods bypass await) | No new class | Relies on naming convention; can't have both `run()` and `runAsync()` on the same typed interface; collisions with user methods named `*Async` | Convention-based, brittle |
| C. Mutating toggle (`$stub->withRawPromises()->run()`) | No new class | Mutating fluent API in an experimental facade is weird; method behavior depends on prior state | API ergonomics worse than A |
| D. Expose `FiberProxy::getInner()` escape hatch | Trivial change | Loses type safety; users hand-craft `FiberHelper::await($inner->__call(...))` | Defeats the purpose of the facade |

## API surface (proposed)

### New file: `src/Experiments/Fibers/FiberAsyncProxy.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * @template T of object
 * @mixin T
 * @experimental
 * @internal
 */
final class FiberAsyncProxy
{
    public function __construct(
        private readonly object $inner,
    ) {}

    /**
     * @return PromiseInterface<mixed>
     */
    public function __call(string $method, array $args): PromiseInterface
    {
        $result = $this->inner->__call($method, $args);
        if (!$result instanceof PromiseInterface) {
            throw new \LogicException(\sprintf(
                'FiberAsyncProxy expects the inner proxy to return a PromiseInterface; got %s.',
                \get_debug_type($result),
            ));
        }
        return $result;
    }
}
```

### New factory methods on `src/Experiments/Fibers/Workflow.php`

```php
/**
 * @template T of object
 * @param class-string<T> $class
 * @return FiberAsyncProxy<T>
 */
public static function newAsyncChildWorkflowStub(
    string $class,
    ?ChildWorkflowOptions $options = null,
): FiberAsyncProxy {
    return new FiberAsyncProxy(\Temporal\Workflow::newChildWorkflowStub($class, $options));
}

/**
 * @template T of object
 * @param class-string<T> $class
 * @return FiberAsyncProxy<T>
 */
public static function newAsyncActivityStub(
    string $class,
    ?ActivityOptionsInterface $options = null,
): FiberAsyncProxy {
    return new FiberAsyncProxy(\Temporal\Workflow::newActivityStub($class, $options));
}

/**
 * @return FiberAsyncProxy<object>
 */
public static function newAsyncExternalWorkflowStub(
    string $workflowId,
    ?string $runId = null,
): FiberAsyncProxy {
    return new FiberAsyncProxy(\Temporal\Workflow::newExternalWorkflowStub($workflowId, $runId));
}
```

### Usage pattern in failing tests (post-fix)

```php
$wf = Workflow::newAsyncChildWorkflowStub(ChildWorkflow::class, $options);
$handle = $wf->run();                                       // PromiseInterface — NOT awaited
$signaled = $wf->mySignal('child-wf-arg');                  // PromiseInterface — NOT awaited
FiberHelper::await($signaled);                              // ensure signal command flushed
return FiberHelper::await($handle);                         // wait for child result
```

Or, equivalently using Promise combinators:
```php
$wf = Workflow::newAsyncChildWorkflowStub(ChildWorkflow::class, $options);
$handle = $wf->run();
return FiberHelper::await(
    $wf->mySignal('child-wf-arg')->then(fn() => $handle)
);
```

## Tasks

### Task A — Create `FiberAsyncProxy` class

File: `src/Experiments/Fibers/FiberAsyncProxy.php`.

Per project rules (CLAUDE.md "Match the existing style"): mirror the structure of `FiberProxy.php` — same file header, same namespace, same `final` modifier, same constructor pattern, same `\LogicException` shape. No comments beyond the experimental docblock.

### Task B — Add factory methods to `Workflow.php`

Add three methods to `src/Experiments/Fibers/Workflow.php`, alongside the existing `newChildWorkflowStub`/`newActivityStub`/`newExternalWorkflowStub`:
1. `newAsyncChildWorkflowStub(string $class, ?ChildWorkflowOptions $options = null): FiberAsyncProxy`
2. `newAsyncActivityStub(string $class, ?ActivityOptionsInterface $options = null): FiberAsyncProxy`
3. `newAsyncExternalWorkflowStub(string $workflowId, ?string $runId = null): FiberAsyncProxy`

Position: directly after the matching non-async factory methods (read locality — readers find both forms together).

### Task C — Fix the 2 failing tests

#### C.1 — `tests/Acceptance/Harness/ChildWorkflow/Fibers/SignalTest.php`

Original workflow body uses `Workflow::newChildWorkflowStub(ChildWorkflow::class)` + `$workflow->run()` + `$workflow->signal('unblock')` + `return $handle`. Rewrite to:

```php
$workflow = Workflow::newAsyncChildWorkflowStub(
    ChildWorkflow::class,
    \Temporal\Workflow\ChildWorkflowOptions::new()
        ->withTaskQueue(Workflow::getInfo()->taskQueue)
);
$handle = $workflow->run();
$signaled = $workflow->signal('unblock');
FiberHelper::await($signaled);
return FiberHelper::await($handle);
```

Add import: `use Temporal\Experiments\Fibers\FiberHelper;`.

#### C.2 — `tests/Acceptance/Harness/Signal/Fibers/ChildWorkflowTest.php`

Same rewrite pattern, with `mySignal('child-wf-arg')` instead of `signal('unblock')`.

### Task D — Add unit tests

New file: `tests/Unit/Experiments/Fibers/FiberAsyncProxyTestCase.php` (suffix `TestCase.php` per naming rule).

Test cases:
1. `__call` returns the inner's promise unchanged (no await, no FiberHelper invocation). Verify by asserting the returned value is the exact `PromiseInterface` instance produced by the inner's `__call`, not a wrapper.
2. Non-promise inner result throws `\LogicException` with the full exact message (per project rule "Exception tests: assert full messages, not fragments"). Cover both string scalar return and `null` return — both should throw.
3. Multiple sequential `__call`s on different methods of the same proxy work independently (no shared state); verify by mocking inner with distinct return promises per method name and asserting each call routes correctly.
4. **Mixed-mode interop**: a workflow body that uses BOTH `Workflow::newChildWorkflowStub` (sync proxy) AND `Workflow::newAsyncChildWorkflowStub` (async proxy) in the same `run()` does not deadlock; each proxy retains its own behavior. (Cover via stub-level test — exercise both proxies on the same inner ChildWorkflowProxy and assert the sync one awaits, the async one returns a promise.)
5. **`@mixin T` reflection**: verify Psalm/IDE static-analysis sees the inner type's method list — declare a fake `interface Demo { public function action(): \React\Promise\PromiseInterface; }` and assert `(new FiberAsyncProxy(...))->action()` resolves at static-analysis level. (Acceptable as Psalm tag-only test if PHPUnit can't drive Psalm.)

Required setup per `aif-implement/SKILL.md` rule "Fiber unit tests must reset Facade context in tearDown":
```php
protected function tearDown(): void {
    Facade::setCurrentContext(null);
}
```

### Task E — Update transform script (defensive)

Update `/tmp/fibers_transform.py` (or wherever the canonical Fibers transformation script lives in the repo) to detect the `$wf->run() + $wf->signal() + return $handle` pattern in originals and emit a warning or auto-apply the `newAsyncChildWorkflowStub` rewrite. This is a forward-looking guard so future test-replication runs don't reproduce this bug.

Pattern detector (Python pseudo-code):
```
if file contains: 
    "Workflow::newChildWorkflowStub" AND
    /\$\w+->run\(\)\s*;[\s\S]+\$\w+->\w+Signal\(/
then: emit WARN for that file
```

### Task F — Run full regression

1. `composer psalm` — must stay green (new generic `@template T` annotation on `FiberAsyncProxy<T>` is type-system-only; no runtime change).
2. `composer cs:diff` — must report zero style violations on the new file.
3. `composer test:unit` — green; new `FiberAsyncProxyTestCase` runs.
4. `composer test:arch` — green; `FiberAsyncProxy` lives in `src/Experiments/Fibers/` so no boundary violation.
5. `composer test:func` — green; not affected.
6. `composer test:accept-fast` — green; especially watch `Extra/Activity/Fibers/CancelTryCancelTest` (existing Fiber scope flow) and `Extra/Plugin/Fibers/ClientPluginTest`.
7. `composer test:accept-slow` — green; both `Harness/ChildWorkflow/Fibers/Signal` and `Harness/Signal/Fibers/ChildWorkflow` pass; Generator siblings untouched.

## Cross-plan relationship

This plan SUPERSEDES the prior `.ai-factory/skill-context/aif-implement/SKILL.md` rule **"Typed child workflow proxy auto-awaits — use untyped stub for start→signal→await patterns"**. After this plan lands, the first-class solution for typed start→signal→await is `Workflow::newAsyncChildWorkflowStub(C::class)`, not the untyped fallback. Update `aif-implement/SKILL.md` and `aif-plan/SKILL.md` in the implementation pass — replace the "use untyped stub" guidance with "use `newAsyncChildWorkflowStub` for typed patterns; use untyped stub when the child workflow class isn't statically known."

**Independence from `fibers-fix-scope-cancel-propagation.md`:** this plan is independent and can ship without it. It does not touch `FiberScope` or any cancel-propagation code. The 2 affected tests do not use `Workflow::async` — only direct child workflow stubs. The two plans address orthogonal symptoms and can be executed in either order.

**Recommended order (per reviewer consensus):** Plan B first (this plan — zero regression risk, fully scoped, clear API surface), then Plan A (investigation-driven, broader code reach). Shipping Plan B first reduces the failing-test count from 3 → 1, narrowing Plan A's blast radius.

## Out of scope

- `CancelAbandon/childWorkflowInClosingInnerScope` — separate plan `fibers-fix-scope-cancel-propagation.md`.
- Generic `Promise::race/all/any/some` helpers in the Fiber facade — could be done later if `FiberHelper::await(Promise::race(...))` ergonomics become friction.
- Migration of OTHER acceptance tests to `FiberAsyncProxy` — only apply where the start+signal+await pattern requires it (the 2 failing tests). Other tests that use `Workflow::newChildWorkflowStub` (`ChildWorkflow/Fibers/Result`, `ChildWorkflow/Fibers/CancelAbandon`, etc.) work fine with the blocking typed proxy — do not over-migrate.

## Risks

- **R1 — Type inference for `FiberAsyncProxy<T>`**: Psalm/IDEs handle `@mixin T` on `FiberProxy<T>` already. Verify the same annotation on `FiberAsyncProxy<T>` produces method-completion on `$wf->run()` etc. If not, add explicit method-list to the docblock (acceptable; matches Java's `Async.function(stub::method)` slight ergonomic loss).
- **R2 — Forgotten await**: a user calling `newAsyncChildWorkflowStub` and forgetting `FiberHelper::await` will get a `PromiseInterface` instance back where they expected a value, and the IDE will warn (return type is `PromiseInterface`). This is acceptable — it's the SAME tradeoff as untyped `startAsync`. Document in `docs/runtime/fibers.md`.
- **R3 — Generator-mode parity**: Generator-mode users have ONE API (`Workflow::newChildWorkflowStub`) that returns `ChildWorkflowProxy` which returns promises directly. Fibers users now have TWO APIs (sync `newChildWorkflowStub` + async `newAsyncChildWorkflowStub`). Asymmetry is intentional — Fibers users opt out of auto-await only when needed.
- **R4 — Untyped escape hatch obviated**: `FiberChildWorkflowStub::*Async` methods may now feel redundant when typed-async covers the same cases. Don't remove them — they handle untyped (string-type) child workflows where the class isn't known.

## Acceptance criteria

- [ ] `src/Experiments/Fibers/FiberAsyncProxy.php` created (~25 LOC, mirrors `FiberProxy.php`).
- [ ] Three factory methods added to `src/Experiments/Fibers/Workflow.php`.
- [ ] `tests/Acceptance/Harness/ChildWorkflow/Fibers/SignalTest.php` rewritten using the new API; test passes.
- [ ] `tests/Acceptance/Harness/Signal/Fibers/ChildWorkflowTest.php` same; test passes.
- [ ] Generator-mode siblings (`Harness/ChildWorkflow/SignalTest`, `Harness/Signal/ChildWorkflowTest`) untouched, still green.
- [ ] `tests/Unit/Experiments/Fibers/FiberAsyncProxyTestCase.php` added with the 3 test cases.
- [ ] Full pyramid green (`psalm && unit && arch && func && accept-fast && accept-slow`).
- [ ] No comments added beyond the experimental docblock on the new class.
- [ ] No `markTestSkipped`/`markTestIncomplete` anywhere.
- [ ] `composer cs:diff` reports zero style violations on the new file.
