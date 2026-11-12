# Fiber-mode workflow runtime

How `\Temporal\Experiments\Fibers\Workflow` lets you write workflow bodies without `yield` / `\Generator`, while the underlying `Scope::next()` event loop keeps driving the same protocol.

> **Status:** `@experimental`. Public API stability not promised. Generator mode ([workflow-coroutines.md](workflow-coroutines.md)) remains the primary path.

## TL;DR

Generator-mode workflow body:

```php
#[WorkflowMethod]
public function run(): \Generator
{
    yield Workflow::timer(5);
    $result = yield Workflow::executeActivity('echo', [$input]);
    return $result;
}
```

Same workflow in Fiber mode:

```php
use Temporal\Experiments\Fibers\Workflow;

#[WorkflowMethod]
public function run(): mixed
{
    Workflow::timer(5);
    return Workflow::executeActivity('echo', [$input]);
}
```

**Three-step migration:**
1. `use Temporal\Workflow;` → `use Temporal\Experiments\Fibers\Workflow;`
2. Delete every `yield` before a `Workflow::*` call.
3. Drop `: \Generator` from workflow / signal / update / handler return types.

Workflow attributes (`#[WorkflowInterface]`, `#[WorkflowMethod]`, `#[SignalMethod]`, `#[QueryMethod]`, `#[UpdateMethod]`) stay in the standard `Temporal\Workflow\…` namespace — they are not duplicated.

## The bridge: every handler runs inside a Fiber

The headline trick is in [src/Internal/Workflow/Process/Scope.php:480](../../src/Internal/Workflow/Process/Scope.php:480):

```php
private function createFiberHandler(callable $handler, ScopeContext $scopeContext): \Closure
{
    return static function (ValuesInterface $values) use ($handler, $scopeContext): mixed {
        $fiber = new \Fiber(static function () use ($handler, $values, $scopeContext): mixed {
            $scopeContext->setFiberMode(true);
            Workflow::setCurrentContext($scopeContext);
            return $handler($values);
        });

        try {
            $suspendedValue = $fiber->start();
        } catch (\Throwable $e) {
            $scopeContext->setFiberMode(false);
            throw $e;
        }

        if ($fiber->isTerminated()) {
            $scopeContext->setFiberMode(false);
            return $fiber->getReturn();
        }

        return (static function (\Fiber $fiber, mixed $suspendedValue, ScopeContext $scopeContext): \Generator {
            $value = $suspendedValue;
            try {
                while (!$fiber->isTerminated()) {
                    try {
                        $sent = yield $value;
                        $value = $fiber->resume($sent);
                    } catch (\Throwable $e) {
                        $value = $fiber->throw($e);
                        if ($fiber->isTerminated()) {
                            break;
                        }
                    }
                }
                return $fiber->getReturn();
            } finally {
                $scopeContext->setFiberMode(false);
            }
        })($fiber, $suspendedValue, $scopeContext);
    };
}
```

**Two branches matter.** The user-handler return type decides which path runs:

| User wrote | `$handler($values)` returns | Bridge does |
|---|---|---|
| Fiber body (no `yield`, no `\Generator`) | the return value | Fiber suspends from inside `FiberHelper::await()`. Bridge wraps `$fiber` in a forwarding Generator and returns it. `Scope::next()` sees a Generator, treats it like every other coroutine. |
| Generator body (has `yield`) | the user's `\Generator` object | Fiber terminates immediately; bridge returns `$fiber->getReturn()` (the Generator object). `Scope::next()` sees a Generator and runs it natively — Fiber overhead is one start/return, then untouched. |

Every workflow handler, signal handler, update handler, query handler, and `Workflow::async()` task goes through `createFiberHandler` — Generator mode is just the Fiber bridge with one degenerate iteration.

### The forwarding Generator

When the Fiber suspends, the bridge wraps it in an inner Generator (the `(static function ...)` returning `\Generator`). That bridge Generator:
- `yield`s whatever the Fiber suspended with (a `PromiseInterface` from `Workflow::timer(...)` etc.)
- when `Scope::next()` sends back the resolved value, calls `$fiber->resume($sent)` to wake the Fiber
- catches thrown values from `Scope::next()` and re-throws them inside the Fiber via `$fiber->throw($e)`

To the rest of the runtime, the Fiber body is **indistinguishable** from a Generator body. No new dispatch loop, no new wire protocol, no separate event layer.

## `ScopeContext::$fiberMode` — the state flag

A boolean on `ScopeContext` that gates `FiberHelper::await()` behavior. Set sites (the **only** legitimate ones):

