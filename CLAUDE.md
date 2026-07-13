# CLAUDE.md - Temporal PHP SDK

## Project Overview

Temporal PHP SDK (`temporal/sdk`) - official PHP client library for the Temporal workflow orchestration platform. Enables building resilient, distributed workflows and activities in PHP. Requires PHP 8.1+.

## Runtime в двух абзацах

PHP-воркер ничего сетевого не делает во время workflow execution. Persistent gRPC к Temporal держит **RoadRunner** (Go-плагин `rrtemporal` на базе Temporal Go SDK). PHP общается с RR через локальный goridge-pipe, отдавая декларативные команды (`NewTimer`, `ExecuteActivity`, `ExecuteNexusOperation`, …) и получая в ответ events/responses. gRPC-клиент в SDK (`Temporal\Client\GRPC\ServiceClient`) есть, но используется только для **client-side API** (вне workflow): `WorkflowClient`, `ScheduleClient`, `OperatorClient`.

Workflow-метод — это PHP-generator. Его «крутит» `Scope::next()` ([src/Internal/Workflow/Process/Scope.php](src/Internal/Workflow/Process/Scope.php)): смотрит `Generator::current()`, если promise — навешивает on-fulfilled, который через `defer()` поставит в loop вызов `Generator::send($result)`. Loop — это event-emitter с фиксированными слоями (`ON_TICK`, `ON_QUERY`, …), реализованный самим `WorkerFactory`. ReactPHP loop'а в SDK нет. Replay-логики в PHP тоже нет: воркер при replay'е переисполняет workflow с нуля, снова генерирует те же команды (request ID `9000+`, статический counter), а RR резолвит их мгновенно из истории — для PHP это просто «быстрые promise'ы».

