# Plan: Tighten Fiber stub decorator type system (FiberProxy generics, parallel interfaces, async escape hatches, void typing, dead-code purge)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** fix / refactor / type-system
**Source finding:** `.ai-factory/research/fibers-review.md` — H3 (lines 183–192), H4 (lines 194–203), M7 (lines 324–330), M8 (lines 332–335), L9 (lines 432–436)

## Settings

- Testing: yes — `composer psalm` must stay green at strict level 2, plus `composer test:accept-fast` to confirm runtime behaviour of the renamed `executeAsync` and `: void` returns. No new dedicated unit tests are required by this plan (the Fiber-wide unit-test gap is owned by M14 — see *Out of scope*).
- Docs: no — the `Experiments\Fibers\*` namespace is `@experimental` + `@internal`, so there is no public reference doc to update. **However**, if any in-tree sample workflow, README snippet, or doc fixture currently calls `FiberActivityStub::createExecution(...)`, that call site must be renamed to `executeAsync(...)` as part of Task B (grep `createExecution` across the repo and adjust any hits — implementation-side concern, but flagged here so the implementer doesn't miss it).
- Logging: minimal — the change is purely type-system / signature-shape; no new runtime branches that would benefit from log lines. The single new `\LogicException` thrown by `FiberProxy::__call` is its own diagnostic.
- Roadmap linkage: none — this is Phase 5 of the review's suggested action plan (static-analysis & typing).

## Scope statement

Touches `src/Experiments/Fibers/FiberProxy.php`, `src/Experiments/Fibers/FiberActivityStub.php`, `src/Experiments/Fibers/FiberChildWorkflowStub.php`, `src/Experiments/Fibers/FiberExternalWorkflowStub.php`, plus three new interface files (`FiberActivityStubInterface.php`, `FiberChildWorkflowStubInterface.php`, `FiberExternalWorkflowStubInterface.php`) under the same namespace. Safe to parallelize with other Fiber-fix plans EXCEPT plan P7 (facade API) which may also touch `Workflow.php` — but P7 doesn't touch decorator files.

Additionally, this plan **does** edit `src/Experiments/Fibers/Workflow.php` at lines 309–315 (`newUntypedActivityStub`), 329–336 (`newUntypedChildWorkflowStub`), and 358–363 (`newUntypedExternalWorkflowStub`) to widen the three factory return types from the concrete decorator classes (`FiberActivityStub`, `FiberChildWorkflowStub`, `FiberExternalWorkflowStub`) to the new interface types. **Coordinate with any concurrent edit of `Workflow.php`** (notably the H1/H2/H7/H8 plans which retouch other static methods in the same file): merge order matters but the hunks do not overlap, since those plans modify methods in the "Concurrency & locking" and "sideEffect / awaitWithTimeout" sections, not the "Proxy factories" block.

## Findings

### H3 — `FiberProxy` loses `T` through factory `@return T`

`final class FiberProxy` is non-generic and exposes only `__call(string, array): mixed`. The four `Workflow::new*Stub(class-string<T>): T` factories declare `@template T of object` + `@return T` but actually return `new FiberProxy($inner)`. Without `@template T of object` + `@mixin T` on `FiberProxy` itself, Psalm / IDEs cannot follow `T` through the wrapper: `$activity->myActivityMethod(...)` is rendered as an `__call` on an opaque `FiberProxy` instead of as a typed `T::myActivityMethod()` call.

**Fix:** declare `FiberProxy` as `@template T of object` + `@mixin T`, narrow the constructor param's PHPDoc to `T`, and keep the runtime signature unchanged. Note: the three **typed** decorators (`FiberActivityStub`, `FiberChildWorkflowStub`, `FiberExternalWorkflowStub`) intentionally do **not** need a template — their methods are explicit (not `__call`-routed) and their `$inner` is already statically typed to the canonical interface, so analyzers already see the correct shape there.

### H4 — Typed decorators cannot `implements` the canonical stub interfaces

`FiberActivityStub::execute(): mixed`, `FiberChildWorkflowStub::execute(): mixed`, `FiberExternalWorkflowStub::signal(): mixed`, etc. all diverge from their canonical interfaces (`ActivityStubInterface::execute(): PromiseInterface`, `ChildWorkflowStubInterface::execute(): PromiseInterface`, `ExternalWorkflowStubInterface::signal(): PromiseInterface`) — auto-await flattens the Promise into the resolved value, so the return type must be the unwrapped shape, which is incompatible with the canonical contract.

Consequence: a Fiber-mode workflow that wants to pass its activity stub to a helper typed `function f(ActivityStubInterface $s)` cannot do so. There is no parallel abstraction users can depend on.

**Fix:** introduce three parallel interfaces under `Temporal\Experiments\Fibers\`:

- `FiberActivityStubInterface` — mirrors `ActivityStubInterface` shape but with auto-awaited return types.
- `FiberChildWorkflowStubInterface` — mirrors `ChildWorkflowStubInterface` shape with auto-awaited returns.
- `FiberExternalWorkflowStubInterface` — mirrors `ExternalWorkflowStubInterface` shape with auto-awaited returns.

Each typed decorator `implements` its new interface. The three factory return types in `Workflow.php` widen from the concrete class to the new interface. The optional `inner()` escape hatch mentioned in the review (line 200) is **out of scope** for this plan — see *Out of scope*.

### M7 — `FiberActivityStub::createExecution()` is misnamed; raw-promise escape hatch is missing on sibling decorators

`createExecution()` (FiberActivityStub.php:41–48) calls `$this->inner->execute(...)` (NOT `createExecution()` — that method does not exist on `ActivityStubInterface`). Same body shape as `execute()`, only difference being that the outer decorator does **not** call `FiberHelper::await` — it hands back the raw `PromiseInterface` for callers that need to compose with `awaitWithTimeout(...)` or similar.

The name hides the intent and implies a non-existent inner method. Worse, `FiberChildWorkflowStub` and `FiberExternalWorkflowStub` have no equivalent escape hatch at all — composing their methods with `awaitWithTimeout` requires manual unwrapping that has to bypass the decorator entirely.

**Fix — naming convention:** `<methodName>Async(): PromiseInterface`. Specifically:

- Rename `FiberActivityStub::createExecution()` → `FiberActivityStub::executeAsync(): PromiseInterface` (declared on the new `FiberActivityStubInterface`).
- Add `FiberChildWorkflowStub::executeAsync()`, `startAsync()`, `getResultAsync()`, `signalAsync()` — each returning the raw `PromiseInterface` from the inner stub (declared on the new `FiberChildWorkflowStubInterface`).
- Add `FiberExternalWorkflowStub::signalAsync()`, `cancelAsync()` — same shape (declared on the new `FiberExternalWorkflowStubInterface`).

This is a **breaking source-level change** for any caller that has already adopted the experimental API and used `createExecution()`. Because the namespace is `@experimental` + `@internal`, callers were warned, but the rename should be called out clearly in the commit message. There is no deprecation alias — the experimental annotation is its own deprecation policy.

### M8 — `signal()` / `cancel()` return `mixed` for what is always a `PromiseInterface<void>`

Three offending method bodies:

- `FiberChildWorkflowStub::signal()` (lines 71–74) — declared `: mixed`, inner returns `PromiseInterface<void>`, `FiberHelper::await(...)` resolves to `null`, body returns it.
- `FiberExternalWorkflowStub::signal()` (lines 30–33) — same pattern.
- `FiberExternalWorkflowStub::cancel()` (lines 35–38) — same pattern.

**Fix:** change the declared return type to `: void` on each, and drop the implicit return — `FiberHelper::await(...)` already resolves to `null` but the call site doesn't need that value. Keep the canonical-interface mirror intact: the parallel `FiberChildWorkflowStubInterface::signal(): void`, `FiberExternalWorkflowStubInterface::signal(): void`, `::cancel(): void` declarations match the new implementation. The raw-promise sibling methods `signalAsync` / `cancelAsync` still return `PromiseInterface` (M7 — composability).

### L9 — `FiberProxy::__call` defensive fall-through is dead code today, leak vector tomorrow

`FiberProxy::__call` (lines 25–34) returns the inner result as-is when it is not a `PromiseInterface`. All four inner proxies (`ActivityProxy`, `ChildWorkflowProxy`, `ContinueAsNewProxy`, `ExternalWorkflowProxy`) currently always return `PromiseInterface` from `__call` — verified by reading each file in this plan's context-gathering step. So the fall-through is unreachable today.

But: a future internal refactor that returns a non-Promise from one of those `__call` methods (e.g. a synchronous getter accidentally promoted) would silently leak the raw value to user code through `FiberProxy`, bypassing the auto-await contract. That is the worst possible failure mode — a quiet semantic drift.

**Fix:** replace the fall-through with a hard `throw new \LogicException(...)`. The message must include the actual runtime type of the unexpected return (use `get_debug_type($result)`) so the failing inner proxy can be located fast.

## Target end state

### New file: `src/Experiments/Fibers/FiberActivityStubInterface.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;

interface FiberActivityStubInterface
{
    public function getOptions(): ActivityOptionsInterface;

    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): mixed;

    public function executeAsync(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface;
}
```

### New file: `src/Experiments/Fibers/FiberChildWorkflowStubInterface.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowExecution;

interface FiberChildWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    public function getChildWorkflowType(): string;

    public function getOptions(): ChildWorkflowOptions;

    public function start(mixed ...$args): WorkflowExecution;

    public function getResult(mixed $returnType = null): mixed;

    public function execute(array $args = [], mixed $returnType = null): mixed;

    public function signal(string $name, array $args = []): void;

    public function startAsync(mixed ...$args): PromiseInterface;

    public function getResultAsync(mixed $returnType = null): PromiseInterface;

    public function executeAsync(array $args = [], mixed $returnType = null): PromiseInterface;

    public function signalAsync(string $name, array $args = []): PromiseInterface;
}
```

### New file: `src/Experiments/Fibers/FiberExternalWorkflowStubInterface.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\WorkflowExecution;

