# Plan: Fix Fiber facade API findings (Workflow + FiberHelper)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** API correctness / parity with base `\Temporal\Workflow`
**Source finding:** `.ai-factory/research/fibers-review.md` — H1, H2, H7, H8, L3, L4, M5, M6, M11

## Settings

- Testing: no (test code lives in plan **P12 — Fibers facade acceptance scenarios**; this plan only edits production code under `src/Experiments/Fibers/`)
- Docs: no (PHPDoc within edited files is part of the fix; no new docs/ pages)
- Logging: minimal — each task is a localized edit
- Roadmap linkage: Phase 4 (API completeness) and Phase 5 (static-analysis & typing) of the action plan in `.ai-factory/research/fibers-review.md:455–502`

## Scope statement — file locks

This plan **LOCKS** the following two files for its full duration:

- `src/Experiments/Fibers/Workflow.php`
- `src/Experiments/Fibers/FiberHelper.php`

Cannot be parallelized with plan **P8 (stub decorators)** at the import/refactor
level — both plans share concepts and may overlap on PHPDoc patterns — **but
P8 touches different files** (`FiberProxy.php`, `FiberActivityStub.php`,
`FiberChildWorkflowStub.php`, `FiberExternalWorkflowStub.php`), so file-level
merge is OK. Coordinate the merge order via PR sequencing, not via worktree
locks.

Tasks **within** this plan must be executed sequentially in a single worktree —
all Group A tasks edit the same `Workflow.php` file, so parallel branches
would conflict on every hunk.

## Constraint: no test code

Acceptance tests for the behavioral fixes below (Mutex acceptance in
`awaitWithTimeout`, `sideEffect` with options, `runLocked` auto-await,
`gather` cancellation, `FiberHelper::await` out-of-context error) are owned
by plan **P12 — Fibers facade acceptance scenarios**. Do **not** add tests
in this plan. Existing acceptance and unit suites must continue to pass; if
any pre-existing test breaks because of a fix here, the fix is wrong (or the
test was masking the bug — escalate before "fixing" the test).

## Pre-flight verification (SPIKE)

Before starting **Group B / Task B1** (M11 — throw on out-of-context), run a
SPIKE to enumerate every call site of `FiberHelper::await` and classify each
one as **safe** (always inside a workflow Fiber) or **unsafe** (may legitimately
run outside a Fiber and rely on the current silent-passthrough behavior).

### SPIKE commands

```bash
rtk grep -rn "FiberHelper::await\|FiberHelper\\\\await" src/ tests/
```

### Known call sites (from current branch, 2026-05-23)

Production code (`src/Experiments/Fibers/`):

| File | Line | Context | Classification |
|---|---|---|---|
| `Workflow.php` | 211 | inside `await(...)` facade | safe (workflow-only) |
| `Workflow.php` | 219 | inside `awaitWithTimeout(...)` (after H1 fix) | safe |
| `Workflow.php` | 224 | inside `getVersion(...)` | safe |
| `Workflow.php` | 233 | inside `sideEffect(...)` (after H2 fix) | safe |
| `Workflow.php` | 241 | inside `timer(...)` | safe |
| `Workflow.php` | 257 | inside `continueAsNew(...)` | safe |
| `Workflow.php` | 266 | inside `executeChildWorkflow(...)` | safe |
| `Workflow.php` | 275 | inside `executeActivity(...)` | safe |
| `Workflow.php` | 280/285/290 | `uuid()/uuid4()/uuid7()` | safe |
| `Workflow.php` | 383 | `runLocked()` else branch (`$mutex instanceof BaseMutex`) | safe — runs inside an `async()` scope |
| `Promise.php` | 24/32/40/49/57/65 | `Promise::all/any/some/race/map/reduce` | safe (workflow-only) |
| `FiberProxy.php` | 30 | `__call` when inner returned a Promise | safe — proxies hold workflow-scoped stubs |
| `FiberActivityStub.php` | 38 | `execute()` | safe |
| `FiberChildWorkflowStub.php` | 31/49/57/65/73 | various awaiting methods | safe |
| `FiberExternalWorkflowStub.php` | 32/37 | `signal/cancel` | safe |
| `Mutex.php` | 31 | `lock()` | **unsafe** — H6 documents that `Mutex::lock()` is called from workflow constructors **before** `Scope::createFiberHandler` wraps execution. Constructor runs outside Fiber-mode. |