Подробности и file:line ссылки:
- [docs/runtime/](docs/runtime/) — архитектура, wire-протокол PHP↔RR, корутины и replay.
- [docs/nexus/](docs/nexus/) — Nexus spec, handler-side SDK, RR-контракт (handler + caller).
- [docs/data-conversion/](docs/data-conversion/) — converter chain (Null/Binary/ProtoJson/Proto/Json), `EncodedValues`/`EncodedCollection`, `RawValue`, поздняя инъекция converter'а, Marshaller для DTO, integration map (codec/routes/Nexus/failures).
- [docs/testing/](docs/testing/) — виды тестов (unit/functional/acceptance/arch), инфраструктура (bootstrap'ы, lifecycle серверов, fast/slow split), `testing/` mini-framework.

## Common Commands

### Testing

```bash
composer test:unit           # Unit tests (tests/Unit, suffix *TestCase.php)
composer test:func           # Functional tests (tests/Functional, suffix *TestCase.php) — requires Temporal server
composer test:accept         # All acceptance tests (tests/Acceptance, suffix *Test.php) — requires Temporal server
composer test:accept-fast    # Fast acceptance tests only
composer test:accept-slow    # Slow acceptance tests only
composer test:arch           # Architecture constraint tests (tests/Arch)
```

Run a single test:
```bash
vendor/bin/phpunit --testsuite=Unit --filter=SomeTestCase
tests/runner.php vendor/bin/phpunit --testsuite=Functional --filter=SomeTestCase
```

Unit tests don't require external services. Functional and acceptance tests require a running Temporal server (`TEMPORAL_ADDRESS=127.0.0.1:7233` by default) and RoadRunner. Bootstrap in `tests/bootstrap.php` auto-detects the suite and includes the suite-specific bootstrap (e.g. `tests/Acceptance/bootstrap.php`).

**Never start Temporal or RoadRunner manually before running the test suite.** The functional and acceptance bootstraps spin up the test server and the RR worker themselves. Running `./temporal server start-dev` or `rr serve` by hand fights the bootstrap (port 7233 double-bind, stale workers) and produces misleading errors. If tests fail with server/worker issues, look at `runtime/rr.log`, `runtime/rr.err.log`, and `runtime/tests/logs/*.log` — do not try to "warm up" or "verify" the binaries by launching them yourself.

`tests/runner.php` is a CI workaround script that wraps PHPUnit execution with JUnit log parsing.

### Code Style

```bash
composer cs:diff    # Check code style (php-cs-fixer dry-run)
composer cs:fix     # Fix code style
```

Config: `.php-cs-fixer.dist.php` — uses `Spiral\CodeStyle\Builder`, covers `src/` and `testing/src/`.

### Static Analysis

```bash
composer psalm              # Run Psalm (error level 2, strict)
composer psalm:baseline     # Update psalm baseline
```

Config: `psalm.xml` — error level 2, `strictBinaryOperands=true`, `findUnusedVariablesAndParams=true`, baseline in `psalm-baseline.xml`.

### Code Generation

```bash
make generate-proto     # Generate protobuf PHP code
make generate-client    # Generate gRPC client stubs
```

### Binaries

```bash
composer get:binaries   # Download Temporal server & RoadRunner binaries
```

## Project Structure

```
src/                    Main SDK source code
  Activity/             Activity definitions, context, options
  Workflow/             Workflow definitions (WorkflowMethod, SignalMethod, QueryMethod, UpdateMethod)
  Client/               WorkflowClient, ScheduleClient, ServiceClient
  Client/GRPC/          gRPC connection and transport
  Worker/               Worker factory, RoadRunner integration
  Interceptor/          Interceptor interfaces and pipelines
  Plugin/               Plugin system (Worker, Client, Schedule, Connection plugins)
  Common/               Shared DTOs (RetryOptions, SearchAttributes, etc.)
  DataConverter/        Serialization/deserialization, type system
  Exception/            Exception hierarchy
  Internal/             Internal implementation (not public API)
  Nexus/                Nexus RPC handler-side library (data model, service routing,
                        middleware, serialization). Subdirs: Attribute/, Exception/,
                        Handler/, Internal/, Serializer/, Validation/.
tests/
  Unit/                 Unit tests (*TestCase.php) — no external deps
  Functional/           Functional tests (*TestCase.php) — needs Temporal server
  Acceptance/           Acceptance tests (*Test.php) — full E2E
    Extra/              SDK-specific acceptance tests
    Harness/            Cross-SDK harness tests
  Arch/                 Architecture constraint tests
  Fixtures/             Test fixtures (sample workflows, activities, DTOs)
  Nexus/                Nexus subsystem tests (Unit/ uses *Test.php suffix,
                        plus Fixture/ and Support/). Picked up by the Unit suite.
testing/                Testing framework (TestService, ActivityMocker, Environment)
resources/scripts/      Code generation scripts
```

## Architecture & Conventions

### Strict Types

All PHP files must start with `declare(strict_types=1);`. Strong type hints and return types are required everywhere.

### Naming

Use full words for identifiers — no informal abbreviations.

- Wrong: `$attrs`, `$impl`, `$ref`, `$opts`, `$ctx`
- Right: `$attributes`, `$implementation`, `$reflection` (or `$reference`), `$options`, `$context`

The exceptions are well-established protocol abbreviations baked into wire-level
contracts (e.g. `RPC`, `URL`, `URI`, `ID`) and standard short variable names in
narrow scopes (`$e` for an exception in a `catch`, `$i` for a loop counter).
When in doubt, write the full word.

### Control flow: prefer `if`-statements over short-circuit side effects

Don't smuggle imperative actions into boolean expressions. Each statement
should make either a decision (a condition guarding a block) or an action,
not both at once.

Wrong:

```php
$result === 'ok' && $handler->continue();
$value !== null or throw new \LogicException('missing value');
```

Right:

```php
if ($result === 'ok') {
    $handler->continue();
}

if ($value === null) {
    throw new \LogicException('missing value');
}
```

Reasons: the `if`-form is easier to scan, easier to step through in a debugger,
plays nicely with code coverage tools, and doesn't rely on the reader spotting
that the right-hand side is a side-effecting expression rather than a check.

### Namespace Structure

Root namespace: `Temporal\`. Mirrors directory layout:
- `Temporal\Workflow\`, `Temporal\Activity\`, `Temporal\Client\`, `Temporal\Worker\`
- `Temporal\Interceptor\`, `Temporal\Plugin\`, `Temporal\Internal\`
- Tests: `Temporal\Tests\`, Testing framework: `Temporal\Testing\`

### PHP 8 Attributes

Workflows and activities are defined using PHP 8 attributes (not annotations):
- Class-level: `#[WorkflowInterface]`, `#[ActivityInterface(prefix: "...")]`
- Method-level: `#[WorkflowMethod]`, `#[ActivityMethod]`, `#[SignalMethod]`, `#[QueryMethod]`, `#[UpdateMethod]`

### Key Patterns

- **Facade pattern**: `Workflow` and `Activity` classes provide static context-aware access
- **Interceptor chain**: `WorkflowInboundCallsInterceptor`, `WorkflowOutboundRequestInterceptor`, `ActivityInboundInterceptor`
- **Plugin system**: `WorkerPluginInterface`, `ClientPluginInterface`, `ScheduleClientPluginInterface`, `ConnectionPluginInterface`
- **Data converters**: `DataConverterInterface` with multiple implementations (Proto, JSON, Binary, Null)

### Dependency Rules (ENFORCED)

Namespace dependency directions — violations must be rejected:

- `Internal\*` → any public namespace (implements public interfaces)
- `Client\*` → `Common\*`, `DataConverter\*`, `Exception\*`
- `Interceptor\*` → `Common\*`, `Workflow\*`, `Activity\*`
- `Nexus\*` → `Common\*`, `Exception\*`, `Workflow\*` (must not reach into `Internal\*` /
  `Client\*`)
- `Worker\*` → `Internal\*` (worker bootstraps internal machinery)
- FORBIDDEN: Public namespaces → `Internal\*`
- FORBIDDEN: `Activity\*` → `Workflow\*` or vice versa (peer namespaces)
- FORBIDDEN: `DataConverter\*` → `Client\*` (lower → higher layer)

### Naming (file-level)

- Files: PascalCase (`WorkflowClient.php`)
- Variables: camelCase (`$workflowClient`)
- Methods: camelCase (`startWorkflow()`)
- Classes: PascalCase (`WorkflowClient`)
- Constants: UPPER_SNAKE_CASE

### File Naming

- Interfaces: `*Interface.php`
- Traits: `*Trait.php`
- Unit/Functional tests: `*TestCase.php`
- Acceptance tests: `*Test.php`
- Nexus subsystem unit tests: `*Test.php` (under `tests/Nexus/Unit`, picked up by the Unit suite)

### Match the existing style before writing code

Before adding or changing code, look at the surrounding files and match what's
already there: naming, member ordering, visibility patterns, which language
features are used (e.g. `readonly` properties + named arguments vs. setters,
`match` vs. `switch`, enums vs. constants, attribute placement, nullability
style). New code should be indistinguishable from neighbouring code.

If a new pattern is genuinely needed, justify it explicitly — don't introduce
a parallel style by accident.

### Comments

**Do not write comments.** This is not a soft default — it's the rule. Names,
types, and small focused functions are the documentation. If a block needs a
comment to be understood, the block is wrong: rename it, extract it, or split
it until it isn't.

Banned in particular:

- Section labels like `// Register Workflow`, `// Feature flags`, `// Scan all
  the test cases`. If the code below needs a label, extract a method whose
  name *is* the label.
- Restating the code (`// loop over users`, `// return null`).
- Multi-line docblock essays explaining *why* a function exists, when it was
  introduced, what experiments validated it, or which sibling class it
  mirrors. That belongs in commit messages and PR descriptions, not in the
  file. Comments rot; git history doesn't.
- "Note: ", "TODO: " without an issue link, "HACK: ", or any other meta
  narration aimed at a future reader.
- Inline comments on enum cases / array entries explaining *why each entry is
  there*. If an entry needs justification, either the test name carries it or
  it doesn't belong in the list.

Allowed, narrowly:

- PHPDoc type annotations the type system can't express (`@param non-empty-string`,
  `@return list<class-string>`, generic templates). These are types, not prose.
