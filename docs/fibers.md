# Fiber-based Workflows (experimental)

The SDK can run workflow code in two interchangeable styles that coexist in the same
worker:

- **Generator mode** — the classic `use Temporal\Workflow;` API where every async call is
  `yield`ed. This is the stable, primary path and is unchanged.
- **Fiber mode** — `use Temporal\Experiments\Fibers\Workflow;`, where the same operations
  look like ordinary blocking calls and **no `yield` is used**. Experimental.

Both styles produce identical Temporal commands, so a worker can host generator and fiber
workflows side by side.

## Migrating a workflow to Fiber mode

1. Replace the facade import: `use Temporal\Workflow;` → `use Temporal\Experiments\Fibers\Workflow;`.
2. Delete every `yield` in front of a `Workflow::…` call.
3. Drop `\Generator` from workflow / signal / update method return types.

Attributes (`#[WorkflowInterface]`, `#[WorkflowMethod]`, `#[SignalMethod]`,
`#[QueryMethod]`, `#[UpdateMethod]`) stay in the standard `Temporal\Workflow\…` namespace.

```php
// Generator mode
public function run(): \Generator
{
    $value = yield Workflow::executeActivity('greet', ['world']);
    yield Workflow::timer(5);
    return $value;
}

// Fiber mode
public function run(): string
{
    $value = Workflow::executeActivity('greet', ['world']);
    Workflow::timer(5);
    return $value;
}
```

## Concurrency

`Workflow::async()` / `asyncDetached()` return a `FiberScope`. To run operations
concurrently, start them in `async()` closures (or via the `*Async()` / `*Promise()`
escape hatches) and await the combinator:

```php
use Temporal\Experiments\Fibers\FiberHelper;
use Temporal\Promise;

$a = Workflow::async(fn() => Workflow::executeActivity('a'));
$b = Workflow::async(fn() => Workflow::executeActivity('b'));
[$ra, $rb] = FiberHelper::await(Promise::all([$a, $b]));
```

Child-workflow start/signal/result ordering that relied on the unawaited-promise pattern
must use the **untyped** stub with explicit async calls (the typed proxy always
auto-awaits, which would deadlock a start-then-signal sequence):

```php
$child = Workflow::newUntypedChildWorkflowStub('Child', $options);
$child->start($arg);                 // awaits child start, not its result
$child->signal('unblock', [$data]);
return $child->getResult();          // awaits the result
```

## Rules and restrictions

- **Blocking APIs are only valid inside a workflow fiber.** `FiberHelper::await()` (and the
  facade methods that use it) throw `OutOfContextException` if called outside a running
  workflow fiber — e.g. from a constructor, a query handler, an `await` condition closure,
  or a promise callback. Those contexts must stay synchronous.
- **Queries and update validators are synchronous** and run with fiber mode disabled; they
  must never reach a suspension point.
- **Foreign suspends are rejected.** Suspending a workflow fiber with anything other than a
  workflow promise/request (for example by calling a non-workflow async library that uses
  `Fiber::suspend()` internally) raises `InvalidSuspendException` instead of corrupting the
  deterministic scheduler.
- **Teardown runs `finally`.** When a workflow is evicted, its suspended fiber is unwound
  so `finally` blocks execute, mirroring generator behavior.

## Notes

- Requires PHP ≥ 8.1 (`\Fiber`). No new dependency.
- Each workflow scope (main handler, every signal/update handler, every `async()` closure)
  runs in its own fiber. The default fiber stack is ~2 MB of virtual address space on
  64-bit builds (`fiber.stack_size`), committed only to the depth actually used; generator
  mode remains lighter, so tune `fiber.stack_size` if a worker caches many workflows.