Test code:

| File | Line | Context | Classification |
|---|---|---|---|
| `tests/Acceptance/Extra/Stability/Fibers/DynamicSignalWithPromisesTest.php` | 71 | inside a workflow body | safe |
| `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` | 74 | inside a workflow body | safe |

### SPIKE outcome → impact on B1

The **only** non-workflow call site is `Mutex::lock()` (H6). Throwing
`OutOfContextException` from `FiberHelper::await` would crash workflow
constructors that pre-lock the mutex. Two options:

1. **Throw + fix Mutex (recommended).** Throw `OutOfContextException` from
   `FiberHelper::await` when not in fiber mode. Separately, change
   `Mutex::lock()` to detect the out-of-context case and call the underlying
   `BaseMutex::lock()` directly without going through `FiberHelper::await`.
   This makes the constructor-time pre-lock explicit and the runtime behavior
   well-defined.

2. **Throw + document Mutex as workflow-body-only.** Throw from
   `FiberHelper::await` and document `Mutex::lock()` as illegal outside a
   workflow Fiber. Migration burden: any test or sample that pre-locks in a
   constructor breaks.

This plan picks **Option 1** — the constructor-time lock is the realistic
pattern (`MutexYieldTest.php:67` does this; users will too). Task B1 ships
both edits: `FiberHelper::await` throws, and `Mutex::lock()` is updated to
preserve the constructor-time behavior without going through `FiberHelper`.

H6 itself ("rename or document `MutexYieldTest` constructor lock") is owned
by a separate plan; this plan only ensures the underlying primitive
(`Mutex::lock()`) keeps working in the constructor case.

---

## Group A — `src/Experiments/Fibers/Workflow.php`

All tasks below edit the same file. Execute sequentially in one worktree.

### A1 — H1 — Add `Mutex` to the `awaitWithTimeout` parameter union

**Where:** `src/Experiments/Fibers/Workflow.php:217`

**Current signature:**

```php
public static function awaitWithTimeout($interval, callable|BaseMutex|PromiseInterface ...$conditions): mixed
```

**Target signature** (matches sibling `await()` at line 209):

```php
public static function awaitWithTimeout($interval, callable|BaseMutex|Mutex|PromiseInterface ...$conditions): mixed
```

`Mutex` here is `Temporal\Experiments\Fibers\Mutex` — already imported by
the file (used in `runLocked` signature). No new `use` statement required.

**Rationale:** Base `\Temporal\Workflow::awaitWithTimeout` accepts
`Fibers\Mutex` (see `src/Workflow.php:337` — the base method's union
already includes `\Temporal\Experiments\Fibers\Mutex`). The Fiber facade
narrows the union and rejects the very type it was designed to accept.

**Verification (no new tests in this plan):**

- `composer cs:diff` clean for this file.
- `composer psalm` clean for this file.
- Plan P12 owns the acceptance test:
  `awaitWithTimeout('10s', new \Temporal\Experiments\Fibers\Mutex())`
  inside a Fiber workflow — must not TypeError.

### A2 — H2 — Restore `?SideEffectOptions $options = null` on `sideEffect`

**Where:** `src/Experiments/Fibers/Workflow.php:227–234`

**Current shape:**

```php
/**
 * @template TReturn
 * @param callable(): TReturn $value
 */
public static function sideEffect(callable $value): mixed
{
    return FiberHelper::await(\Temporal\Workflow::sideEffect($value));
}
```

**Target shape:**

```php
/**
 * @template TReturn
 * @param callable(): TReturn $value
 * @return TReturn
 */
public static function sideEffect(callable $value, ?SideEffectOptions $options = null): mixed
{
    return FiberHelper::await(\Temporal\Workflow::sideEffect($value, $options));
}
```