- One short line capturing a hidden constraint a reader genuinely cannot
  recover from the code — a workaround tied to a specific upstream bug, a
  protocol invariant, a deliberate ordering requirement. One line. No essay.

When in doubt: delete the comment. If a reviewer later asks "why?", the
answer goes in the commit message, not back into the file.

### Tests must exercise real behaviour

A test exists to catch real failures — wire formats, branching logic,
contracts between collaborators, error paths, edge cases that could
realistically break.

Tautologies are not tests. Do not write:

```php
$value = 5;
self::assertSame(5, $value);

$dto = new Foo(name: 'x');
self::assertSame('x', $dto->name);
```

These pass by construction and catch nothing. They also don't belong in the
codebase even as "coverage filler". If the only thing a test does is read
back what it just wrote, delete it or replace it with something that
actually exercises behaviour (a method call, a state transition, a
serializer round-trip, an error path).

When adding a test, ask: *what production change would make this fail?* If
the answer is "none", the test isn't carrying its weight.

**Never use `markTestIncomplete()` or `markTestSkipped()` to bypass a failing
test when the task is to write or fix tests.** A red test that documents a
real gap is more valuable than a green-ish "incomplete" marker that hides it.
If the production code path the test targets is genuinely broken, fix the
production code first or narrow the assertion to what *is* in the plan's
scope. The only legitimate uses of skip/incomplete are external preconditions
outside the test author's control (a missing extension, an unavailable
third-party service, a known-flaky upstream not yet addressable) — and those
must be paired with a one-line `// TODO(plan-or-issue-ref):` pointer to the
unblocking work.

