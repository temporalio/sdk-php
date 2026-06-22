# Plan: Regression tests for setFiberMode lifecycle (H5, H10, C6/C7/C8)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** test addition / regression coverage
**Source finding:** `.ai-factory/research/fibers-review.md` — H5 (HIGH, lines 205–213), H10 (HIGH, lines 257–266), C6 (CRITICAL, lines 104–126), C7 (CRITICAL, lines 128–142), C8 (CRITICAL, lines 144–159)

## Settings

- Testing: yes — this plan **is** tests. The deliverable is six new test files; the verification step is running them.
- Docs: no — no public API surface changes.
- Logging: standard — file creation only.
- Roadmap linkage: none.

## Scope statement

This plan creates new files under `tests/Unit/Internal/Workflow/Process/`, `tests/Unit/Internal/Workflow/`, and `tests/Acceptance/Extra/Workflow/Fibers/`. **Safe to parallelize internally** — each test file targets an independent finding and lives in its own namespace, so the six file-creation tasks can be dispatched concurrently.

**DEPENDS on plans P5 (`fibers-fix-query-handler.md`) and P6 (`fibers-fix-scope-runtime.md`) being IMPLEMENTED first** — these regression tests are intentionally designed to FAIL on the current master / pre-fix code and to PASS only after the production fixes from P5 and P6 have landed. If this plan is implemented before P5/P6 the tests will fail; that is by design (red-baseline) but the suite must not be merged in that state.

Production code is **not modified by this plan**. The only files touched live under `tests/`. P5 owns the `Process.php` query executor change, and P6 owns the `Scope.php` destroy/cancel/next changes.

## Findings recap

### H5 — No regression for the `setFiberMode` correctness commit (`5be5c6ac`)

Commit `5be5c6ac` moved `setFiberMode(false)` calls into `try/finally` blocks inside `Scope::createFiberHandler` (`src/Internal/Workflow/Process/Scope.php:479–518`). Without a test, a future revert silently regresses. Three sub-paths need coverage:

1. **Synchronous throw during `$fiber->start()`** — the `catch (\Throwable $e)` block at lines 490–493 calls `setFiberMode(false)` then rethrows. Verify the flag is `false` after the throw.
2. **Bridge generator exits via uncaught Fiber exception** — the inner generator's `finally` block at lines 513–515 calls `setFiberMode(false)` regardless of how the generator unwinds (return, throw, or GC-finalization). Verify the flag is reset when the Fiber throws after suspension.
3. **Bridge generator GC'd while suspended** — combined with the P6 fix for C6, when the outer `Scope::destroy()` runs while the bridge generator is still suspended, the `finally` block (now reached via P6's explicit reset) must still produce `isFiberMode() === false`. This is also the H5 bonus item.

### H10 — No multi-level Fiber suspension test

Every existing Fiber acceptance test exercises the Fiber lifecycle at a single level (workflow method → activity, one suspension point). No test runs a 3+-deep call stack with `try/finally` blocks at each level, suspending on activity, throwing, catching, suspending again, completing. The bridge generator's exception-injection path (`$fiber->throw` at line 509) and the `finally`-during-Fiber-suspension semantics are not stressed.

### C6 — `Scope::destroy()` / `Scope::cancel()` don't reset `fiberMode` (fixed in P6)

`Scope::destroy()` at lines 299–312 first calls `$this->scopeContext?->destroy()` (line 302) which unsets the inner references, and then unsets `$this->coroutine`. If the bridge Generator's `finally` runs later during GC, it calls `setFiberMode(false)` on a context whose state was already touched. P6 adds an explicit `$this->scopeContext->setFiberMode(false)` before relinquishing references. Regression target: drive `Scope::destroy()` on a fiberMode-active scope and assert `isFiberMode() === false` afterward.

### C7 — Query handler clones context without resetting fiberMode (fixed in P5)

`Process::setQueryExecutor` clones via `withInput()` which copies `$fiberMode` by value. P5 adds `$context->setFiberMode(false)` after `setReadonly(true)`. Regression target: build a `ScopeContext`, flip `fiberMode = true`, clone via `withInput(...)`, run the **same closure shape** P5 added the reset to, assert the clone observed is `false`.

### C8 — Bare `catch (\Throwable)` at `Scope::next:422–425` (fixed in P6)