Add `use Temporal\Common\SideEffectOptions;` to the imports block (sorted
alphabetically with the existing `use`s — should land between
`Temporal\Common\SearchAttributes\SearchAttributeUpdate` and
`Temporal\DataConverter\Type`).

**Rationale:** Base method (see `src/Workflow.php:575`) is
`sideEffect(callable $value, ?SideEffectOptions $options = null)`. Dropping
the second parameter silently breaks anyone migrating from base to Fiber
facade who passed options.

**Verification:**

- `composer cs:diff` clean.
- `composer psalm` clean — the existing PHPDoc `@template TReturn` already
  exists; adding `@return TReturn` is a follow-on for L4 (see A6).
- P12 owns the acceptance test: call with non-null options and assert the
  options propagate.

### A3 — H8 — Make `runLocked` auto-await Promise returns from the callable

**Where:** `src/Experiments/Fibers/Workflow.php:369–392`

**Current body:**

```php
public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface
{
    return self::async(static function () use ($mutex, $callable): mixed {
        if ($mutex instanceof Mutex) {
            $mutex->lock();
        } else {
            FiberHelper::await($mutex->lock());
        }

        try {
            return $callable();
        } finally {
            $mutex->unlock();
        }
    });
}
```

**Target body:**

```php
public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface
{
    return self::async(static function () use ($mutex, $callable): mixed {
        if ($mutex instanceof Mutex) {
            $mutex->lock();
        } else {
            FiberHelper::await($mutex->lock());
        }

        try {
            $result = $callable();
            if ($result instanceof PromiseInterface) {
                $result = FiberHelper::await($result);
            }
            return $result;
        } finally {
            $mutex->unlock();
        }
    });
}
```

**Rationale:** Base `runLocked` (`src/Workflow.php:1173–1182`) uses
`yield $callable()` which transparently unwraps both Generators and
Promises. The Fiber rewrite at line 387 (`return $callable();`) is purely
synchronous and never unwraps. If the user calls `Fibers\Workflow::createTimer`
(raw promise, L3 below) or any other promise-returning helper inside the
locked callable, the surrounding scope resolves to the raw `PromiseInterface`
instead of its eventual value.

Why explicit `instanceof PromiseInterface` instead of always calling
`FiberHelper::await($result)`: `FiberHelper::await` is typed
`PromiseInterface $promise` — calling it with a scalar/object would TypeError.
The runtime check keeps the synchronous-callable path zero-overhead.

**Generator handling note:** This task does **not** add Generator unwrapping
here. Generator-returning callables remain handled by the broader M5/M6
decision in task A5 below. A reader concerned about parity with base
`runLocked` should read A5 first.

**Verification:**

- `composer cs:diff` and `composer psalm` clean.
- P12 owns: `runLocked($m, fn() => Fibers\Workflow::createTimer(1))` —
  surrounding scope must resolve to `null` (post-timer value), not to a
  `PromiseInterface`.

### A4 — H7 — Tighten `gather` return type; document non-cancellability

**Where:** `src/Experiments/Fibers/Workflow.php:394–411`

**Current shape:**

```php
/**
 * Execute multiple tasks in parallel and wait for all results.
 *
 * ```php
 *  [$a, $b] = Workflow::gather(
 *      fn() => $activity->methodA(),
 *      fn() => $activity->methodB(),
 *  );
 * ```
 *
 * @return array<mixed>
 */
public static function gather(callable ...$tasks): mixed
{
    $scopes = \array_map(static fn(callable $task) => self::async($task), $tasks);

    return Promise::all($scopes);
}
```

**Target shape:**

```php
/**
 * Execute multiple tasks in parallel and wait for all results.
 *
 * ```php
 *  [$a, $b] = Workflow::gather(
 *      fn() => $activity->methodA(),
 *      fn() => $activity->methodB(),
 *  );
 * ```
 *
 * Cancellation: this helper does NOT expose the underlying scopes, so a
 * surrounding scope cancellation can stop further iteration of the gather
 * but cannot individually cancel in-flight inner scopes. If you need
 * per-task cancellation, hold the `async()` scopes yourself and cancel
 * them directly.
 *
 * @return array<int, mixed>
 */
public static function gather(callable ...$tasks): array
{
    $scopes = \array_map(static fn(callable $task) => self::async($task), $tasks);

    return Promise::all($scopes);
}
```

