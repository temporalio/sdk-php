# Project-specific rules for `/aif-review`

These rules **override** the general checklist in the skill — when they apply,
they are non-negotiable. Every `/aif-review` run MUST execute the dedicated
passes below in addition to the default correctness/security/perf checks.

## Mandatory pass — Naming (Critical, not Suggestion)

`CLAUDE.md` rule: **"Use full words for identifiers — no informal abbreviations."**
This rule is **enforced at review time**, not aspirational.

### How to run the pass

After reviewing the diff for bugs/security/perf, do a **separate dedicated walk**
over every identifier the diff introduces or touches:

- Directory names (`Skel/`, `Tmp/`, `Cfg/`)
- File names
- Variable names (Bash `SKEL=`, PHP `$attrs`, Go `var ctx`)
- Function/method names
- Type names (classes, structs, interfaces)
- Workflow type strings, task queue names, namespace strings
- README/docs tree diagrams (this is where stale names hide — when you redraw
  a Layout tree, every node in it counts as a name you're endorsing)
- Plan files' Layout sections

For each identifier, ask: **is this a full word, or an abbreviation?**

### Failing the check is a Critical issue

If a diff introduces or touches an abbreviated identifier, raise it as
**Critical**, not Suggestion. The rationale: abbreviations rot the codebase
permanently (rename later costs N callsites), they slow down every future
reader, and the project rule is explicit.

### Known-bad patterns (auto-flag, no judgment call)

| Bad | Correct |
|---|---|
| `Skel` | `Templates` / `Skeletons` |
| `attrs` | `attributes` |
| `impl` | `implementation` |
| `ref` | `reference` |
| `opts` | `options` |
| `ctx` | `context` (BUT: `ctx` is the standard Go SDK convention for `context.Context` and `workflow.Context` — accept in Go workflow/activity bodies only; flag everywhere else) |
| `cfg` | `config` |
| `tmp` | `temp` / `temporary` |
| `msg` | `message` |
| `req` / `res` | `request` / `response` |
| `arg` / `args` | `argument` / `arguments` (BUT: `args` is established for shell `$@`, Go `os.Args`, Java `String[] args` — accept in those signatures only) |

The exceptions list is **closed**: only well-established wire-protocol
abbreviations (`RPC`, `URL`, `URI`, `ID`, `gRPC`) and the language-conventional
ones called out above. Anything else → flag as Critical.

### Where the rule has historically been missed

Past `/aif-review` runs on this repo missed `Framework/Skel/` because:

1. The reviewer focused on bugs/risks in the lifecycle scripts (the "interesting"
   part of the diff) and skimmed the README Layout diagrams.
2. The reviewer treated naming as a Suggestion-tier check rather than Critical.
3. The reviewer accepted "the directory already existed" as an excuse for not
   flagging a name that appeared in the new diff (in README Layout, in scaffolder
   `SKEL=` variable, in plan files).

**None of those are valid excuses.** If a name appears in the diff — even if it
existed before — and it violates the rule, flag it. The diff is your scope; the
name is in it; the rule is explicit.

### Concrete review template addition

When producing the review summary, include this section even if empty:

```markdown
### Naming Audit

Identifiers introduced or touched in this diff:
- <list every new/renamed identifier>

Each one verified against the "full words, no abbreviations" rule from CLAUDE.md:
- <pass / fail per identifier>

Critical issues from this pass: <count>
```

If the count is non-zero, those issues go in the **Critical Issues** section of
the main review, not Suggestions.

## Mandatory pass — `CLAUDE.md` "Comments" rule

`CLAUDE.md` rule: **"Do not write comments. This is not a soft default — it's
the rule."** with narrow exceptions (PHPDoc type annotations, one-line hidden
constraints).

### How to run the pass

Walk every line added in the diff. For each comment line (`//`, `#`, `/* */`,
PHPDoc, Go doc):

- Is it a type annotation the type system can't express (PHPDoc generics,
  `@param non-empty-string`)? — **Accept.**