When `$this->coroutine->getReturn()` raises (e.g. because the underlying Fiber terminated via exception), the `catch (\Throwable) { $this->onResult(null); }` silently resolves the workflow's deferred to `null`. P6 replaces it with `catch (\Throwable $e) { $this->onException($e); }`. Regression targets:

- **Unit** — drive `Scope::next()` directly with a coroutine whose `getReturn()` throws, assert the scope's promise rejects with the original exception (not resolves to `null`).
- **Acceptance** — Fiber workflow that throws `RuntimeException` synchronously inside an activity callback chain after first suspension; assert workflow stub fails with the expected exception, not resolves with `null`.

## Target end state

Six new test files exist:

```
tests/Unit/Internal/Workflow/Process/ScopeFiberModeLifecycleTestCase.php
tests/Unit/Internal/Workflow/Process/ScopeNextErrorHandlingTestCase.php
tests/Unit/Internal/Workflow/ScopeContextCloneFiberModeTestCase.php
tests/Acceptance/Extra/Workflow/Fibers/DeepCallStackFiberTest.php
tests/Acceptance/Extra/Workflow/Fibers/FiberDestroyDuringSuspendTest.php
tests/Acceptance/Extra/Workflow/Fibers/FiberThrowAfterSuspendTest.php
```

After P5 and P6 have landed, the following commands pass:

```
composer test:unit
composer test:accept-fast
```

Specifically:

- `vendor/bin/phpunit --testsuite=Unit --filter=ScopeFiberModeLifecycleTestCase` — all H5 sub-cases pass.
- `vendor/bin/phpunit --testsuite=Unit --filter=ScopeNextErrorHandlingTestCase` — C8 unit case passes (the workflow's deferred rejects, not resolves to null).
- `vendor/bin/phpunit --testsuite=Unit --filter=ScopeContextCloneFiberModeTestCase` — C7 unit case passes (clone has `isFiberMode() === false`).
- The three acceptance tests run inside `Acceptance-Fast` and produce green tallies for H10, C6 regression, and C8 regression.

On **pre-P5/P6 code**, the unit tests and acceptance tests targeting the corresponding bugs **must fail** — that red baseline is the validation that the tests are meaningful.

## Detailed design — per file

The six files are independent. Each is described below with full intent, public class name, target methods, fixtures, and the production assertion target.

---

### 1. `tests/Unit/Internal/Workflow/Process/ScopeFiberModeLifecycleTestCase.php`

**Namespace:** `Temporal\Tests\Unit\Internal\Workflow\Process`
**Extends:** `PHPUnit\Framework\TestCase`
**Attributes:** `#[CoversClass(\Temporal\Internal\Workflow\Process\Scope::class)]`, `#[UsesClass(\Temporal\Internal\Workflow\ScopeContext::class)]`, `#[UsesClass(\Temporal\Internal\Workflow\Process\DeferredGenerator::class)]`

**Purpose:** H5 — assert that `setFiberMode(false)` runs in all three Fiber-exit paths inside `Scope::createFiberHandler`.

**Why this is a unit test, not an acceptance test:** the `createFiberHandler` closure is a `private` method that takes a `callable` and a `ScopeContext` and returns a `\Closure` whose body builds the Fiber + bridge generator. We do not need the full worker/RR harness — `ReflectionMethod::invoke($scope, ...)` against `createFiberHandler` (after instantiating `Scope` with a stubbed `ServiceContainer`) gives us the exact Fiber bridge in isolation, and the only externally observable side effect is `ScopeContext::isFiberMode()`. That isolation is also what makes the test stable: no Temporal server, no RoadRunner, no timing.

**Fixtures (private helpers inside the test class):**

- `makeScopeContext(): ScopeContext` — constructs a real `ScopeContext` by:
  1. Building a stub `\Temporal\Internal\ServiceContainer` (use `Mockery` or PHPUnit's `createStub` against the readable subset; only `services->loop` and `services->queue` are touched on the codepaths we hit, neither is reached in this test).
  2. Building a real `WorkflowContext` via a partial mock or a minimal `WorkflowContext` constructed with all-stub dependencies. Because `ScopeContext::fromWorkflowContext()` references `$context->services`, `$context->client`, `$context->workflowInstance`, `$context->input`, `$context->getLastCompletionResultValues()`, `$context->handlers`, `$context->readonly`, `$context->continueAsNew`, `$context->trace`, `$context->currentDetails` — we instantiate `ScopeContext` directly without going through `WorkflowContext` if possible (the test only cares about `fiberMode` accessor pair). **Implementation choice (recorded so the implementer doesn't re-derive it):** instantiate `ScopeContext` via `(new \ReflectionClass(ScopeContext::class))->newInstanceWithoutConstructor()` and access only `setFiberMode()` / `isFiberMode()` — those don't touch any other state. This bypasses the broader constructor and is the minimum viable seam.

- `invokeFiberHandler(Scope $scope, callable $handler, ScopeContext $scopeContext): mixed` — uses `(new \ReflectionMethod(Scope::class, 'createFiberHandler'))->invoke($scope, $handler, $scopeContext)` to obtain the inner closure, then calls it with `EncodedValues::empty()`.

**Tests:**

1. `testFiberModeIsResetWhenFiberThrowsSynchronouslyOnStart(): void`
   - Build a handler that immediately throws `RuntimeException::class` with message `'sync-start-throw'`.
   - Set `$scopeContext->setFiberMode(true)` ahead of time (simulate any earlier toggle — in practice the inner `\Fiber` body sets it `true` on entry; we just need to know the post-condition reset works).
   - Invoke the closure inside `expectException(RuntimeException::class)` + `expectExceptionMessage('sync-start-throw')`.
   - **After** the call (use a `try { ... } catch (\RuntimeException $e) { ... }` block so we can both assert the throw and inspect post-state), assert `$scopeContext->isFiberMode() === false`.
   - Production target: `Scope.php` lines 488–493 — the explicit `catch (\Throwable $e) { $scopeContext->setFiberMode(false); throw $e; }` block.

2. `testFiberModeIsResetWhenFiberThrowsAfterSuspension(): void`
   - Build a handler that calls `\Fiber::suspend(new \React\Promise\Promise(static fn() => null));` and then **after the suspend point** throws `RuntimeException::class` with message `'post-suspend-throw'`.
   - Invoke the closure. It returns a `\Generator` (the bridge). Drive the generator: call `->current()` (forces start), then `->throw(new RuntimeException('post-suspend-throw'))` to push the exception back into the Fiber (the bridge's inner `try` block at lines 506–510 catches it from the generator side and forwards to `$fiber->throw($e)`).
   - The `$fiber->throw($e)` rethrows out of the suspended Fiber → propagates up through the generator → the generator's `finally` (lines 513–515) runs → `setFiberMode(false)` is called.
   - Assert that after the unwound throw, `$scopeContext->isFiberMode() === false`.
   - Production target: `Scope.php` lines 501–515 — the bridge generator's `finally`.

3. `testFiberModeIsResetWhenBridgeGeneratorGarbageCollectedWhileSuspended(): void`
   - **Depends on P6's explicit reset in `Scope::destroy()` / `Scope::cancel()`.** Without P6, the generator's `finally` runs at GC time but reads through `$scopeContext` *after* `destroy()` already touched state, and the test would race / leak.
   - Build a handler that suspends with a never-resolving promise.
   - Invoke the closure to obtain the bridge generator. Force `->current()` to start it.
   - Drop the only strong reference to the generator (`unset($generator)`) and trigger collection: `\gc_collect_cycles()`.
   - Assert `$scopeContext->isFiberMode() === false`.
   - Note: this exercises PHP's GC-runs-Generator-finally semantics. PHP runs the `finally` block of a suspended Generator when the Generator is collected (verified: this is documented behavior, see <https://wiki.php.net/rfc/generators> §"Cleanup at GC" — write the assertion explicitly without relying on the wider PHP semantics being intuitive).
   - Production target: `Scope.php` lines 513–515 — the `finally` runs on GC too.

4. `testFiberModeStartsFalseAndIsSetTrueOnlyInsideFiberBody(): void`
   - Build a handler that asserts (inside its own body) `$scopeContext->isFiberMode() === true` at the call site, and records a side-channel flag.
   - Construct the closure; invoke it.
   - The handler runs synchronously and returns. Bridge sees `$fiber->isTerminated() === true` and takes the early-return branch at lines 495–498, calling `setFiberMode(false)` first.
   - After the call, assert: (a) the side-channel flag is `true` (the handler observed Fiber mode), and (b) `$scopeContext->isFiberMode() === false`.
   - Production target: lines 482–484 (set to true) + 495–498 (reset on terminal Fiber).

**Bonus assertion (H5 "bonus" from the prompt):** This file's case (3) plus a small additional sub-case `testFiberModeFlippedExternallyIsResetByScopeDestroy(): void` covers the bonus path:
- Flip `$scopeContext->setFiberMode(true)` via the public setter (no Fiber involved).
- Build a minimal `Scope` instance, inject `$scopeContext` via `(new \ReflectionProperty(Scope::class, 'scopeContext'))->setValue($scope, $scopeContext)`.
- Call `$scope->destroy()`.
- Assert `$scopeContext->isFiberMode() === false`.
- This **only passes after P6**, because P6 adds the explicit reset before `$this->scopeContext?->destroy()`. On master the destroy unsets references but doesn't reset the flag — and once `parent::destroy()` runs the inner state is gone, so the test must capture the flag *before* `destroy()` would have wiped the object. **Implementation detail:** capture the `ScopeContext` reference into a local `$captured = $scopeContext` before calling `destroy()`. After `destroy()`, `$captured->isFiberMode()` is what we assert against; the reference itself stays alive thanks to the local, even if `Scope` unsets its internal copy.

---

### 2. `tests/Unit/Internal/Workflow/Process/ScopeNextErrorHandlingTestCase.php`

**Namespace:** `Temporal\Tests\Unit\Internal\Workflow\Process`
**Extends:** `PHPUnit\Framework\TestCase`
**Attributes:** `#[CoversClass(\Temporal\Internal\Workflow\Process\Scope::class)]`, `#[UsesClass(\Temporal\Internal\Workflow\Process\DeferredGenerator::class)]`

**Purpose:** C8 — assert that when the coroutine's `getReturn()` raises an exception, the scope's deferred rejects with that exception (P6 behavior) rather than silently resolving to `null` (master behavior).

**Why this is a unit test:** `Scope::next()` is `protected`, but reachable via reflection on a real `Scope` instance whose `$coroutine` field has been set to a hand-built `CoroutineInterface` stub. The bug surface is the catch block at lines 417–425 — purely a control-flow choice over a single try/catch. No worker, no RR, no Temporal server.

**Fixtures (private helpers):**

- `makeScopeWithCoroutine(CoroutineInterface $coroutine): Scope` — builds a `Scope` via `newInstanceWithoutConstructor()`, then uses reflection to set:
  - `$scope->services = $stubServiceContainer` (only `services->loop->once()` is touched on the deferred-resolve path; mock the loop to a noop `LoopInterface` stub).
  - `$scope->deferred = new \React\Promise\Deferred()`.
  - `$scope->context = $stubWorkflowContext` (only `getStackTrace()` and `resolveConditions()` are called on a few branches — both noop on the stub).
  - `$scope->scopeContext = $stubScopeContext` (the scope context only feeds `makeCurrent()` → `Workflow::setCurrentContext()`; safe to use a real `ScopeContext` built via `newInstanceWithoutConstructor()`).
  - `$scope->onCancel = []`, `$scope->onClose = []`.
  - `$scope->coroutine = $coroutine`.

**Tests:**

1. `testNextRejectsScopeWhenCoroutineGetReturnThrows(): void`
   - Build a `CoroutineInterface` stub via `$this->createMock(CoroutineInterface::class)` with:
     - `isRunning()` → `false`
     - `getReturn()` → throws `RuntimeException::class` with message `'getReturn-explosion'`.
   - Wire the scope and call `(new \ReflectionMethod(Scope::class, 'next'))->invoke($scope)`.
   - Hook a `then(null, $onRejected)` on `$scope->promise()` before calling `next()`. Capture the rejection in a closure.
   - Assert the captured `\Throwable` is a `RuntimeException` and its message is `'getReturn-explosion'`.
   - On master this assertion fails — `onResult(null)` is called, the deferred resolves to `null`, no rejection is fired. On P6 this assertion passes.
   - Production target: `Scope.php` lines 417–425. P6 changes `catch (\Throwable) { $this->onResult(null); return; }` to `catch (\Throwable $e) { $this->onException($e); return; }`.

2. `testNextResolvesNormallyWhenCoroutineGetReturnSucceeds(): void` (anti-regression — ensures P6 doesn't break the happy path)
   - Build a coroutine stub: `isRunning()` → `false`; `getReturn()` → returns the array `['ok', 1, 2]`.
   - Invoke `next()`.
   - Hook a `then($onFulfilled, null)` on `$scope->promise()` before the call.
   - Assert the captured value equals `['ok', 1, 2]`.
   - Anchors the regression — confirms the catch-block tightening doesn't accidentally narrow the success branch.

**Note on `defer()`:** `onException` schedules nothing — it rejects synchronously. `onResult` calls `$this->deferred->resolve(...)` which also fires `then` callbacks synchronously (React's `Deferred`). So neither test needs to advance any loop tick. The `$this->services->loop->once(...)` call inside `defer()` is reached only via `nextPromise()` — out of scope for these tests.

---

### 3. `tests/Unit/Internal/Workflow/ScopeContextCloneFiberModeTestCase.php`

**Namespace:** `Temporal\Tests\Unit\Internal\Workflow`
**Extends:** `PHPUnit\Framework\TestCase`
**Attributes:** `#[CoversClass(\Temporal\Internal\Workflow\ScopeContext::class)]`

**Purpose:** C7 — verify the query executor closure shape forces `fiberMode = false` on the cloned context.

**Why this is a unit test:** the closure under test lives inside `Process::__construct` (`Process.php:55–71`). It's reachable in two ways: (a) instantiate a `Process` and trip the query executor by calling the dispatcher, or (b) replicate the closure body verbatim in the test, asserting the *shape* matches P5's contract. Option (b) is brittle (couples tests to production line layout); option (a) requires building a full `Process` which pulls in too much. **Chosen approach: pure object-level**, as the prompt requires.

**Direct object-level mirror:** the failure mode P5 fixes is "`$context = $this->scopeContext->withInput(...)` followed by `setReadonly(true)` does **not** reset `fiberMode` — without P5 the clone inherits `true`". The unit test verifies the `withInput`-then-reset *combination* yields `false`. P5's production change is one explicit `$context->setFiberMode(false)` line. The unit test:

1. Builds a `ScopeContext` via `newInstanceWithoutConstructor()` (we only need the `fiberMode` slot + `withInput()`).
2. Calls `$ctx->setFiberMode(true)`. Asserts `isFiberMode() === true`.
3. Clones via `$clone = $ctx->withInput(new Input($info, EncodedValues::empty(), $header))` — to satisfy `withInput`'s signature without booting a full workflow input we need either a real `Input` or a stub. `Input` is a plain DTO (`Temporal\Internal\Workflow\Input`) with three nullable-ish fields — instantiate it with stubs from `createStub(WorkflowInfo::class)`, `EncodedValues::empty()`, and `createStub(HeaderInterface::class)`.
4. Asserts `$clone->isFiberMode() === true` — this is the **before-P5 behaviour**, documented as the bug.
5. Calls `$clone->setReadonly(true);` (mirrors line 61 of `Process.php`).
6. Calls `$clone->setFiberMode(false);` (mirrors P5's new line).
7. Asserts `$clone->isFiberMode() === false`.
8. Asserts `$ctx->isFiberMode() === true` — confirms the explicit reset on the clone did not leak back to the parent.

**Tests:**

1. `testWithInputCopiesFiberModeByValue(): void` — steps 1–4 above (just the regression-baseline half).
2. `testExplicitFiberModeResetOnCloneDoesNotAffectParent(): void` — steps 1–8 above (the full P5-compliant flow).
3. `testFiberModeDefaultIsFalseForFreshContext(): void` — instantiate a fresh `ScopeContext` and assert `isFiberMode() === false` before any setter call. Anchors the default.

Production target (verified after P5): `Process.php` line 62 inserts `$context->setFiberMode(false);`. The unit test does not load `Process.php`; it verifies the **mechanism** P5 added is correct. A complementary acceptance test (a fiber-mode workflow with a query that touches `Fibers\Workflow::getInfo()`) lives in P5's own plan as out-of-scope-for-tests; we intentionally don't duplicate it here, the unit-level mirror is sufficient.

---

### 4. `tests/Acceptance/Extra/Workflow/Fibers/DeepCallStackFiberTest.php`

**Namespace:** `Temporal\Tests\Acceptance\Extra\Workflow\Fibers\DeepCallStackFiber`
**Extends:** `Temporal\Tests\Acceptance\App\TestCase`
**Workflow registration name:** `'Extra_Workflow_Fibers_DeepCallStack'`
**Activity registration prefix:** `'Extra_Workflow_Fibers_DeepCallStack.'`

**Purpose:** H10 — stress the Fiber lifecycle across a 3+-deep call stack with `try/finally` blocks at each level, with suspend → throw → catch → suspend → complete.

**Workflow flow:**

```
handle()
  try { levelA(); } finally { record('handle-finally'); }

levelA()
  try {
    levelB();
  } finally { record('A-finally'); }

levelB()
  try {
    $first = Workflow::executeActivity('echo', 'first');      // SUSPEND 1
    levelC($first);
  } catch (\RuntimeException $e) {
    record('B-caught: ' . $e->getMessage());
    $second = Workflow::executeActivity('echo', 'second');    // SUSPEND 2 after catch
    record('B-after-second: ' . $second);
  } finally { record('B-finally'); }

levelC(string $previous)
  try {
    Workflow::executeActivity('boom', $previous);              // SUSPEND that throws
  } finally { record('C-finally'); }
```

The activity `boom` always throws `ApplicationFailure::nonRetryable("intentional-boom", "TestError")`. The `levelC` `try/finally` runs its `finally` before the exception escapes; `levelB` catches and re-suspends on a second activity; `levelA`'s `finally` runs after the second suspend completes; `handle`'s `finally` runs last.

**Assertions:**

The workflow returns an array of `$record` entries (workflow-local `private array $log` mutated via a `record(string)` helper). Test asserts the log equals:

```php
[
    'C-finally',
    'B-caught: intentional-boom',
    'B-after-second: second',
    'B-finally',
    'A-finally',
    'handle-finally',
]
```

This ordering verifies:
- Each `finally` ran (including the deepest one, `C-finally`, which P6/M9 don't currently guarantee on hard destroy but *do* guarantee on caught-exception unwind — this test exercises the caught path).
- The second suspension (`executeActivity('echo', 'second')`) successfully re-entered the Fiber after the first re-throw, proving the bridge `$fiber->throw` → `$fiber->resume` cycle works mid-stack.
- The Fiber returned normally after stack unwind (workflow completes successfully with a non-null result).

**Activity:**

```php
#[\Temporal\Activity\ActivityInterface(prefix: 'Extra_Workflow_Fibers_DeepCallStack.')]
class TestActivity
{
    #[\Temporal\Activity\ActivityMethod(name: 'echo')]
    public function echo(string $value): string { return $value; }

    #[\Temporal\Activity\ActivityMethod(name: 'boom')]
    public function boom(string $previous): never
    {
        throw \Temporal\Exception\Failure\ApplicationFailure::nonRetryable(
            'intentional-boom',
            'TestError',
        );
    }
}
```

**Tests:**

1. `testDeepCallStackUnwindRunsAllFinalliesAndReSuspends(): void` — the main assertion above.
2. `testWorkflowCompletesSuccessfullyAfterCaughtThrow(): void` — asserts `$stub->getResult()` returns a non-null payload (the recorded log array) and does **not** throw `WorkflowFailedException`, demonstrating the throw was caught inside the workflow.

**Pre-P6 expectation:** the C8 bare-catch bug would cause `Scope::next()` to silently `onResult(null)` when the activity-failure exception bubbles in certain rare paths. With current master + the Fiber bridge, the exception path goes through `handleError()` not the bare catch, so this test does **not** explicitly verify C8 — that's `FiberThrowAfterSuspendTest`'s job. This test verifies the **lifecycle** (multiple `finally`s in the correct order, suspend-resume-throw-catch-suspend-resume sequence).

---

### 5. `tests/Acceptance/Extra/Workflow/Fibers/FiberDestroyDuringSuspendTest.php`

**Namespace:** `Temporal\Tests\Acceptance\Extra\Workflow\Fibers\FiberDestroyDuringSuspend`
**Extends:** `Temporal\Tests\Acceptance\App\TestCase`
**Workflow registration name:** `'Extra_Workflow_Fibers_FiberDestroyDuringSuspend'`

**Purpose:** C6 regression — verify that destroying a scope mid-await leaves no `fiberMode=true` state hanging on the surviving context.

**Why this is an acceptance test (not pure unit):** C6's failure mode is reachable only through the real Scope lifecycle (memory flush / hard destroy). The unit-level "destroy with reflection" case is already covered by `ScopeFiberModeLifecycleTestCase::testFiberModeFlippedExternallyIsResetByScopeDestroy()`. The acceptance test demonstrates that under realistic eviction conditions the regression doesn't reappear via some other path.

**Workflow flow:**

```php
#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: 'Extra_Workflow_Fibers_FiberDestroyDuringSuspend')]
    public function handle(): array
    {
        Workflow::await(fn(): bool => $this->exit);
        return ['completed' => true];
    }

    #[SignalMethod]
    public function exit(): void { $this->exit = true; }

    #[QueryMethod]
    public function probe(): string { return 'alive'; }
}
```

**Test method:** `testDestroyWhileSuspendedLeavesContextClean(): void`

1. Start the workflow via `#[Stub('Extra_Workflow_Fibers_FiberDestroyDuringSuspend')]`. The Fiber suspends inside `Workflow::await`.
2. Send a `probe()` query before sending the `exit` signal. This is the canary: on pre-P5/P6 code, if the cloned query context inherits `fiberMode=true` *and* the query handler internally touches a path that would `\Fiber::suspend()`, the worker fatals. `probe()` only calls `Workflow::getInfo()`-style sync calls, so this asserts the **default** query path is clean.
3. Send `exit()` signal and read the result.
4. Assert `$result === ['completed' => true]`.
5. Assert `$queryResult === 'alive'`.

**Note on test ownership:** this test does not literally call `Scope::destroy()` from the test body — RR-driven hard destroy is not exposable from the client side. The test instead asserts the *symptom* C6 would produce (query handler crashes with `FiberError` because the cloned context inherits `fiberMode=true`). After P5 and P6 it passes trivially because both paths are explicitly safe; on pre-fix code the test still passes today (because the bridge's `finally` happens to run between Fiber executions, per C7's "incidentally correct" framing), so this test is best understood as a **stability anchor** rather than a discriminating regression. The discriminating regression is the unit test in file (1).

Document this clearly in the test docblock (one line, per CLAUDE.md "Comments" rules) so future readers know not to delete it.

---

### 6. `tests/Acceptance/Extra/Workflow/Fibers/FiberThrowAfterSuspendTest.php`

**Namespace:** `Temporal\Tests\Acceptance\Extra\Workflow\Fibers\FiberThrowAfterSuspend`
**Extends:** `Temporal\Tests\Acceptance\App\TestCase`
**Workflow registration name:** `'Extra_Workflow_Fibers_FiberThrowAfterSuspend'`
**Activity prefix:** `'Extra_Workflow_Fibers_FiberThrowAfterSuspend.'`

**Purpose:** C8 regression — workflow that throws `RuntimeException` synchronously inside an activity callback chain after first suspension. Assert workflow stub fails with the expected exception, not resolves to null.

**Workflow flow:**

```php
#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_Fibers_FiberThrowAfterSuspend')]
    public function handle(): array
    {
        $first = Workflow::executeActivity('warmup', 'init');   // SUSPEND 1
        throw new \RuntimeException('post-suspend-runtime: ' . $first);
    }
}

#[ActivityInterface(prefix: 'Extra_Workflow_Fibers_FiberThrowAfterSuspend.')]
class TestActivity
{
    #[ActivityMethod(name: 'warmup')]
    public function warmup(string $seed): string { return strrev($seed); }
}
```

**Tests:**

1. `testWorkflowFailsWithExpectedExceptionAfterFirstSuspend(): void`
   - `$this->expectException(\Temporal\Exception\Client\WorkflowFailedException::class);`
   - Trigger `$stub->getResult('array')`.
   - In a catch block (after `expectException` is consumed by PHPUnit's harness, capture the chained cause from a separate test or use `$this->expectExceptionMessage` against the canonical wrapped string). Cleanest pattern: don't use `expectException` here — call `$stub->getResult()` inside `try { } catch (WorkflowFailedException $e) { ... }`, then assert:
     - `$e->getPrevious()` is `\Temporal\Exception\Failure\ApplicationFailure` (or whatever wrapping the SDK produces for a raw `\RuntimeException` from inside a workflow — verified by inspecting an existing test that throws a `\RuntimeException` from a workflow).
     - The cause carries the message `'post-suspend-runtime: tini'` (`strrev('init')` → `'tini'`).
   - On pre-P6 master, the C8 bare catch would convert the throw into `onResult(null)`, making `$stub->getResult()` succeed with `null` rather than fail. On P6, the throw surfaces correctly.

**Verification helper recipe (to be implemented):**

Verify by direct inspection of `Scope.php:417–425` after P6 lands that the catch block writes `$this->onException($e)` rather than `$this->onResult(null)`. The acceptance test then exercises the path that exercises the catch (the Fiber `getReturn()` raising `FiberError` from the post-suspend-throw state).

---

## Tasks

The six files are independent. Tasks #1–#6 can be dispatched in parallel by the implement coordinator.

- [ ] **#1** Create `tests/Unit/Internal/Workflow/Process/ScopeFiberModeLifecycleTestCase.php` per design (1) above. The new subdirectory `tests/Unit/Internal/Workflow/Process/` must be created (it currently does not exist — verified via `ls`).

- [ ] **#2** Create `tests/Unit/Internal/Workflow/Process/ScopeNextErrorHandlingTestCase.php` per design (2).

- [ ] **#3** Create `tests/Unit/Internal/Workflow/ScopeContextCloneFiberModeTestCase.php` per design (3). Directory already exists (`LoggerTestCase.php` lives there).

- [ ] **#4** Create `tests/Acceptance/Extra/Workflow/Fibers/DeepCallStackFiberTest.php` per design (4), including the workflow and activity fixtures inline in the same file (matching the convention in `ActivityMethodTest.php`, `MutexRunLockedTest.php`, etc.).

- [ ] **#5** Create `tests/Acceptance/Extra/Workflow/Fibers/FiberDestroyDuringSuspendTest.php` per design (5).

- [ ] **#6** Create `tests/Acceptance/Extra/Workflow/Fibers/FiberThrowAfterSuspendTest.php` per design (6), including the activity fixture inline.

- [ ] **#7** Syntax check all six files: `php -l <path>` for each. All must report `No syntax errors detected`.

- [ ] **#8** Style and static analysis on the new files:
  - `composer cs:diff` — must show zero issues against the new files. Run `composer cs:fix` if needed and recheck.
  - `composer psalm` — must not introduce new errors against the new files. Reflection-based test files may need narrow `@psalm-suppress` annotations; if so, add per-line suppressions with the canonical Psalm issue name (e.g. `@psalm-suppress PossiblyNullReference`) — never blanket-suppress.

- [ ] **#9** Run the unit suite alone first:
  - `composer test:unit` — all three new unit test classes must execute. If P5+P6 have already landed in the workspace, all assertions pass. If they haven't, the tests **must fail** with specific assertion-failure messages (not fatal/parse errors) — confirm the red-baseline shape.

- [ ] **#10** Run the acceptance-fast suite:
  - `composer test:accept-fast` — the three new acceptance tests must execute. Same red/green dichotomy as #9.

- [ ] **#11** Final verification — once **P5 and P6 are both implemented** in the workspace, re-run `composer test:unit && composer test:accept-fast` and confirm 100% green on the six new test classes. Capture the result in the PR description.

## Out of scope (explicitly)

- **Modifying production code** — every fix lives in P5 or P6. This plan only adds tests.
- **Other H/M/L findings** — H1–H4, H6–H11 (except H10), and all M/L items have their own plans or are deferred.
- **Update validator path coverage** — the C7 fix in P5 explicitly scopes to the query executor. The update validator's analogous `withInput` clone is acknowledged but not under regression test in this plan.
- **`runLocked` BaseMutex branch coverage** (M13) — separate plan.
- **`src/Experiments/Fibers/*` isolated unit coverage** (M14) — separate plan.
- **Comment hygiene / TODO cleanup** (L2) — separate plan.
- **`Scope::cancel`-on-detached-scope coverage** (L1, partial overlap with C6) — the unit test in file (1) case (3) and the bonus case both target `destroy()`; the cancel path is a follow-up if the M9 plan (Fiber `finally` on hard destroy) lands.

## Parallelization

- **Internal:** all six file creations are independent. Tasks #1–#6 can be dispatched in parallel.
- **External:** This plan must run **after** P5 and P6 are implemented. It is otherwise independent of every other Fiber plan (test-hygiene cleanup, yield removal, query-handler fix, scope-runtime fix, Logger rename, DeferredFiber cleanup). It only touches new files under `tests/`.
- **Conflict surface:** zero — no existing file is modified.

## Why full mode (not fast)

Six new files across two test layers, three distinct production findings to trace through, and a hard cross-plan dependency on P5+P6. Each file has subtle correctness requirements (which `finally` runs when in PHP's Fiber/Generator interaction; how to instantiate `Scope`/`ScopeContext` without going through their constructors; how the bare-catch failure surfaces through `Scope::next`'s error path). The implementer needs the design-per-file detail above to write correct code first try, especially the Reflection-based seam for `Scope::createFiberHandler` — fast mode would force the implementer to re-derive these decisions and likely produce flaky or over-broad tests.