interface FiberExternalWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    public function signal(string $name, array $args = []): void;

    public function cancel(): void;

    public function signalAsync(string $name, array $args = []): PromiseInterface;

    public function cancelAsync(): PromiseInterface;
}
```

### Modified: `src/Experiments/Fibers/FiberProxy.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * @template T of object
 * @mixin T
 *
 * @experimental
 * @internal
 */
final class FiberProxy
{
    /**
     * @param T $inner
     */
    public function __construct(
        private readonly object $inner,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        $result = $this->inner->__call($method, $args);

        if ($result instanceof PromiseInterface) {
            return FiberHelper::await($result);
        }

        throw new \LogicException(\sprintf(
            'FiberProxy expects the inner proxy to return a PromiseInterface; got %s.',
            \get_debug_type($result),
        ));
    }
}
```

(Class-level docblock retains the existing `@experimental` / `@internal` markers. The descriptive prose in the current docblock is removed per the project's "no comments" rule — `@template`, `@mixin`, `@experimental`, `@internal` are type / metadata annotations and stay.)

### Modified: `src/Experiments/Fibers/FiberActivityStub.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;
use Temporal\Workflow\ActivityStubInterface;

/**
 * @experimental
 * @internal
 */
final class FiberActivityStub implements FiberActivityStubInterface
{
    public function __construct(
        private readonly ActivityStubInterface $inner,
    ) {}