Note that `Promise::all` here is `Temporal\Experiments\Fibers\Promise::all`
(see `src/Experiments/Fibers/Promise.php:22`) — it already calls
`FiberHelper::await`, so by the time `gather` returns the value is an
array, not a `PromiseInterface`. The return-type tighten from `mixed` to
`array` is therefore safe and accurately reflects runtime behavior.

**Rationale:**

- PHPDoc claimed `array<mixed>`, declared type was `mixed` — the contract
  was self-contradictory.
- Cancellation-propagation gap is documented (not fixed). A `gatherWithScopes`
  variant that returns both the array and the scope handles is out of scope
  for this plan — it's a new API surface, not a bug fix. Owned by a future
  enhancement plan if demand materializes.

**Verification:**

- `composer cs:diff` and `composer psalm` clean — `array<int, mixed>` is
  a precise psalm-friendly shape.
- P12 owns: `gather(fn() => sleep, fn() => sleep); cancel surrounding scope;`
  document current behavior (inner scopes NOT cancelled). The test
  asserts the current, documented semantics — not the eventually-desired
  cancellation propagation.

### A5 — M5 + M6 — Decide Generator handling for async/asyncDetached/runLocked/gather; align PHPDocs

**Where:** `src/Experiments/Fibers/Workflow.php:185–212` (`async`,
`asyncDetached`) and the methods edited in A3/A4.

**Decision:** Document Generators as **legal but discouraged** in Fiber
mode, and **widen the PHPDoc unions** to match the base methods'
`callable(): TReturn|\Generator<...>` shape. Do **NOT** runtime-reject
Generator-returning callables — they still work today via
`Scope::next:448` attaching them as sub-scopes (per the review note at
research lines 313–315), and runtime rejection would be a breaking change
beyond the scope of this plan.