### Exception tests: assert full messages, not fragments

When testing exception messages, use `expectExceptionMessage()` with the full
expected text — not `expectExceptionMessageMatches()` with regex fragments.
Full-message assertions catch accidental message changes and are easier to read.

Wrong:

```php
$this->expectExceptionMessageMatches('/"url"/');
```

Right:

```php
$this->expectExceptionMessage('Nexus link at index 0 has missing or empty "url"');
```

If the message contains dynamic parts that genuinely vary per invocation
(timestamps, UUIDs), `assertStringContainsString` on the caught `$e` is
acceptable — but for static messages, always assert the full string.

## Nexus Subsystem (`src/Nexus/`)

Handler-side Nexus RPC library: data model, service routing, middleware, serialization
contract. Sits under `Temporal\Nexus\` and is wired into the Temporal worker through
`src/Internal/Nexus/` and the `InvokeNexusOperation` / `CancelNexusOperation` routes.

### Service / impl shape

Services follow the same `implements`-based shape as Workflows and Activities:

- A `#[Service]`-annotated **interface** declares operations as plain method
  signatures. Sync operations carry `#[Operation]`; async operations carry
  `#[AsyncOperation(output: '<wire-type>')]` and return a `WorkflowHandle`
  (SDK-managed workflow run) or — for full manual control — an
  `OperationHandlerInterface` implementation.
- An **impl class** `implements` that interface — PHP's signature covariance
  guarantees the operation methods match the contract. Worker registration
  walks `getInterfaces()` to discover the contract; mirror in
  `tests/Acceptance/App/RuntimeBuilder.php`.
- Cancel is wired automatically. A `WorkflowHandle`-backed async op is cancelled
  by the SDK (it decodes the operation token and cancels the backing workflow);
  a manual `OperationHandlerInterface` carries its own `cancel()`. `ServiceHandler`
  rejects cancel requests for sync ops with `ErrorType::NotImplemented`. There is
  no `#[OperationCancel]` attribute.