- Is it a one-line note capturing a hidden constraint a reader genuinely
  cannot recover from the code (workaround for upstream bug, protocol
  invariant, deliberate ordering)? — **Accept.**
- Section labels like `// Register Workflow`, `// Feature flags`? — **Flag as Critical.**
- Restating the code (`// loop over users`, `// return null`)? — **Flag as Critical.**
- Multi-line docblock essays explaining *why* a function exists? — **Flag as Critical.**
- "Note: ", "TODO: " without an issue link, "HACK: "? — **Flag as Critical.**
- Inline comments on enum cases / array entries explaining "why each entry is there"? — **Flag as Critical.**

### Known reviewer failures on this rule

Past Go scenario ports added several explanatory comments above driver closures
and worker-setup blocks that violate this rule and were not flagged in review.
Treat any new comment in `Scenarios/*/go/main.go`, `Scenarios/*/java/.../Main.java`,
or `Scenarios/*/php/scenario.php` as suspect until proven to fit the narrow
exceptions list.

## Mandatory pass — Parity wire-identity invariants

The parity tier's whole point is that three SDKs produce **identical normalized
event histories**. The following identifiers MUST be byte-identical across all
three sides of a scenario:

- **Workflow type names** (`Parity_<Name>` strings, e.g. `"Parity_HelloWorld"`)
  — appear in PHP `#[WorkflowMethod(name: ...)]`, Java `@WorkflowMethod(name = ...)`,
  Go `workflow.RegisterOptions{Name: ...}` AND in the driver's `ExecuteWorkflow`
  call.
- **Activity names** (`#[ActivityMethod('...')]` / `@ActivityMethod(name = "...")` /
  `activity.RegisterOptions{Name: "..."}`).
- **Signal names**, **query names**, **child workflow type names**.

### How to run the pass

For every scenario file touched by the diff:

1. Grep for the workflow type string. Confirm the same exact string appears in
   `php/scenario.php`, `java/.../Main.java`, `go/main.go`, AND in any driver
   closures that call `ExecuteWorkflow` / `client.QueryWorkflow` /
   `c.SignalWorkflow`.
2. Same for activity, signal, query names.
3. A typo on one side that the type system can't catch silently produces a
   `workflowType.name` diff at fixture-comparison time — no normalizer should
   mask it. Flag as **Critical**.

### Known-bad signal pattern in Go

`workflow.SetSignalHandler` **does not exist** in `go.temporal.io/sdk` —
signals are received via `workflow.GetSignalChannel(ctx, name).Receive(...)`.
Any Go scenario in a diff calling `workflow.SetSignalHandler` is broken; flag
as Critical with the fix.

## Mandatory pass — `markTestSkipped` / `markTestIncomplete` usage

`CLAUDE.md` rule: **"Never use `markTestIncomplete()` or `markTestSkipped()`
to bypass a failing test."** Allowed only with a paired `// TODO(plan-or-issue-ref):`
pointer to the unblocking work.

The only sanctioned current use in this repo is `Framework/AbstractParityScenarioTest::normalizedGoMatchesPhp`
when `fixtureGo()` returns null — paired with a plan-ref TODO. Anything else
in a diff is a Critical issue.

## Mandatory pass — Fiber-mode workflow correctness (Critical)

The Fiber facade under `src/Experiments/Fibers/` is `@experimental`. When the diff touches
**any** file under `src/Experiments/Fibers/`, `src/Internal/Workflow/Process/{Scope,Process}.php`,
or `tests/Acceptance/Extra/.../Fibers/`, run this dedicated pass:

### Pass items

1. **Fiber-mode workflow body — no `yield`, no `\Generator` return type.**
   In every `tests/Acceptance/Extra/.../Fibers/*.php` workflow body that uses
   `\Temporal\Experiments\Fibers\Workflow`, grep for `yield` and `: \Generator`.
   Any hit means the body silently falls back to the Generator path — the Fiber bridge
   does not run, sentinel assertions become meaningless. **Critical**.

