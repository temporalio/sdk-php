# Plan: Force `fiberMode=false` on the cloned ScopeContext in the query executor

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** fast
**Type:** correctness hardening (defensive)
**Source finding:** `.ai-factory/research/fibers-review.md` — C7 (CRITICAL, lines 128–142)

## Settings

- Testing: no — regression tests are tracked in sibling plan P12 (`fibers-add-regression-tests.md`, not yet written). End-to-end verification only.
- Docs: no
- Logging: minimal — one-line behaviour change, no new runtime paths
- Roadmap linkage: none

## Scope statement

Touches `src/Internal/Workflow/Process/Process.php` only — safe to parallelize with most Fiber-fix plans EXCEPT plan P12 (regression tests for this fix) which depends on this plan completing.

## Finding

In `src/Internal/Workflow/Process/Process.php` the query executor closure (lines 54–71) builds a per-query `ScopeContext` by cloning the process scope context:

```php
$context = $this->scopeContext
    ->withInput(new Input($this->scopeContext->getInfo(), $input->arguments));
$context->setReadonly(true);
Workflow::setCurrentContext($context);
return $handler($input->arguments);
```

`WorkflowContext::withInput()` (lines 177–184 of `src/Internal/Workflow/WorkflowContext.php`) is `clone $this` plus an input override. Because `$fiberMode` is a plain `bool` property on `ScopeContext` (declared at line 32, `private bool $fiberMode = false;`), it is **copied by value** into the clone. The query executor then explicitly forces `readonly = true` but does **not** force `fiberMode = false`.

Today this is **incidentally correct**: the Fiber bridge in `Scope.php` clears the flag in its `finally` block, so between Fiber runs `$this->scopeContext->isFiberMode()` is `false`, and the clone inherits `false`. Any future change that keeps `fiberMode=true` across the query boundary (a wrong fix for C6, or a direct `DeferredFiber` wiring that doesn't clear the flag) makes queries crash with `FiberError: Cannot suspend outside of a fiber` the moment the handler touches a `Fibers\Workflow::*` method that internally calls `FiberHelper::await()`.

The fix is defensive: state the invariant explicitly at the cloning site instead of relying on a sibling subsystem's `finally` block. `ScopeContext::setFiberMode(bool)` exists at lines 106–109 of `src/Internal/Workflow/ScopeContext.php`. `withInput()` has a `static` return type, and `$this->scopeContext` is a `ScopeContext`, so the clone's static type is `ScopeContext` — calling `setFiberMode(false)` on it is type-safe with no extra narrowing.

## Target end state

`src/Internal/Workflow/Process/Process.php` lines 59–63 read exactly:

```php
$context = $this->scopeContext
    ->withInput(new Input($this->scopeContext->getInfo(), $input->arguments));
$context->setReadonly(true);
$context->setFiberMode(false);
Workflow::setCurrentContext($context);
return $handler($input->arguments);
```

No other lines in `Process.php` change. No other files change.

## Tasks

- [ ] **#1** In `src/Internal/Workflow/Process/Process.php`, inside the `setQueryExecutor(...)` closure, insert `$context->setFiberMode(false);` on a new line immediately after the existing `$context->setReadonly(true);` (after current line 61), and immediately before `Workflow::setCurrentContext($context);`.

- [ ] **#2** Verify the file:
  - `php -l src/Internal/Workflow/Process/Process.php` — must report `No syntax errors detected`.
  - `rtk git diff master..HEAD -- src/Internal/Workflow/Process/Process.php` — the only branch-local addition vs. `master` in the query executor block must be the single new line `$context->setFiberMode(false);`.

- [ ] **#3** Static analysis sanity check:
  - `composer psalm` — must not introduce new errors. The call resolves against `ScopeContext::setFiberMode(bool): void`; `$context` carries the `static` return type from `withInput()` so Psalm sees it as `ScopeContext`. No baseline churn expected.

- [ ] **#4** End-to-end verification (no new tests — manual / CI check only):
  - Identify or pick any existing Fiber-mode acceptance workflow that registers a query handler calling a sync `Fibers\Workflow::*` method such as `Fibers\Workflow::getInfo()`. Run that scenario via `composer test:accept-fast` (or its targeted `--filter`) and confirm the query still succeeds. The expected outcome is **no behaviour change** — this fix only matters under the future fiberMode-leak scenarios described in C7.

## Out of scope (explicitly tracked separately)

- **Update validator path** (`Process.php` lines 75–95). It also clones via `withInput(...)` at line 80 and is **not** readonly. C7's scope is the query path only; the update-validator clone is acknowledged here so reviewers know it was considered, but it is **out of scope for this plan** and will be handled in a separate finding/plan if/when it is promoted.
- **Update executor path** (`Process.php` lines 99–127) uses `createScope(...)` — a fresh scope built by `Scope::createScope`, not a `withInput` clone — so `fiberMode` is not propagated from the parent context there. No change required.
- **Signal handler path** (`Process.php` lines 129+). Same `createScope` mechanism. Not affected.
- **Regression tests** (unit test that flips `fiberMode=true` on a `ScopeContext`, clones via `withInput`, asserts the clone's flag is reset by the query executor; plus a Fiber-mode acceptance test for a query touching `Fibers\Workflow::getInfo()`). Tracked in sibling plan **P12 — `fibers-add-regression-tests.md`** (not yet written). P12 depends on this plan landing first because the assertion target is the new line added here.
- **Other C-series Fiber findings** (C1–C6, C8) and all H/M/L items — each has its own plan.

## Why minimal (no helper extraction)

The clone-then-reset pattern would only justify a helper (`forNonFiberQuerySnapshot()` or similar on `ScopeContext`) if it appeared in two or more sites. Today only the query path needs the reset — the update validator at line 80 is explicitly out of scope, and even if it later needs the same treatment, "one line in two places" is still cheaper than introducing a named helper that hides one assignment. Per the fast-mode bias toward the minimal change, the fix is a single statement in a single closure.

## Parallelization

This plan is safe to dispatch alongside all other Fiber-fix plans **except** P12 (regression tests for this fix), which must run after this plan because its assertions target the new `setFiberMode(false)` line. It modifies one file, introduces no new symbols, and changes no public API.
