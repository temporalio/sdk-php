# Plugin System Implementation Review

## Overview

This document is a code review of the PHP SDK plugin system implementation (per [temporalio/features#652](https://github.com/temporalio/features/issues/652)), compared against the Java SDK (v1.33.0, PR #2761) and TypeScript SDK (PR #1794) reference implementations.

---

## Architecture Comparison

### Java: 4 interfaces, 14 methods

```
WorkflowServiceStubsPlugin (3 methods)
├── configureServiceStubs(Builder)
├── connectServiceClient(options, next)      ← chain-of-responsibility
└── getName()

WorkflowClientPlugin (2 methods)
├── configureWorkflowClient(Builder)
└── getName()

ScheduleClientPlugin (2 methods)
├── configureScheduleClient(Builder)
└── getName()

WorkerPlugin (9 methods)
├── configureWorkerFactory(Builder)
├── configureWorker(taskQueue, Builder)
├── initializeWorker(taskQueue, worker)
├── startWorker(taskQueue, worker, next)              ← chain-of-responsibility
├── startWorkerFactory(factory, next)                 ← chain-of-responsibility
├── shutdownWorker(taskQueue, worker, next)            ← chain-of-responsibility
├── shutdownWorkerFactory(factory, next)               ← chain-of-responsibility
├── replayWorkflowExecution(worker, history, next)     ← chain-of-responsibility
└── getName()
```

### TypeScript: 5 interfaces

```
ConnectionPlugin
├── configureConnection(options): options

NativeConnectionPlugin
├── configureNativeConnection(options): options

ClientPlugin
├── configureClient(options): options

WorkerPlugin
├── configureWorker(options): options
├── configureReplayWorker(options): options
├── runWorker(worker, next)                            ← chain-of-responsibility
└── name

BundlerPlugin
├── configureBundler(options): options
```

### PHP (this PR): 3 interfaces, 8 methods

```
ClientPluginInterface (2 methods)
├── configureClient(ClientPluginContext)
└── getName()

ScheduleClientPluginInterface (2 methods)
├── configureScheduleClient(ScheduleClientPluginContext)
└── getName()

WorkerPluginInterface (4 methods)
├── configureWorkerFactory(WorkerFactoryPluginContext)
├── configureWorker(WorkerPluginContext)
├── initializeWorker(WorkerInterface)
└── getName()
```

---

## Feature Matrix

| Feature | Java | TypeScript | PHP | Status |
|---------|------|------------|-----|--------|
| `configureServiceStubs` / connection config | `configureServiceStubs(Builder)` | `configureConnection(options)` | — | Missing |
| `connectServiceClient` (chain) | `connectServiceClient(options, next)` | — | — | Missing |
| `configureClient` | `configureWorkflowClient(Builder)` | `configureClient(options)` | `configureClient(Context)` | OK |
| `configureScheduleClient` | `configureScheduleClient(Builder)` | — | `configureScheduleClient(Context)` | OK |
| `configureWorkerFactory` | `configureWorkerFactory(Builder)` | — | `configureWorkerFactory(Context)` | OK |
| `configureWorker` | `configureWorker(taskQueue, Builder)` | `configureWorker(options)` | `configureWorker(Context)` | OK |
| `initializeWorker` | `initializeWorker(taskQueue, worker)` | — | `initializeWorker(worker)` | Minor: no taskQueue param |
| `startWorker` (chain) | `startWorker(tq, worker, next)` | `runWorker(worker, next)` | — | Missing |
| `startWorkerFactory` (chain) | `startWorkerFactory(factory, next)` | — | — | Missing |
| `shutdownWorker` (chain) | `shutdownWorker(tq, worker, next)` | — | — | Missing |
| `shutdownWorkerFactory` (chain) | `shutdownWorkerFactory(factory, next)` | — | — | Missing |
| `replayWorkflowExecution` (chain) | `replayWorkflowExecution(w, h, next)` | `configureReplayWorker(options)` | — | Missing |
| Plugin names sent to Core | Yes | Yes | No | Missing |
| Auto-propagation (instanceof) | Yes | Yes (concat) | Explicit `$client` param | Partial |
| Duplicate handling | Warning + skip | — | RuntimeException | Problematic |
| SimplePlugin builder | 14+ declarative options | `SimplePlugin` class | Empty `AbstractPlugin` | Partial |
| `@Experimental` annotation | Yes | Yes | No | Missing |
| Interceptors always merge | Yes (Builder append) | Yes (append) | Lost with custom PipelineProvider | Bug |
| Schedule client interceptors | Via Builder | — | Context has no interceptor support | Missing |

---

## Critical Issues (P0)

### 1. No lifecycle hooks (start/shutdown worker + factory)

Java provides 4 chain-of-responsibility methods for lifecycle management:

```java
void startWorker(String taskQueue, Worker worker, BiConsumer<String, Worker> next);
void startWorkerFactory(WorkerFactory factory, Consumer<WorkerFactory> next);
void shutdownWorker(String taskQueue, Worker worker, BiConsumer<String, Worker> next);
void shutdownWorkerFactory(WorkerFactory factory, Consumer<WorkerFactory> next);
```

TypeScript provides `runWorker(worker, next)` which wraps the entire worker lifecycle.

PHP has none of these. While the PHP SDK uses a tick-based model via RoadRunner, plugins cannot:
- Execute setup before worker starts polling
- Manage resources (connection pools, caches)
- Ensure graceful shutdown (flush OTel spans, close connections)
- Wrap the worker lifecycle for observability

**Recommendation:** Add at minimum `beforeRun`/`afterRun` hooks or event-based hooks tied to `WorkerFactory::run()` lifecycle.

### 2. CompositePipelineProvider silently drops base interceptors

In `src/Plugin/CompositePipelineProvider.php`, when the base provider is not a `SimplePipelineProvider`:

```php
// Use only plugin interceptors - the base pipeline is lost in this edge case.
// Users should either use plugins OR a custom PipelineProvider, not both.
return $this->cache[$interceptorClass] = Pipeline::prepare($filtered);
```

Java and TypeScript always merge interceptors (append via Builder). PHP silently discards the user's interceptors when a custom `PipelineProvider` is used alongside plugins.

**Recommendation:** At minimum throw a `\LogicException` instead of silently losing interceptors. Ideally find a way to merge both sources.

### 3. PluginRegistry::merge() throws on duplicates

PHP throws `RuntimeException` on duplicate plugin names during merge:

```php
throw new \RuntimeException(\sprintf(
    'Duplicate plugin "%s": a plugin with this name is already registered.',
    $name,
));
```

Java uses warning + skip via `PluginUtils.mergePlugins()`. This is important because a single plugin implementing both `ClientPluginInterface` and `WorkerPluginInterface` will naturally appear in both propagation paths.

**Recommendation:** On merge, skip duplicates with a warning (like Java) or check object identity (`===`) rather than name equality.

---

## Serious Issues (P1)

### 4. No `connectServiceClient` / connection-level hook

Java has `WorkflowServiceStubsPlugin` with `configureServiceStubs(Builder)` and `connectServiceClient(options, next)`. TypeScript has `ConnectionPlugin` / `NativeConnectionPlugin`.

PHP has nothing at the connection level. Plugins cannot influence TLS settings, API keys, gRPC metadata, or channel options.

**Impact:** A Temporal Cloud plugin (configuring mTLS, API key, endpoint) cannot be built using the PHP plugin system. Users must configure the connection manually.

**Recommendation:** Add a `ConnectionPluginInterface` or allow plugins to configure `ServiceClient` options.

### 5. No replay plugin hook

Java has `replayWorkflowExecution(worker, history, next)` as chain-of-responsibility. TypeScript has `configureReplayWorker(options)`.

PHP has no replay-related hooks.

### 6. Code duplication: WorkerFactory and Testing\WorkerFactory

`src/WorkerFactory.php::newWorker()` and `testing/src/WorkerFactory.php::newWorker()` contain nearly identical plugin handling code. Any change to plugin logic requires synchronized changes in both files.

**Recommendation:** Extract shared logic into a protected method in base `WorkerFactory` that the testing version overrides only for Worker vs WorkerMock creation.

---

## Moderate Issues (P2)

### 7. `initializeWorker` missing `taskQueue` parameter

Java: `initializeWorker(String taskQueue, Worker worker)` — task queue is explicit.
PHP: `initializeWorker(WorkerInterface $worker)` — task queue available only via `$worker->getID()`.

Minor inconsistency. While `$worker->getID()` works, having `taskQueue` as an explicit parameter is more discoverable and consistent with the Java API.

### 8. ScheduleClientPluginContext has no interceptor support

`ClientPluginContext` has `addInterceptor()` / `getInterceptors()`, but `ScheduleClientPluginContext` does not. Plugins cannot add schedule-specific interceptors.

### 9. Plugin names not sent to Core

Java and TypeScript send plugin names to the Temporal Core bridge for observability/metrics. PHP collects names via `getName()` but only uses them for deduplication.

**Recommendation:** Pass plugin names to the bridge when creating workers.

### 10. No `@experimental` marking

Java and TypeScript mark all plugin APIs as `@Experimental` / `@experimental`. PHP has no such annotation, which may give users a false sense of API stability.

---

## Minor Issues (P3)

### 11. No declarative SimplePlugin builder

Java's `SimplePlugin` provides a builder with 14+ options:

```java
SimplePlugin.newBuilder("my-plugin")
    .addWorkerInterceptors(new TracingInterceptor())
    .addClientInterceptors(new LoggingInterceptor())
    .customizeDataConverter(existing -> new CodecDataConverter(existing, codec))
    .registerWorkflowImplementationTypes(MyWorkflow.class)
    .registerActivitiesImplementations(new MyActivityImpl())
    .onWorkerStart((tq, w) -> logger.info("Started: {}", tq))
    .onWorkerShutdown((tq, w) -> logger.info("Stopped: {}", tq))
    .build();
```

PHP's `AbstractPlugin` is an empty base class with no-op traits. For common use cases (interceptor + data converter + activity registration), users must write full method overrides.

### 12. Nullable DataConverter semantics in context

`ClientPluginContext` accepts `null` as DataConverter (meaning "don't change"). If a plugin calls `$context->setDataConverter(null)`, it cannot reset the converter — null means "no change". This is subtle and not documented.

---

## What Works Well

1. Clean separation into interfaces + traits with no-op defaults
2. `PluginRegistry` with deduplication by name
3. Plugin propagation from client to worker factory
4. `CompositePipelineProvider` for merging interceptors (with `SimplePipelineProvider`)
5. `initializeWorker` hook for post-creation registration — a good addition not present in all SDKs
6. Backward-compatible constructor signatures (optional `$plugins` parameter)
7. Mutable context objects (builder pattern) — idiomatic for PHP

---

## Configuration Approach Comparison

| Aspect | Java | TypeScript | PHP |
|--------|------|------------|-----|
| Config mutation | Builder objects | Return new options (immutable) | Mutable context objects |
| Validation | `build()` validates | Runtime | None (no validation step) |
| Interceptor merge | Always append via Builder | Always append | Append with SimplePipelineProvider only |
| Plugin propagation | Automatic via `instanceof` | Automatic via array concat | Explicit via `$client` parameter |
| Duplicate policy | Warn + skip | No dedup | Throw exception |

---

## Execution Order Comparison

| Phase | Java | TypeScript | PHP |
|-------|------|------------|-----|
| Configuration (`configure*`) | Forward order, no chain | Forward order, sequential fold | Forward order, no chain |
| Initialization (`initializeWorker`) | Forward order | — | Forward order |
| Start (`startWorker`) | Reverse order, chain-of-responsibility | Reverse order (`runWorker`) | — |
| Shutdown (`shutdownWorker`) | Reverse order, chain-of-responsibility | — | — |
| Connection (`connectServiceClient`) | Reverse order, chain-of-responsibility | — | — |
| Replay | Reverse order, chain-of-responsibility | — | — |

---

## Summary: Priority Action Items

| Priority | Issue | Fix |
|----------|-------|-----|
| P0 | No lifecycle hooks (start/shutdown) | Add chain-of-responsibility start/shutdown hooks |
| P0 | Interceptors silently lost with custom PipelineProvider | Throw or merge, never silently drop |
| P0 | Duplicate plugins cause RuntimeException on merge | Warn + skip (like Java) |
| P1 | No connection-level plugin hook | Add ConnectionPluginInterface |
| P1 | No replay plugin hook | Add replayWorkflowExecution hook |
| P1 | Code duplication in WorkerFactory / Testing\WorkerFactory | Extract shared logic to protected method |
| P2 | `initializeWorker` missing `taskQueue` parameter | Add `taskQueue` as first parameter |
| P2 | ScheduleClientPluginContext without interceptor support | Add interceptor methods |
| P2 | Plugin names not sent to Core | Pass names to bridge |
| P2 | No `@experimental` marking | Add annotation to all plugin APIs |
| P3 | No SimplePlugin builder | Add declarative builder class |
| P3 | Nullable DataConverter semantics undocumented | Document null = "no change" convention |