    public function getOptions(): ActivityOptionsInterface
    {
        return $this->inner->getOptions();
    }

    public function execute(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): mixed {
        return FiberHelper::await($this->inner->execute($name, $args, $returnType, $isLocalActivity));
    }

    public function executeAsync(
        string $name,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        bool $isLocalActivity = false,
    ): PromiseInterface {
        return $this->inner->execute($name, $args, $returnType, $isLocalActivity);
    }
}
```

### Modified: `src/Experiments/Fibers/FiberChildWorkflowStub.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 * @internal
 */
final class FiberChildWorkflowStub implements FiberChildWorkflowStubInterface
{
    public function __construct(
        private readonly ChildWorkflowStubInterface $inner,
    ) {}

    public function getExecution(): WorkflowExecution
    {
        return FiberHelper::await($this->inner->getExecution());
    }

    public function getChildWorkflowType(): string
    {
        return $this->inner->getChildWorkflowType();
    }

    public function getOptions(): ChildWorkflowOptions
    {
        return $this->inner->getOptions();
    }

    public function start(mixed ...$args): WorkflowExecution
    {
        return FiberHelper::await($this->inner->start(...$args));
    }

    public function getResult(mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->getResult($returnType));
    }

    public function execute(array $args = [], mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->execute($args, $returnType));
    }

    public function signal(string $name, array $args = []): void
    {
        FiberHelper::await($this->inner->signal($name, $args));
    }

    public function startAsync(mixed ...$args): PromiseInterface
    {
        return $this->inner->start(...$args);
    }

    public function getResultAsync(mixed $returnType = null): PromiseInterface
    {
        return $this->inner->getResult($returnType);
    }

    public function executeAsync(array $args = [], mixed $returnType = null): PromiseInterface
    {
        return $this->inner->execute($args, $returnType);
    }

    public function signalAsync(string $name, array $args = []): PromiseInterface
    {
        return $this->inner->signal($name, $args);
    }
}
```

Notes on the change set:
- `getExecution()` return is tightened from `: mixed` (with `@return WorkflowExecution` PHPDoc) to a declared `: WorkflowExecution` — the inner method's contract is `PromiseInterface<WorkflowExecution>` and the await always produces a `WorkflowExecution`, so the declared type can match.
- `start()` is tightened identically (declared `: WorkflowExecution` instead of `: mixed`) — the inner contract is `CompletableResultInterface<WorkflowExecution>`.
- All the per-method narrative docblocks ("Start the child workflow and return …", "Signal the child workflow.", etc.) are deleted per the project's "no comments" rule. The names are the documentation.

### Modified: `src/Experiments/Fibers/FiberExternalWorkflowStub.php`

```php
<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 * @internal
 */