- Inside an impl method body, reach the surrounding dispatch state via the
  static `Nexus::` accessors:
  - `Nexus::getCurrentContext()` — the active `NexusContext` dispatch state
  - `Nexus::getCurrentOperationContext()` — handler-side `OperationContext`
    (links, headers, deadline)
  - `Nexus::getStartDetails()` / `Nexus::getCancelDetails()` — per-call details
  - `Nexus::getOperationContext()` — Temporal-side context (namespace,
    task queue, workflow client)

An `#[AsyncOperation]` method returns the `WorkflowHandle` directly:

```php
#[AsyncOperation(output: 'string')]
public function hello(string $input): WorkflowHandle {
    return WorkflowHandle::fromWorkflowMethod(MyWorkflow::class, $options, $input);
}
```

The previous shapes (`#[ServiceImpl]`, `#[OperationImpl]`, `#[OperationCancel]`,
`OperationInfo` returns, and `SynchronousOperationHandler`) are gone — those
attributes and classes have been deleted. `OperationHandlerInterface` remains as
the internal adapter contract and may also be returned from an `#[AsyncOperation]`
method for full manual start+cancel control.

### Conventions

- `#[Attribute]` over annotations; backed `enum`s for wire-level values
- `readonly` properties + named arguments instead of builders
- Wire values (enum string values, header names) **must match the Nexus spec verbatim** —
  they are the protocol contract, not an implementation detail

### Validation policy

- Service / operation names and operation tokens are validated by
  `src/Nexus/Validation/*Validator.php` — call `::assert()` from any new constructor that
  accepts these values
- Validators throw `Temporal\Nexus\Exception\InvalidArgumentException` on failure
- `LinkParser` and other strict parsers throw `HandlerException{BadRequest}` on malformed
  input — never silently drop entries

### Wire-shape canonicalization

Every spec-defined Failure shape has a single canonical builder/reader pair in
`src/Nexus/Exception/`:

- `OperationErrorFailure::from()` / `::isOperationError()` / `::readState()`
- `HandlerErrorFailure::from()` / `::isHandlerError()` / `::readErrorType()` /
  `::readRetryableOverride()`

Transport glue (worker, RoadRunner adapter) must use these — do **not** hand-roll the JSON
shape elsewhere.

### Test conventions (Nexus only)

- Every test class declares `#[CoversClass(Subject::class)]`; classes touched by the test
  declare `#[UsesClass(Other::class)]` for each one
- Prefer `$this->expectException(...)` over `try/catch + self::fail()`
- New code keeps the covered lines at 100% (or land an explicit waiver in the PR)

## Key Dependencies

- **gRPC**: `grpc/grpc` — client-server communication
- **Protobuf**: `google/protobuf` — message serialization
- **RoadRunner**: `spiral/roadrunner` — PHP worker process manager (required for workers)
- **Spiral Attributes**: `spiral/attributes` — attribute reflection
- **PHPUnit**: `phpunit/phpunit` v10.5 — testing
- **Psalm**: `vimeo/psalm` — static analysis (level 2)

Optional extensions: `ext-grpc`, `ext-protobuf` (recommended for production performance).

## Git Workflow

- Main branch: `master`
- PRs target `master`

<!-- rtk-instructions v2 -->
# RTK (Rust Token Killer) - Token-Optimized Commands

## Golden Rule

**Always prefix commands with `rtk`**. If RTK has a dedicated filter, it uses it. If not, it passes through unchanged. This means RTK is always safe to use.

**Important**: Even in command chains with `&&`, use `rtk`:
```bash
# ❌ Wrong
git add . && git commit -m "msg" && git push

# ✅ Correct
rtk git add . && rtk git commit -m "msg" && rtk git push
```

## RTK Commands by Workflow

### Build & Compile (80-90% savings)
```bash
rtk cargo build         # Cargo build output
rtk cargo check         # Cargo check output
rtk cargo clippy        # Clippy warnings grouped by file (80%)
rtk tsc                 # TypeScript errors grouped by file/code (83%)
rtk lint                # ESLint/Biome violations grouped (84%)
rtk prettier --check    # Files needing format only (70%)
rtk next build          # Next.js build with route metrics (87%)
```