Rationale for widening rather than narrowing: forcing users to refactor
all Generators out of `async`/`gather`/`runLocked` callables is a much
bigger migration than this plan warrants. The Fiber facade is a
*drop-in replacement* (per the file's own header comment at line 27), and
narrowing the PHPDoc made it not a drop-in.

**Target PHPDoc shapes:**

```php
/**
 * @template TReturn
 * @param callable(): (TReturn|\Generator<mixed, mixed, mixed, TReturn>) $task
 * @return CancellationScopeInterface<TReturn>
 */
public static function async(callable $task): CancellationScopeInterface

/**
 * @template TReturn
 * @param callable(): (TReturn|\Generator<mixed, mixed, mixed, TReturn>) $task
 * @return CancellationScopeInterface<TReturn>
 */
public static function asyncDetached(callable $task): CancellationScopeInterface

/**
 * @template T
 * @param Mutex|BaseMutex $mutex
 * @param callable(): (T|\Generator<mixed, mixed, mixed, T>) $callable
 * @return CancellationScopeInterface<T>
 */
public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface

/**
 * @param callable(): mixed ...$tasks
 * @return array<int, mixed>
 */
public static function gather(callable ...$tasks): array
```

**Runtime semantics for `runLocked` with a Generator return:** the
`$callable()` invocation in A3's edit will return a `\Generator` instance
(not a `PromiseInterface`), so the `$result instanceof PromiseInterface`
branch is skipped, and the Generator falls through to `return $result;`.
`Scope::next` then attaches it as a sub-scope — same behavior as base
`runLocked`'s `yield $callable()`. No additional code change needed beyond
A3.

For `gather`, generator callables work identically: each
`self::async($task)` already routes Generator-returning callables through
`Scope::next:448`. Already correct.

**No-comment rule check:** The CLAUDE.md "Comments" rule bans narrative
prose. The cancellation paragraph added to `gather` in A4 documents a
genuine hidden invariant ("scopes not exposed → no per-task cancel") that
a reader cannot recover from the body — borderline but defensible under
the "hidden constraint" exception. Keep it short (3–4 lines max). The
`@param`/`@return` PHPDoc updates are type annotations, not prose, and
are explicitly allowed.

**Verification:**

- `composer cs:diff` and `composer psalm` clean.
- No new tests; existing Fiber acceptance tests must still pass.

### A6 — L4 — Add `@template T` PHPDoc to awaiting methods that take `$returnType`

**Where:** `src/Experiments/Fibers/Workflow.php` — methods declared
`mixed` that have a base-method counterpart with `@return PromiseInterface<T>`
and a `$returnType` parameter:

- `getVersion` (line 222) — base returns `PromiseInterface<int>`
- `sideEffect` (line 231, after A2) — base is `PromiseInterface<TReturn>`
- `continueAsNew` (line 252) — base returns `PromiseInterface<mixed>` (no
  template — skip)
- `executeChildWorkflow` (line 260)
- `executeActivity` (line 269)
- `uuid`, `uuid4`, `uuid7` (lines 278, 283, 288) — base returns
  `PromiseInterface<UuidInterface>`

**Target shape (representative):**

```php
/**
 * @template T
 * @param non-empty-string $type
 * @param list<mixed> $args
 * @param Type|string|\ReflectionType|\ReflectionClass<T>|null $returnType
 * @return T
 */
public static function executeActivity(
    string $type,
    array $args = [],
    ?ActivityOptionsInterface $options = null,
    Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
): mixed {
    return FiberHelper::await(\Temporal\Workflow::executeActivity($type, $args, $options, $returnType));
}
```

Add an `@import-type` for `UuidInterface` from `Ramsey\Uuid\UuidInterface`
(or import the class via `use`) only where actually referenced in PHPDoc.
Don't speculatively import what isn't used.

**Scope guard:** Only methods with a single, well-typed return are
templated. Do NOT add `@template T` to `await`, `awaitWithTimeout`,
`timer` (return `null`-equivalent) — those stay `mixed`/`bool`/`null`
per the base.

**Rationale:** Type-narrowing currently loses generic inference from the
base facade — IDE/Psalm can't propagate the `$returnType` argument into
the call site's variable type. The base method signatures are the source
of truth; the Fiber facade should be at parity.

**Verification:**

- `composer psalm` clean — Psalm should now correctly infer
  `$result = Fibers\Workflow::executeActivity('foo', [], null, MyDto::class)`
  as `MyDto` (rather than `mixed`).
- No runtime change. Existing tests must pass.

### A7 — L3 — Rename `createTimer` → `timerPromise`; keep `timer` as the awaiting variant

**Where:** `src/Experiments/Fibers/Workflow.php:243–250` (declaration) and
`tests/Acceptance/Extra/Workflow/Fibers/UserMetadataTest.php:211` (the
one in-tree caller).

**Current shape:**

```php
/**
 * @param \DateInterval|string|int $interval
 * @return PromiseInterface<void>
 */
public static function createTimer($interval, ?TimerOptions $options = null): PromiseInterface
{
    return \Temporal\Workflow::timer($interval, $options);
}
```

**Target shape:**

```php
/**
 * Returns the raw, unawaited timer promise.
 *
 * Use this when you need a `PromiseInterface` handle (e.g. to compose
 * with `awaitWithTimeout`, `Promise::any`, etc.). For the auto-awaiting
 * variant, use {@see timer()}.
 *
 * @param \DateInterval|string|int $interval
 * @return PromiseInterface<null>
 */
public static function timerPromise($interval, ?TimerOptions $options = null): PromiseInterface
{
    return \Temporal\Workflow::timer($interval, $options);
}
```

Also update the single in-tree caller
`tests/Acceptance/Extra/Workflow/Fibers/UserMetadataTest.php:211`:

```diff
-        $timer = Workflow::createTimer(30, TimerOptions::new()->withSummary('test timer summary'));
+        $timer = Workflow::timerPromise(30, TimerOptions::new()->withSummary('test timer summary'));
```

**Breaking-change disclosure:** The class header already declares
`@experimental` (line 34) and the namespace is `Temporal\Experiments\Fibers\`.
Per established SDK convention, the experimental contract is explicit:
public APIs in this namespace may change between minor releases. Renaming
`createTimer` is therefore **permitted** but should be called out in the
PR description and (eventually) a CHANGELOG / migration note under the
experimental section.

External callers (outside this repo) using `Workflow::createTimer` will
break at upgrade time. Acceptable trade-off given:
1. `createTimer` is misleading (the symmetric `createActivity` /
   `createChildWorkflow` do not exist — users guessing by analogy will
   reach for `newActivityStub` and miss `createTimer`).
2. `timerPromise` is self-describing — "returns a Promise of timer
   completion".
3. The rename is one-shot; further churn unlikely.

**Why not delete instead of rename:** the raw-promise variant has a
legitimate use case — composition with `awaitWithTimeout` /
`Promise::any` requires an unawaited handle. Removing it would force
users to import base `\Temporal\Workflow::timer` directly, breaking the
"drop-in replacement" promise.

**Verification:**

- `composer cs:diff` and `composer psalm` clean.
- `composer test:accept` — the renamed caller in `UserMetadataTest.php`
  must continue to pass.
- P12 will add an explicit acceptance test using `timerPromise` to lock
  in the rename.

---

## Group B — `src/Experiments/Fibers/FiberHelper.php`

Only one file, one task — but read the SPIKE outcome at the top of this
plan first (the fix bundles a coordinated edit to `Mutex.php`).

### B1 — M11 — Throw `OutOfContextException` from `FiberHelper::await` when not in fiber-mode

**Where:** `src/Experiments/Fibers/FiberHelper.php:28–39`

**Current body:**

```php
public static function await(PromiseInterface $promise): mixed
{
    $context = Facade::getCurrentContext();

    if ($context instanceof ScopeContext && $context->isFiberMode()) {
        return \Fiber::suspend($promise);
    }

    return $promise;
}
```

**Target body:**

```php
public static function await(PromiseInterface $promise): mixed
{
    $context = Facade::getCurrentContext();

    if (!$context instanceof ScopeContext || !$context->isFiberMode()) {
        throw new OutOfContextException(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );
    }

    return \Fiber::suspend($promise);
}
```

Add `use Temporal\Exception\OutOfContextException;` to the imports block.

**Coordinated edit — `src/Experiments/Fibers/Mutex.php:29–32`:**

The SPIKE established `Mutex::lock()` is the only unsafe call site. Update
it to handle the out-of-context case explicitly rather than relying on the
old silent-passthrough:

```php
public function lock(): mixed
{
    $promise = $this->inner->lock();

    $context = \Temporal\Internal\Support\Facade::getCurrentContext();
    if ($context instanceof \Temporal\Internal\Workflow\ScopeContext && $context->isFiberMode()) {
        return FiberHelper::await($promise);
    }

    return $promise;
}
```

Style note: the `Facade` and `ScopeContext` checks are duplicated from
`FiberHelper::await`. Extract a tiny private helper
`FiberHelper::isInFiberMode(): bool` to avoid the duplication. Wire it:

```php
// FiberHelper.php
public static function isInFiberMode(): bool
{
    $context = Facade::getCurrentContext();
    return $context instanceof ScopeContext && $context->isFiberMode();
}

public static function await(PromiseInterface $promise): mixed
{
    if (!self::isInFiberMode()) {
        throw new OutOfContextException(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );
    }

    return \Fiber::suspend($promise);
}
```

```php
// Mutex.php
public function lock(): mixed
{
    $promise = $this->inner->lock();

    if (FiberHelper::isInFiberMode()) {
        return FiberHelper::await($promise);
    }

    return $promise;
}
```

`FiberHelper::isInFiberMode()` is `public` only because `Mutex.php` (a
sibling, same namespace, not internal) consumes it. The class is still
marked `@internal`; the visibility relaxation is documented by the new
PHPDoc on the method:

```php
/**
 * @internal Sibling Fiber primitives may consume this to gate their own
 *           passthrough behavior. Not part of the public API.
 */
public static function isInFiberMode(): bool
```

(One-line PHPDoc explaining a non-obvious visibility decision falls under
the CLAUDE.md "hidden constraint" exception.)

**Rationale:** Silent passthrough makes misuse hard to spot. A user who
calls `FiberHelper::await` outside a workflow Fiber today gets a raw
`PromiseInterface` back — type-narrowing assumptions (`: void`, `: mixed`)
silently break. Throwing makes the error loud at the call site.

**Test impact:**

The SPIKE found two test call sites:

- `tests/Acceptance/Extra/Stability/Fibers/DynamicSignalWithPromisesTest.php:71`
  — inside a workflow body. Safe; will not trigger the throw.
- `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php:74` —
  inside a workflow body. Safe.

Production `Mutex::lock()` callers in test workflows (e.g.
`MutexYieldTest.php:67`'s constructor call) are handled by the
coordinated `Mutex.php` edit above — they no longer flow through
`FiberHelper::await`'s throwing path.

**Verification:**

- `composer cs:diff` and `composer psalm` clean.
- `composer test:unit` — all unit tests pass (none currently exist for
  Fibers per M14, but the suite must not regress).
- `composer test:accept` — all existing acceptance tests pass. In
  particular `MutexYieldTest`, `MutexRunLockedTest`, and the dynamic
  signal test must remain green.
- P12 owns the acceptance test that *exercises* the new throw path: a
  call to `FiberHelper::await` from outside a workflow (e.g. from a
  unit-test harness) — assert the exception.

---

## Cross-task verification checklist (run after all tasks)

```bash
rtk composer cs:diff
rtk composer psalm
rtk composer test:unit
rtk composer test:arch
rtk composer test:accept-fast
```

If any of the existing Fiber acceptance scenarios listed below regress,
**stop and triage** — do not modify the tests to make them pass; the
production fix is wrong:

- `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php` — exercises
  constructor-time `Mutex::lock()`. Must still pass after B1's Mutex edit.
- `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` —
  exercises both `runLocked` branches and `FiberHelper::await` from a
  workflow body. Must still pass after A3 and B1.
- `tests/Acceptance/Extra/Workflow/Fibers/UserMetadataTest.php` — calls
  `createTimer` (renamed by A7). Caller updated as part of A7.
- `tests/Acceptance/Extra/Stability/Fibers/DynamicSignalWithPromisesTest.php`
  — calls `FiberHelper::await` from a workflow body. Must still pass
  after B1.

---

## Task ordering recommendation

Within a single worktree:

```
A1  (H1 — pure signature widening, 1 line; safe foundation)
A2  (H2 — restore sideEffect param)
A4  (H7 — gather return type; touches PHPDoc + signature)
A5  (M5+M6 — PHPDoc widening for async/asyncDetached; rebases on A2/A4)
A3  (H8 — runLocked auto-await; behavioral)
A6  (L4 — @template T sweep across awaiting methods)
A7  (L3 — rename createTimer → timerPromise; touches one test file)
B1  (M11 — FiberHelper::await throws; bundles Mutex.php coordinated edit)
```

Rationale: pure-typing edits first (A1, A2, A4, A5, A6) to keep the diff
reviewable. Then behavioral edits (A3, A7, B1). B1 is last because it
touches a second file (`Mutex.php`) and its SPIKE pre-flight is the most
sensitive — running it after all other Group A edits means the worker
is in its final shape when the B1 acceptance run executes.

---

## Out of scope

- **H6** (Mutex constructor lock undocumented behavior) — separate plan.
  B1's `Mutex.php` edit ensures the constructor case keeps working, but
  the *documentation* and *test rename* for `MutexYieldTest` belong
  elsewhere.
- **H3, H4** (FiberProxy `@mixin T`, parallel decorator interfaces) —
  owned by plan **P8 — Stub decorators**.
- **All test writing** — owned by plan **P12 — Fibers facade acceptance
  scenarios**.
- **gatherWithScopes variant** — new API surface, not a bug fix.
- **Generator runtime rejection** in `async`/`gather`/`runLocked` — A5
  documents the decision to keep accepting them; runtime reject would be
  a separate enhancement plan.