| Site | Action | Why |
|---|---|---|
| `Scope::createFiberHandler` inner closure | `setFiberMode(true)` right before `$handler($values)` | Mark this Fiber as suspend-capable. |
| `Scope::createFiberHandler` `try/catch` on `$fiber->start()` | `setFiberMode(false)` on throw | Restore flag if the body crashed before the bridge took over. |
| `Scope::createFiberHandler` after `$fiber->isTerminated()` (sync completion) | `setFiberMode(false)` | The Fiber returned without suspending — Generator path effectively. |
| Bridge Generator `try/finally` | `setFiberMode(false)` | Final reset when the Fiber finishes (normally or via exception). |
| `Scope::destroy()` | `?->setFiberMode(false)` | Last-resort cleanup at scope teardown. |
| `Process::setQueryExecutor` query closure | `setFiberMode(false)` (right after `setReadonly(true)`) | Queries are synchronous — they MUST NOT suspend even if the parent Fiber is in flight. |

**Never** flip `fiberMode` in `Scope::cancel()`, `Scope::next()`, `Scope::nextPromise()`, `Scope::handleError()`, or any other intermediate site. A reset there leaks into a suspended child Fiber, and the next `FiberHelper::await` throws `FiberError: Cannot suspend outside of a fiber`. The bridge's `try/finally` is the contract; everything else must respect it.

## `FiberHelper::await` — the suspension primitive

[src/Experiments/Fibers/FiberHelper.php](../../src/Experiments/Fibers/FiberHelper.php):

```php
public static function await(PromiseInterface $promise): mixed
{
    if (!self::isInFiberMode()) {
        throw new OutOfContextException(
            'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
        );
    }

    return \Fiber::suspend($promise);
}

public static function isInFiberMode(): bool
{
    $context = Facade::getCurrentContext();
    return $context instanceof ScopeContext && $context->isFiberMode();
}
```

Every Fiber-mode `Workflow::*` method that maps to a promise call (`timer`, `executeActivity`, `executeChildWorkflow`, `getVersion`, `sideEffect`, `await`, `awaitWithTimeout`, `uuid*`, `continueAsNew`) delegates to the underlying `\Temporal\Workflow::*` and wraps the resulting promise in `FiberHelper::await(...)`. The Fiber suspends with the promise as the suspension value; the bridge Generator yields that promise to `Scope::next()`; the existing event loop resolves it; the resolved value comes back through `$fiber->resume($sent)` and `\Fiber::suspend()` returns it.

For a workflow author, `Workflow::executeActivity(...)` simply "returns the activity result" — exactly like calling a normal function. There is no syntactic marker that suspension happened.

## Public API surface — `\Temporal\Experiments\Fibers\Workflow`

`src/Experiments/Fibers/Workflow.php` is a thin facade that mirrors `\Temporal\Workflow`. Three categories:

**1. Pure delegators (synchronous accessors that don't suspend):**
`getCurrentContext`, `now`, `isReplaying`, `getInfo`, `getUpdateContext`, `getInput`, `getStackTrace`, `allHandlersFinished`, `getLogger`, `getInstance`, `getCurrentDetails`, `setCurrentDetails`, `getLastCompletionResult`, `upsertMemo`, `upsertSearchAttributes`, `upsertTypedSearchAttributes`, `registerQuery`, `registerSignal`, `registerUpdate`, `registerDynamicSignal`, `registerDynamicQuery`, `registerDynamicUpdate`.

**2. Fiber-suspending wrappers (delegate to `\Temporal\Workflow` then `FiberHelper::await`):**
`await`, `awaitWithTimeout`, `getVersion`, `sideEffect`, `timer`, `continueAsNew`, `executeChildWorkflow`, `executeActivity`, `uuid`, `uuid4`, `uuid7`.

Return-type tightening vs the base:
- `timer(): void` (no `PromiseInterface<null>`)
- `awaitWithTimeout(): bool` (no `PromiseInterface<bool>`)
- `getVersion(): int` (no `PromiseInterface<int>`)

**3. Scope helpers and stubs:**
- `async`, `asyncDetached` — same `CancellationScopeInterface` contract as base.
- `newActivityStub`, `newUntypedActivityStub`, `newChildWorkflowStub`, `newUntypedChildWorkflowStub`, `newContinueAsNewStub`, `newExternalWorkflowStub`, `newUntypedExternalWorkflowStub` — wrap base stubs in `FiberProxy` / `Fiber*Stub` so call results are auto-awaited.
- `runLocked(Mutex|BaseMutex $mutex, callable)` — Fiber-mode mutex acquisition; uses `getInner()` for `Fibers\Mutex` to delegate to base mutex semantics.
- `gather(callable ...$tasks)` — parallel fan-out via `async()` + `Promise::all`, returns a list of results synchronously to the Fiber.

**4. Escape hatches:**
- `timerPromise($interval)` — returns the raw `PromiseInterface<null>` (the only `*Promise()` variant); needed when feeding a timer into `Promise::race` or as the deadline for `awaitWithTimeout`. For any other "I want the raw promise" case, drop down to `\Temporal\Workflow::xxx(...)` directly.

### Helper types

| Class | Role |
|---|---|
| `Temporal\Experiments\Fibers\Mutex` | Fiber-flavored `Workflow\Mutex`. Wraps a base `Workflow\Mutex`; `getInner()` returns it for boundary unwrap. |
| `Temporal\Experiments\Fibers\FiberProxy` | Magic proxy around a typed activity/child-workflow stub. Calls are forwarded to the base stub; promise return values get `FiberHelper::await()`-ed transparently. |
| `Temporal\Experiments\Fibers\FiberActivityStub` / `FiberChildWorkflowStub` / `FiberExternalWorkflowStub` | Untyped stub wrappers. Same auto-await pattern as `FiberProxy`. |
| `Temporal\Experiments\Fibers\Promise` | `all`, `any`, `race` helpers that auto-await and return raw values to the Fiber. |

### Public API stability invariant

`Temporal\Experiments\Fibers\*` symbols MUST NOT leak into stable public API surfaces (`src/Workflow/`, `src/Internal/Workflow/`, `src/Interceptor/`). The base `\Temporal\Workflow` interfaces accept only base types. The Fiber facade unwraps experimental types at its boundary:

```php
public static function await(callable|BaseMutex|Mutex|PromiseInterface ...$conditions): mixed
{
    return FiberHelper::await(\Temporal\Workflow::await(...self::unwrapConditions($conditions)));
}

private static function unwrapConditions(array $conditions): array
{
    $unwrapped = [];
    foreach ($conditions as $condition) {
        $unwrapped[] = $condition instanceof Mutex ? $condition->getInner() : $condition;
    }
    return $unwrapped;
}
```

This pattern is the canonical fix for every "Fiber type should be accepted but stable API can't reference it" case.

## How execution actually unfolds

End-to-end trace of `Workflow::executeActivity('echo', ['hi'])` from a Fiber workflow body:

```
Fiber-mode workflow body
  └─▶ Workflow::executeActivity('echo', ['hi'])              (Fibers facade)
        └─▶ \Temporal\Workflow::executeActivity('echo', ['hi'])
              └─▶ pushes ExecuteActivity request to outbound queue
              └─▶ returns PromiseInterface<mixed>
        └─▶ FiberHelper::await($promise)
              └─▶ checks isInFiberMode() → true
              └─▶ \Fiber::suspend($promise)
                    │
                    ▼
        bridge Generator: `yield $value` (where $value = $promise)
                    │
                    ▼
        Scope::next() — current() = PromiseInterface
              └─▶ Scope::nextPromise($promise)
                    └─▶ $promise->then($onFulfilled, $onRejected)
                    └─▶ next() returns; coroutine suspended
                    │
                    │ (batch flushes to RR, RR runs activity, RR returns result)
                    │
        Client::dispatch(ActivityResult) → Deferred->resolve($payloads)
              └─▶ $onFulfilled fires
                    └─▶ defer(fn) → loop->once(ON_TICK, fn)
                    │
                    ▼
        loop tick fires the deferred callback:
              coroutine->send($result)
                    └─▶ bridge Generator: $sent = $result; $fiber->resume($result)
                          └─▶ \Fiber::suspend() returns $result inside the user's body
                                └─▶ executeActivity() returns $result to the workflow body
              next() continues to look at the next yield
```

The Fiber-mode and Generator-mode paths converge at "bridge Generator yields a promise to `Scope::next()`". Everything below that boundary is identical — same wire protocol, same event loop, same replay mechanics.

## Replay: identical to Generator mode

`Fiber` carries no Temporal-specific state — it is just a PHP stack. At replay time, the worker restarts the workflow process from scratch, calls the user handler, which starts a new Fiber, which generates the same outbound commands in the same order (request IDs `9000+` via the static counter). RoadRunner resolves them from history immediately. For the Fiber, this just looks like "every promise resolves instantly" — same as Generator mode.

Same caveats apply:
- Don't use `if (Workflow::isReplaying())` to branch logic.
- Don't capture wall-clock time outside `Workflow::now()`.
- Don't read from external sources mid-Fiber.

The only difference: a Fiber's call stack is **visible** in PHP stack traces during replay (real `\Fiber` frames), where a Generator-mode body shows up as a regular function. This matters for `Workflow::getStackTrace()` and `enhancedStackTrace` query handlers — see [Gotchas](#gotchas).

## Public API map (Generator → Fiber)

| Generator | Fiber |
|---|---|
| `yield Workflow::timer(5)` | `Workflow::timer(5)` |
| `$r = yield Workflow::executeActivity(...)` | `$r = Workflow::executeActivity(...)` |
| `$r = yield Workflow::executeChildWorkflow(...)` | `$r = Workflow::executeChildWorkflow(...)` |
| `yield Workflow::await($cond)` | `Workflow::await($cond)` |
| `yield Workflow::awaitWithTimeout(5, $cond)` | `Workflow::awaitWithTimeout(5, $cond)` |
| `$v = yield Workflow::getVersion('a', 1, 2)` | `$v = Workflow::getVersion('a', 1, 2)` |
| `$id = yield Workflow::uuid4()` | `$id = Workflow::uuid4()` |
| `yield $activityStub->method()` | `$activityStub->method()` (auto-await via `FiberProxy`) |
| `yield Promise::all($promises)` | `Promise::all($scopes)` (via `Fibers\Promise`) |
| `yield Workflow::continueAsNew(...)` | `Workflow::continueAsNew(...)` |
| `Workflow::async(fn() => yield ...)` | `Workflow::async(fn() => ...)` |
| `Workflow::runLocked($mutex, fn() => yield ...)` | `Workflow::runLocked($mutex, fn() => ...)` |
| `#[WorkflowMethod] public function run(): \Generator` | `#[WorkflowMethod] public function run(): mixed` |
| `#[SignalMethod] public function on(...): \Generator` | `#[SignalMethod] public function on(...): void` |

## Gotchas

### 1. Fiber mode is opt-in **per file** — workflow type names must be unique

Both `\Temporal\Workflow` and `\Temporal\Experiments\Fibers\Workflow` workflows are registered on the same worker, on the same task queue, from the same `WorkerFactory`. The worker keeps a single `ArrayRepository` keyed by workflow type name (and activity prefix). Two workflows with the same `#[WorkflowMethod(name: "MyWorkflow")]` will boot-crash:

```
OutOfBoundsException: Entry with same identifier "MyWorkflow" already has been registered
```

When mirroring a Generator workflow into a Fiber sibling, rename:
- `#[WorkflowMethod(name: "X")]` → `#[WorkflowMethod(name: "X_Fibers")]` (or `_Fibers_X`, any consistent infix)
- `#[ActivityInterface(prefix: 'X.')]` → `#[ActivityInterface(prefix: 'X_Fibers.')]`
- Every `#[Stub(...)]` that references either.
- The argument to `replayFromJSON(...)` in any test that loads history fixtures.

### 2. Fiber-mode workflow body MUST NOT `yield`

If the body contains `yield` anywhere, the handler returns a `\Generator` object instead of a Fiber suspension. `createFiberHandler` calls `$handler($values)` inside the Fiber; the Fiber returns the Generator and terminates; bridge returns `$fiber->getReturn()` (the Generator); `Scope::next()` runs the Generator natively. The Fiber path **never engages** — `FiberHelper::isInFiberMode()` returns `false`, sentinel asserts pass on accident, no useful coverage.

Validation grep:

```
grep -nE 'yield|: \\Generator' tests/Acceptance/Extra/.../Fibers/*.php
```

Add a `isFiberMode` assertion to every Fiber acceptance test as a tripwire:

```php
public function run(): array
{
    return [
        'fiberMode' => Workflow::getCurrentContext() instanceof ScopeContext
            && Workflow::getCurrentContext()->isFiberMode(),
        // ...
    ];
}
```

### 3. Query handlers are synchronous — `fiberMode` MUST be `false` inside them

`Process::setQueryExecutor` clones the main scope's `ScopeContext` via `withInput(new Input(...))`. The `$fiberMode` boolean copies by value. If the main scope is mid-suspend in Fiber mode (true) when a query arrives, the query closure inherits `true`. Any `Fibers\Workflow::*` call inside the query handler then enters `FiberHelper::await()`, calls `\Fiber::suspend()` — but the query runs on the main PHP stack, not inside a Fiber. Result: `FiberError: Cannot suspend outside of a fiber`.

The reset is unconditional:

```php
$context = $this->scopeContext->withInput(new Input(...));
$context->setReadonly(true);
$context->setFiberMode(false);   // ← required
Workflow::setCurrentContext($context);
return $handler($input->arguments);
```

### 4. `Scope::startScope` must save/restore Facade context — but only in Fiber mode

`Scope::next()` calls `makeCurrent()` which sets `Facade::$ctx` to the current scope. When a parent Fiber calls `Workflow::async(...)` (which calls `startScope()`), the child scope's synchronous start switches `Facade::$ctx` to the child. After `startScope()` returns to the parent Fiber, the parent's `Facade::$ctx` is wrong — its next `Workflow::await(...)` registers on the child scope.

Fix:

```php
public function startScope(callable $handler, bool $detached, ?string $layer = null): CancellationScopeInterface
{
    $fiberMode = $this->scopeContext->isFiberMode();
    $savedContext = $fiberMode ? Facade::getCurrentContext() : null;
    $scope = $this->createScope($detached, $layer);
    $scope->start($handler(...), EncodedValues::empty(), false);
    if ($fiberMode) {
        Facade::setCurrentContext($savedContext);
    }
    return $scope;
}
```

**Why gated on `$fiberMode`:** Generator workflows tolerate the context leak — the leaked child context gets fixed by the next `makeCurrent()` in `Scope::next()` when the parent resumes via tick. Fiber workflows don't: the parent's body resumes immediately after `startScope` returns, before any tick happens. Unconditional restore breaks Generator workflows that rely on the leak.

### 5. `Scope::cancel()` MUST NOT reset `fiberMode`

A child scope's Fiber is suspended on a Mutex/Deferred. Cancellation propagates through the scope tree. If `cancel()` calls `setFiberMode(false)` on the shared `ScopeContext`, the next time the Fiber's bridge Generator resumes and the user body hits `FiberHelper::await`, `isInFiberMode()` returns `false` → `OutOfContextException` or `FiberError`. The only legitimate `fiberMode` writers are the bridge's `try/finally`, `destroy()`, and the query closure (see #3 above).

### 6. `StackRenderer::$userFrameSeen` — load-bearing, do not revert

`src/Internal/Support/StackRenderer.php` has a `$userFrameSeen` flag that gates `file`/`line` exposure to the **first** user frame only. Without it, `BuiltInPrefixedHandlersTest::enhancedStackTrace` fails because the rendered stack trace exposes implementation file paths instead of user code. This flag is the **single point of regression** for Fiber stack traces — Fiber frames are real PHP frames (not abstracted by `\Generator`), so the renderer sees them and must filter correctly. Auto-review agents have reverted this flag twice; never accept a diff that removes it.

### 7. `Fibers\Mutex` pre-Fiber `lock()` is a single-use no-op

`Fibers\Mutex::lock()` returns a no-op resolved promise when called before the workflow's Fiber starts (i.e., when `isInFiberMode()` is false — typically inside the workflow's `__construct`). This is **intentional** and safe for the first lock only — the constructor wants to pre-lock the mutex so the first `runLocked(...)` call from inside `run()` waits. A second pre-Fiber `lock()` would silently leak a Deferred. If the pattern appears more than once before the Fiber starts, the test is wrong.

### 8. `file_put_contents` / debug calls in `Process.php` corrupt RoadRunner framing

Any `file_put_contents`, `var_dump`, `print_r` left in `src/Internal/Workflow/Process/*` after debugging will corrupt RR's goridge STDOUT framing — the worker's response becomes unparseable and the workflow task times out at 60s. The `Arch` test catches `trap(...)` but not `file_put_contents`. Manual grep is the only defense:

```
grep -rn 'file_put_contents\|var_dump\|print_r(' src/Internal/Workflow/Process/
```

## See also

- [workflow-coroutines.md](workflow-coroutines.md) — the Generator-mode runtime that Fiber mode bridges to. **Read this first.**
- [worker-rr-protocol.md](worker-rr-protocol.md) — wire protocol between PHP and RoadRunner. Unchanged by Fiber mode.
- [architecture.md](architecture.md) — runtime architecture overview.
- `src/Experiments/Fibers/` — facade source.
- `src/Internal/Workflow/Process/Scope.php:480` — the bridge.
- `tests/Acceptance/Extra/.../Fibers/` — Fiber-mode acceptance tests, paired with their Generator-mode siblings.