### Test (60-99% savings)
```bash
rtk cargo test          # Cargo test failures only (90%)
rtk go test             # Go test failures only (90%)
rtk jest                # Jest failures only (99.5%)
rtk vitest              # Vitest failures only (99.5%)
rtk playwright test     # Playwright failures only (94%)
rtk pytest              # Python test failures only (90%)
rtk rake test           # Ruby test failures only (90%)
rtk rspec               # RSpec test failures only (60%)
rtk test <cmd>          # Generic test wrapper - failures only
```

### Git (59-80% savings)
```bash
rtk git status          # Compact status
rtk git log             # Compact log (works with all git flags)
rtk git diff            # Compact diff (80%)
rtk git show            # Compact show (80%)
rtk git add             # Ultra-compact confirmations (59%)
rtk git commit          # Ultra-compact confirmations (59%)
rtk git push            # Ultra-compact confirmations
rtk git pull            # Ultra-compact confirmations
rtk git branch          # Compact branch list
rtk git fetch           # Compact fetch
rtk git stash           # Compact stash
rtk git worktree        # Compact worktree
```

Note: Git passthrough works for ALL subcommands, even those not explicitly listed.

### GitHub (26-87% savings)
```bash
rtk gh pr view <num>    # Compact PR view (87%)
rtk gh pr checks        # Compact PR checks (79%)
rtk gh run list         # Compact workflow runs (82%)
rtk gh issue list       # Compact issue list (80%)
rtk gh api              # Compact API responses (26%)
```

### JavaScript/TypeScript Tooling (70-90% savings)
```bash
rtk pnpm list           # Compact dependency tree (70%)
rtk pnpm outdated       # Compact outdated packages (80%)
rtk pnpm install        # Compact install output (90%)
rtk npm run <script>    # Compact npm script output
rtk npx <cmd>           # Compact npx command output
rtk prisma              # Prisma without ASCII art (88%)
```

### Files & Search (60-75% savings)
```bash
rtk ls <path>           # Tree format, compact (65%)
rtk read <file>         # Code reading with filtering (60%)
rtk grep <pattern>      # Search grouped by file (75%)
rtk find <pattern>      # Find grouped by directory (70%)
```

### Analysis & Debug (70-90% savings)
```bash
rtk err <cmd>           # Filter errors only from any command
rtk log <file>          # Deduplicated logs with counts
rtk json <file>         # JSON structure without values
rtk deps                # Dependency overview
rtk env                 # Environment variables compact
rtk summary <cmd>       # Smart summary of command output
rtk diff                # Ultra-compact diffs
```

### Infrastructure (85% savings)
```bash
rtk docker ps           # Compact container list
rtk docker images       # Compact image list
rtk docker logs <c>     # Deduplicated logs
rtk kubectl get         # Compact resource list
rtk kubectl logs        # Deduplicated pod logs
```

### Network (65-70% savings)
```bash
rtk curl <url>          # Compact HTTP responses (70%)
rtk wget <url>          # Compact download output (65%)
```

### Meta Commands
```bash
rtk gain                # View token savings statistics
rtk gain --history      # View command history with savings
rtk discover            # Analyze Claude Code sessions for missed RTK usage
rtk proxy <cmd>         # Run command without filtering (for debugging)
rtk init                # Add RTK instructions to CLAUDE.md
rtk init --global       # Add RTK to ~/.claude/CLAUDE.md
```

## Token Savings Overview

| Category | Commands | Typical Savings |
|----------|----------|-----------------|
| Tests | vitest, playwright, cargo test | 90-99% |
| Build | next, tsc, lint, prettier | 70-87% |
| Git | status, log, diff, add, commit | 59-80% |
| GitHub | gh pr, gh run, gh issue | 26-87% |
| Package Managers | pnpm, npm, npx | 70-90% |
| Files | ls, read, grep, find | 60-75% |
| Infrastructure | docker, kubectl | 85% |
| Network | curl, wget | 65-70% |

Overall average: **60-90% token reduction** on common development operations.
<!-- /rtk-instructions -->