2. **Workflow type name / activity prefix uniqueness across Fiber/non-Fiber siblings.**
   Run `grep -rhEo 'name:\s*"([^"]+)"' tests/Acceptance/Extra/ | sort | uniq -c | awk '$1 > 1'`
   and `grep -rhEo "prefix:\s*'([^']+)'" tests/Acceptance/Extra/ | sort | uniq -c | awk '$1 > 1'`.
   Any duplicate is a worker-boot crash (`Entry with same identifier already has been registered`)
   waiting to happen. **Critical**.

3. **`Fibers\Mutex` leak into stable API.**
   Grep the diff for `Temporal\\Experiments\\Fibers\\Mutex` / `Fibers\\Mutex` in any file under
   `src/Workflow/`, `src/Internal/Workflow/`, `src/Interceptor/` (interface/PHPDoc/type union).
   The experimental type MUST be unwrapped at the Fiber facade boundary via
   `Experiments\Fibers\Workflow::unwrapConditions(...)` → `$mutex->getInner()`. Stable API
   surfaces accept only base `\Temporal\Workflow\Mutex`. **Critical**.

4. **`Scope::cancel()` MUST NOT call `setFiberMode(false)`.**
   The only legitimate write sites for `ScopeContext::$fiberMode` are
   `Scope::createFiberHandler` (bridge's `try/finally`) and `Scope::destroy()`.
   Any new reset in `cancel()`, `next()`, `nextPromise`, `handleError` etc.
   corrupts state on cross-scope cancel propagation. **Critical**.

5. **`Scope::startScope()` context save/restore — Fiber-mode-gated.**
   If the diff touches `startScope()`, verify the save+restore is wrapped in
   `if ($this->scopeContext->isFiberMode()) { ... }`. Unconditional restore
   breaks Generator workflows that rely on context leak through `makeCurrent()`
   in the loop's `nextPromise.$onFulfilled` path. **Critical**.

6. **`Process.php` query executor explicit fiberMode reset.**
   The closure registered by `setQueryExecutor(...)` must call
   `$context->setFiberMode(false)` right after `setReadonly(true)`. Queries are
   synchronous and cannot suspend a Fiber. Missing reset is incidental-correctness today,
   crash tomorrow. **Critical**.

7. **`StackRenderer::$userFrameSeen` — NEVER REVERT.**
   The flag gates `file`/`line` exposure to the FIRST user frame only. Required by
   `BuiltInPrefixedHandlersTest::enhancedStackTrace`. Auto-revert by code-review agent is the
   known regression — explicitly flag any diff that removes `$userFrameSeen` or its
   conditional with a "do not revert" callout. **Critical** if the revert lands.

8. **Debug-call leftovers (`file_put_contents`, `trap()`, `$a=1;`, `var_dump`, `print_r`).**
   Grep `src/` and `tests/` for these in the diff. `file_put_contents` in `Process.php`
   hot path corrupts RoadRunner's goridge STDOUT framing and times out workflows.
   `trap()` calls violate the `Arch` test. **Critical**.

9. **No `markTestSkipped`/`markTestIncomplete` in any new test.**
   Hard rule from the user. The only sanctioned occurrence is
   `Harness\Update\TaskFailureTest::retryableException` (pre-existing). Any other usage
   in the diff is **Critical** — fix the underlying bug instead.

10. **Fiber unit tests must `Facade::setCurrentContext(null)` in `tearDown()`.**
    Without it, tests in `tests/Unit/Experiments/Fibers/` and
    `tests/Unit/Internal/Workflow/` pollute static state across the suite.
    **Critical** if a new Fiber-touching unit test lacks the reset.

11. **Expression-position `Workflow\X::method()` in Fibers test files.**
    Run a Python regex with negative lookbehind on each Fibers test file:
    ```
    python3 -c "import re; [print(f'{ln}: {m.group(0)}') for ln, line in enumerate(open('<file>.php').read().split('\n'), 1) if not line.lstrip().startswith('use ') for m in re.finditer(r'(?<!\\\\)\bWorkflow\\\\[A-Z]\w*', line)]"
    ```
    Any non-`use` hit (e.g., `Workflow\ChildWorkflowOptions::new()`, `Workflow\ParentClosePolicy::Abandon`)
    is a runtime fatal `Class not found` — the Fibers facade alias shadows the attribute-class namespace.
    Symptom in tests: "Child Workflow execution not found in the history" or empty workflow history.
    Flag as **Critical** with fix: rewrite to fully-qualified `\Temporal\Workflow\<Class>`.

12. **`Promise::race/all/any/some` with blocking Fibers calls.**
    Grep the diff for `Promise::(race|all|any|some)` in Fibers test files. If any of the array
    arguments is `Workflow::timer(...)`, `Workflow::await(...)`, or `$stub->getResult/start/signal/execute(...)`
    (no `Async`/`Promise` suffix), the combinator is broken. PHP evaluates array args eagerly —
    each blocking call suspends the fiber sequentially, defeating parallel-promise semantics.
    Symptom: tests time out or workflows fail with `TIMEOUT_TYPE_START_TO_CLOSE`.
    Flag as **Critical** with fix: replace with `*Promise()`/`*Async()` variants and wrap the
    call in `FiberHelper::await(Promise::race(...))`. Required imports: `Temporal\Experiments\Fibers\FiberHelper`.

13. **Global `\define()` in Fiber-mirror test files.**
    Grep Fibers test files for `\\define\(\s*['\"][A-Z]` (PHP-regex matching bare-string `\define`).
    If the same constant is defined in the non-Fiber sibling at the same path, this is a global-constant
    collision — symptom: `PHP Warning: Constant X already defined` at worker boot.
    Flag as **Critical** with fix: rewrite to `\define(__NAMESPACE__ . '\\NAME', value)` —
    namespaced constants are unique per-namespace; same-file references continue to resolve
    via PHP's namespace-constant fallback.

14. **Plugin `getName()` collision between Fiber and non-Fiber siblings.**
    When the diff touches a Fibers-mirror test that declares plugin classes (implementing
    `ClientPluginInterface`, `ConnectionPluginInterface`, `WorkerPluginInterface`), grep their
    `getName()` return values against the non-Fiber sibling's plugin classes:
    ```
    grep -E "return '\\S+';" tests/Acceptance/.../<Test>.php tests/Acceptance/.../Fibers/<Test>.php
    ```
    Same name = global-registry duplicate. Symptom: worker boot fails with
    `RuntimeException: Duplicate plugin "<name>"` (from `src/Plugin/PluginRegistry.php:42`).
    Flag as **Critical** with fix: append `-fibers` suffix to plugin names in the Fibers-mirror;
    update any `expectExceptionMessage('Duplicate plugin "<name>"')` assertion accordingly.

15. **`executeActivity` call-site mismatched with injected `ActivityInterface(prefix:)`.**
    When the diff adds `#[ActivityInterface(prefix: 'Fibers_')]` to a Fibers-mirror test, grep the
    SAME FILE for `Workflow::executeActivity\(\s*['\"]` to find every bare-string call site.
    For each, verify the first argument starts with `Fibers_` (matching the injected prefix).
    The activity wire name is constructed as `<prefix><method-name-or-explicit-name>` per
    `src/Internal/Declaration/Reader/ActivityReader.php:168`. Any unmatched call-site silently
    times out the activity at runtime — server has no handler for the requested name.
    Symptom: `ActivityFailure: retryState='RETRY_STATE_TIMEOUT'` or `RuntimeException: Activity did not start`.
    Flag as **Critical** with fix: rewrite call sites to use the prefixed wire name.
    Activities invoked via `Workflow::newActivityStub(Class::class)->method()` are immune — proxy
    resolves names via reflection automatically.

## Severity recap

The default `/aif-review` outputs four buckets: Critical, Suggestions,
Questions, Positive Notes. The passes above (Naming, Comments, Wire-identity,
Skip-test, Fiber-mode correctness) all land in **Critical** when they fire — not Suggestions. The
rationale: each of them has been missed in past reviews precisely *because* it
was treated as low-severity. Reclassifying them to Critical is the lever that
makes the next reviewer actually pause on them.