final class FiberExternalWorkflowStub implements FiberExternalWorkflowStubInterface
{
    public function __construct(
        private readonly ExternalWorkflowStubInterface $inner,
    ) {}

    public function getExecution(): WorkflowExecution
    {
        return $this->inner->getExecution();
    }

    public function signal(string $name, array $args = []): void
    {
        FiberHelper::await($this->inner->signal($name, $args));
    }

    public function cancel(): void
    {
        FiberHelper::await($this->inner->cancel());
    }

    public function signalAsync(string $name, array $args = []): PromiseInterface
    {
        return $this->inner->signal($name, $args);
    }

    public function cancelAsync(): PromiseInterface
    {
        return $this->inner->cancel();
    }
}
```

### Modified: `src/Experiments/Fibers/Workflow.php` factory return-type widening

Exactly three signature hunks change. The bodies stay identical.

- Line 309–311 `newUntypedActivityStub(...): FiberActivityStub` → `newUntypedActivityStub(...): FiberActivityStubInterface`.
- Line 329–332 `newUntypedChildWorkflowStub(...): FiberChildWorkflowStub` → `newUntypedChildWorkflowStub(...): FiberChildWorkflowStubInterface`.
- Line 358–360 `newUntypedExternalWorkflowStub(...): FiberExternalWorkflowStub` → `newUntypedExternalWorkflowStub(...): FiberExternalWorkflowStubInterface`.

This is a **binary-compatible** change for callers using `auto` typing (`$stub = Workflow::newUntypedActivityStub(...)`) and any caller type-hinting against the new interface, but it is a **source-breaking** change for any caller that explicitly type-hinted the concrete class (`FiberActivityStub $stub`). Since the namespace is `@experimental` + `@internal`, this is acceptable; call it out in the commit message.

## Tasks

The four tasks are mutually independent at the file level (each touches a disjoint set of source files) and may be implemented in parallel. Within a task, the listed steps are sequential.

<!-- parallel: tasks A, B, C, D (executed sequentially in coordinator due to shared Workflow.php; hunks are non-overlapping) -->

### Task A — `FiberProxy` (H3 + L9)

Files touched: `src/Experiments/Fibers/FiberProxy.php`.

- [x] **A.1** Add `@template T of object` and `@mixin T` to the class-level docblock; keep `@experimental` and `@internal`. Delete all narrative comment prose in the existing docblock (no per-method descriptions, no "Universal decorator …" essay).
- [x] **A.2** Annotate the constructor parameter as `@param T $inner` (PHPDoc only — runtime type stays `object`).
- [x] **A.3** Replace the `return $result;` fall-through in `__call` with `throw new \LogicException(\sprintf('FiberProxy expects the inner proxy to return a PromiseInterface; got %s.', \get_debug_type($result)));`.
- [x] **A.4** Confirm `composer psalm` still passes; verify (manually, by opening a sample file in an IDE if one is to hand) that `\Temporal\Experiments\Fibers\Workflow::newActivityStub(MyActivity::class)->myMethod(...)` resolves the `myMethod` call against `MyActivity`. (Not a CI gate — a sanity check.)

### Task B — `FiberActivityStub` + new `FiberActivityStubInterface` (H4 + M7)

Files touched: `src/Experiments/Fibers/FiberActivityStub.php`, new `src/Experiments/Fibers/FiberActivityStubInterface.php`, `src/Experiments/Fibers/Workflow.php` (line 311 only).

- [x] **B.1** Create `src/Experiments/Fibers/FiberActivityStubInterface.php` with the three methods (`getOptions`, `execute`, `executeAsync`) declared in *Target end state*. No method-body docblocks.
- [x] **B.2** In `FiberActivityStub.php`: add `implements FiberActivityStubInterface`. Delete the narrative class-level docblock prose (keep `@experimental` / `@internal`).
- [x] **B.3** Rename `createExecution(...)` → `executeAsync(...)`. Signature and body otherwise unchanged.
- [x] **B.4** In `src/Experiments/Fibers/Workflow.php` line 311: change the declared return type of `newUntypedActivityStub` from `FiberActivityStub` to `FiberActivityStubInterface`. Import statement may need to be added/updated.
- [x] **B.5** Grep the repository (`rtk grep "createExecution"`) for any caller of the old name and rename. Likely zero hits outside tests; if any fixture or sample workflow uses it, update.
- [~] **B.6** Run `composer psalm`; run `composer test:accept-fast`. <!-- in-progress: deferred to V.1/V.2 -->

### Task C — `FiberChildWorkflowStub` + new `FiberChildWorkflowStubInterface` (H4 + M8 + M7)

Files touched: `src/Experiments/Fibers/FiberChildWorkflowStub.php`, new `src/Experiments/Fibers/FiberChildWorkflowStubInterface.php`, `src/Experiments/Fibers/Workflow.php` (line 332 only).

- [x] **C.1** Create `src/Experiments/Fibers/FiberChildWorkflowStubInterface.php` with the eleven methods declared in *Target end state* (seven await-flattened + four `*Async` raw-promise variants). No per-method docblocks.
- [x] **C.2** In `FiberChildWorkflowStub.php`: add `implements FiberChildWorkflowStubInterface`. Delete the narrative class-level and per-method docblock prose (keep `@experimental` / `@internal` on the class).
- [x] **C.3** Tighten `getExecution()` declared return type from `: mixed` (with `@return WorkflowExecution` PHPDoc) to `: WorkflowExecution`. Delete the now-redundant `@return` PHPDoc.
- [x] **C.4** Tighten `start()` declared return type from `: mixed` to `: WorkflowExecution` (the inner contract is `CompletableResultInterface<WorkflowExecution>`).
- [x] **C.5** Change `signal()` declared return type to `: void` and drop the `return` keyword on `FiberHelper::await(...)`.
- [x] **C.6** Add four new methods: `startAsync(...)`, `getResultAsync(...)`, `executeAsync(...)`, `signalAsync(...)`. Each returns the inner stub's `PromiseInterface` directly (no `FiberHelper::await`). Keep ordering parallel to the await-flattened siblings.
- [x] **C.7** In `src/Experiments/Fibers/Workflow.php` line 332: change the declared return type of `newUntypedChildWorkflowStub` from `FiberChildWorkflowStub` to `FiberChildWorkflowStubInterface`. Update imports.
- [~] **C.8** Run `composer psalm`; run `composer test:accept-fast`. <!-- in-progress: deferred to V.1/V.2 -->

### Task D — `FiberExternalWorkflowStub` + new `FiberExternalWorkflowStubInterface` (H4 + M8 + M7)

Files touched: `src/Experiments/Fibers/FiberExternalWorkflowStub.php`, new `src/Experiments/Fibers/FiberExternalWorkflowStubInterface.php`, `src/Experiments/Fibers/Workflow.php` (line 360 only).

- [ ] **D.1** Create `src/Experiments/Fibers/FiberExternalWorkflowStubInterface.php` with the five methods declared in *Target end state* (`getExecution`, `signal`, `cancel`, `signalAsync`, `cancelAsync`).
- [ ] **D.2** In `FiberExternalWorkflowStub.php`: add `implements FiberExternalWorkflowStubInterface`. Delete the narrative class-level docblock prose (keep `@experimental` / `@internal`).
- [ ] **D.3** Change `signal()` declared return type to `: void` and drop the `return` keyword on `FiberHelper::await(...)`.
- [ ] **D.4** Change `cancel()` declared return type to `: void` and drop the `return` keyword on `FiberHelper::await(...)`.
- [ ] **D.5** Add `signalAsync()` and `cancelAsync()`: each returns the inner stub's `PromiseInterface` directly.
- [ ] **D.6** In `src/Experiments/Fibers/Workflow.php` line 360: change the declared return type of `newUntypedExternalWorkflowStub` from `FiberExternalWorkflowStub` to `FiberExternalWorkflowStubInterface`. Update imports.
- [ ] **D.7** Run `composer psalm`; run `composer test:accept-fast`.

## Verification

- [ ] **V.1** `composer psalm` exits 0 at strict level 2 (the project's configured level — see `psalm.xml`). No new entries in `psalm-baseline.xml`. The four classes touched here must not regress their baseline status.
- [ ] **V.2** `composer test:accept-fast` exits 0. The renamed `executeAsync`, the `: void` `signal`/`cancel` returns, and the new `*Async` methods are exercised by any Fiber-mode acceptance test that uses them. If no acceptance test currently exercises a raw `executeAsync` call, that gap is owned by M14 (unit tests for `Experiments\Fibers\*`) and is **not** added by this plan.
- [ ] **V.3** Spot-check that `\Temporal\Experiments\Fibers\Workflow::newActivityStub(MyActivity::class)->someActivityMethod(...)` resolves in Psalm's `--show-info` output as a call against `MyActivity::someActivityMethod`, not as `FiberProxy::__call`. Practical check: run `vendor/bin/psalm --show-info=true tests/Acceptance/Extra/**/Fibers/**/*.php` and confirm no `MixedMethodCall` or `UndefinedMagicMethod` entries appear for activity / child / external stub call sites.
- [ ] **V.4** `rtk grep "createExecution"` over `src/`, `tests/`, `testing/`, and any in-tree samples returns no hits (other than possibly in commit messages or this plan).
- [ ] **V.5** `rtk grep "implements FiberActivityStubInterface\|implements FiberChildWorkflowStubInterface\|implements FiberExternalWorkflowStubInterface"` returns exactly three hits, one per decorator class.
- [ ] **V.6** Optional psalm-only smoke assertion: a tiny stub file under `tests/Unit/Experiments/Fibers/PsalmStubsAssertionTest.php` could call `f(FiberActivityStubInterface $s)` and `g(ActivityStubInterface $s)` with the same `Workflow::newUntypedActivityStub(...)` argument to assert the former compiles and the latter doesn't. **Not required by this plan** — the broader Fiber unit-test gap is M14's responsibility — but mentioned here so the implementer doesn't add such a file thinking it's expected.

## Out of scope (explicitly)

- All other Fiber-review findings — each has its own plan:
  - C1–C8: critical hygiene and runtime bugs (separate plans).
  - H1 (`awaitWithTimeout` accept Mutex), H2 (`sideEffect` options), H5 (setFiberMode regression tests), H6 (`MutexYieldTest` constructor), H7 (`gather` cancellability / return), H8 (`runLocked` auto-await), H9 (Scope.php style), H10 (deep-stack acceptance test), H11 (`historyLength` polling).
  - M1–M6, M9–M13: separate runtime and test-integrity plans.
  - L1–L8: separate cleanup plans.
- **M14** — the Fiber-namespace unit-test gap. Adding unit tests for `FiberActivityStub::executeAsync`, `FiberChildWorkflowStub::signalAsync`, `FiberProxy::__call` LogicException branch, etc. is owned by M14. This plan only needs to keep `composer psalm` and `composer test:accept-fast` green.
- **Optional `inner()` escape hatches** mentioned in H4's fix suggestion (review line 200). Exposing `FiberActivityStub::inner(): ActivityStubInterface` (and equivalents on the child / external decorators) would let callers reach the canonical interface for cases where the parallel Fiber interface is insufficient. **Defer** — the `*Async` raw-promise hatches added by M7 cover the practical "compose with `awaitWithTimeout`" use case, and adding `inner()` requires its own design pass on whether to widen the canonical-interface coupling. If the implementer finds adding `inner()` is trivially free (one method per decorator, declared on each new interface), they may add it, but it is not a plan requirement.
- Adding `@template T` generics to the **typed** decorators (`FiberActivityStub` et al.). These are explicit-method classes whose `$inner` is already typed to the canonical interface — analyzers see the correct shape without a template. The review's H3 fix mentions "Same for the typed decorator stubs" but on re-reading, the typed decorators don't have an inference gap analogous to `FiberProxy`'s `__call` problem. **Skip** unless a follow-up review identifies a concrete inference gap.
- Renaming `getExecution()` / `start()` / `execute()` / `signal()` / `cancel()` to anything else — name-symmetry with the canonical interfaces is the whole point of the parallel-interface design.

## Parallelization

Tasks A / B / C / D are mutually independent:

| Task | New files | Modified files |
|---|---|---|
| A | — | `FiberProxy.php` |
| B | `FiberActivityStubInterface.php` | `FiberActivityStub.php`, `Workflow.php` (one signature) |
| C | `FiberChildWorkflowStubInterface.php` | `FiberChildWorkflowStub.php`, `Workflow.php` (one signature) |
| D | `FiberExternalWorkflowStubInterface.php` | `FiberExternalWorkflowStub.php`, `Workflow.php` (one signature) |

The only shared file is `src/Experiments/Fibers/Workflow.php`, and the three hunks edited (B.4, C.7, D.6) are at distinct line ranges (309–315 / 329–336 / 358–363) — they can be applied in any order. Parallel implementation strategy:

1. Spawn four worker agents for A / B / C / D simultaneously.
2. Each agent runs its own `composer psalm` and `composer test:accept-fast` to validate its own slice.
3. After all four merge cleanly, run a final whole-plan `composer psalm && composer test:accept-fast` to catch any cross-task regression (none expected, but cheap to verify).

Composition with other Fiber-fix plans:

- **No conflict** with C1 (worker.php debug-line plan), C2/C3/C4/C5 (test-file plans), or any Phase 2 runtime plan (C6/C7/C8/M9 — all in `src/Internal/Workflow/Process/`).
- **Coordinate** with any concurrent plan editing `src/Experiments/Fibers/Workflow.php` (H1, H2, H7, H8). The hunks here are confined to the "Proxy factories" section (lines 297–363); those plans touch the "Concurrency & locking" and "sideEffect / awaitWithTimeout" sections. No line-range overlap, but if a sibling plan reformats imports near the top of the file, this plan's import additions may need a trivial rebase.
- **No conflict** with M14 (Fiber unit-test plan) — once this plan lands, M14 has stable interfaces to write tests against.

## Risks / open questions

1. **`getExecution()` tightening on `FiberChildWorkflowStub`.** Today's signature is `: mixed` with `@return WorkflowExecution`. Tightening to declared `: WorkflowExecution` is safe because the inner contract is `PromiseInterface<WorkflowExecution>` and the await always resolves to a `WorkflowExecution` — but it's a covariance change that could trip Psalm if any caller is currently passing the result to a slot typed `mixed`. Expected risk: very low (`mixed` accepts `WorkflowExecution`). Will be caught by V.1.
2. **`start()` tightening on `FiberChildWorkflowStub`.** Same shape as #1 — inner is `CompletableResultInterface<WorkflowExecution>`, declared return narrows from `mixed` to `WorkflowExecution`. Same low risk profile.
3. **Concurrent `Workflow.php` edits from sibling plans.** If H1/H2/H7/H8 plans land first and reorder imports or reformat the "Proxy factories" section, B.4/C.7/D.6 will need a trivial rebase. Mitigation: this plan's three hunks are signature-line-only and grep-locatable (`newUntypedActivityStub`, `newUntypedChildWorkflowStub`, `newUntypedExternalWorkflowStub`) — easy to relocate if line numbers shift.
4. **Source-breaking impact of the `createExecution` rename.** Callers who adopted the experimental API in its current shape will get a `BadMethodCallException` at runtime (or a Psalm/IDE error at edit time) on the old name. Acceptable because the namespace is `@experimental` + `@internal`, and there is no realistic non-test caller outside the SDK repo today. Call it out in the commit message: "BREAKING (experimental API): `FiberActivityStub::createExecution()` renamed to `executeAsync()`."
5. **Source-breaking impact of the factory return-type widening.** Callers who type-hinted the concrete class (`FiberActivityStub $s`) instead of `auto`-typing will need to update to the interface. Same acceptable-because-experimental reasoning; same commit-message call-out.
6. **`*Async` naming collision with hypothetical future PHP keywords.** PHP 8.x reserves no `async` keyword and no concrete proposal is on the table for one. If a future PHP version ever introduces `async` as a reserved word, method names are unaffected (reserved words are still legal method names). No risk.
7. **Should `getOptions()` on `FiberChildWorkflowStubInterface` return `ChildWorkflowOptions` (concrete class) or be widened to an interface?** The canonical `ChildWorkflowStubInterface::getOptions()` returns the concrete class — mirror that. No risk.
8. **Optional psalm-only stub-assertion test file (V.6).** The plan deliberately does NOT add one to avoid scope creep into M14's territory. If the implementer finds Psalm's existing coverage of the Fibers namespace insufficient to catch the H3/H4 regression, escalate to the M14 owner rather than adding ad-hoc tests under this plan.
