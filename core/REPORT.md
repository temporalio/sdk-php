# Temporal PHP SDK without RoadRunner: report

## 1. Why the SDK needs RoadRunner today

The PHP SDK only runs user code. Everything else in a worker is done by the Go process `rr` with the `roadrunner-temporal` plugin and sdk-go:

| responsibility | where it is today |
|---|---|
| gRPC long polls to the server (many concurrent polls per queue) | sdk-go in `rr` |
| workflow task state machines: history → events → commands, replay, non-determinism checks, sticky cache, markers (`Version`, `SideEffect`, `LocalActivity`) | sdk-go (`internal_command_state_machine.go`, `internal_event_handlers.go`) |
| translation sdk-go callbacks ↔ PHP messages (`StartWorkflow`, `InvokeSignal`, `ExecuteActivity`, `NewTimer`, …) | `roadrunner-temporal/aggregatedpool/*.go` |
| process pool: 1 workflow PHP process + N activity PHP processes, pipes (goridge), restarts | RoadRunner pool |
| heartbeat throttling, activity cancellation, local activity retries, graceful shutdown | sdk-go |

PHP is single-threaded and has no async runtime in the core language. A PHP process that blocks in one gRPC long poll cannot do anything else. That is the reason for the Go host process.

## 2. Options that were evaluated

| option | result |
|---|---|
| **A. sdk-core (Rust) in-process via PHP FFI** — the engine that the TypeScript, Python, .NET and Ruby SDKs use | **Implemented. Works. Faster than RR (see §4).** |
| B. Pure PHP, ext-grpc (blocking) | Activities only: works (`bench/experiments/pure-php`). A process blocks in each poll, so N activities need N processes. |
| C. Pure PHP, non-blocking gRPC (`thesis/grpc-client` on amphp/Revolt + Fibers) | Activities only: works, 1 process with 8 polls = 4 blocking processes, 1/3 of the memory. Same CPU per activity as sdk-core. |
| D. Pure PHP workflow worker | Not viable as a short project: it re-implements about 15 000 lines of sdk-core state machines (estimate 3–6 person-months + replay test suite). |
| E. Fibers + event loop on top of option A | **Implemented** for activities: one PHP process runs hundreds of I/O-bound activities at the same time. |

Direct blocking gRPC calls from the PHP worker (option B for workflows) would make PHP block in the poll and would still need the state machines of option D.
Option A keeps all I/O in Rust threads. PHP blocks only when it has nothing to do, exactly like it blocks on the goridge pipe today.

## 3. What was built

```
            PHP process (one per role, forked by CoreWorkerFactory::run())
 ┌─────────────────────────────────────────────────────────────────────┐
 │ user workflows/activities                                            │
 │ PHP SDK internals (unchanged): Router, WorkflowProcess, Client, …    │
 │ src/Worker/Core: WorkflowActivations / ActivityTasks  (adapter)      │
 │ src/Worker/Core/Bridge (FFI)                                        │
 ├──────────────────────── FFI (C ABI, no callbacks) ──────────────────┤
 │ core/bridge (Rust cdylib): tokio runtime + completion queue          │
 │ sdk-core Worker: pollers, state machines, sticky cache, heartbeats   │
 └─────────────────────────────────────────────────────────────────────┘
                              │ gRPC
                        Temporal server
```

- **Rust bridge** (`core/bridge`, ~500 lines). Async operations (`poll_workflow_activation`, `poll_activity_task`, `complete_*`) are spawned on tokio. Results go to a mutex+condvar queue. PHP drains up to 256 events per FFI call with `tpb_next_events(timeout)`. No FFI callback ever runs on a tokio thread, which is what makes it safe for PHP. A pipe fd (`tpb_event_fd`) signals new events to an event loop. Successful completions produce no event (fewer wake-ups).
- **Adapter** (`src/Worker/Core/WorkflowActivations.php`, `ActivityTasks.php`). It converts sdk-core activation jobs into the same `ServerRequest`/`ServerResponse` objects that RoadRunner produced (`StartWorkflow`, `InvokeSignal`, responses by request id, …). So the whole PHP workflow machinery is reused without change. Outgoing PHP commands become sdk-core `WorkflowCommand`s. Commands that RR answered in the same workflow task (`GetVersion`, `SideEffect`, `Cancel`) are answered by the adapter in a loop inside one activation.
- **CoreWorkerFactory** (`src/Worker/Core/CoreWorkerFactory.php`) extends `WorkerFactory`. `run()` forks role processes (workflow / activity), each with its own sdk-core worker per task queue. It replaces the RoadRunner pool and `.rr.yaml`. Children exit through `_exit()` because ext-grpc hangs in its module shutdown after `fork()`.
- **Fibers + Revolt** (`TEMPORAL_CORE_ACTIVITY_CONCURRENCY`): an activity process runs each activity task in its own Fiber on the Revolt loop, driven by the bridge pipe fd. The Facade context became fiber-local for isolated fibers (`Facade::isolateFiber()`), otherwise concurrent activities overwrite each other's `Activity::getCurrentContext()`.
- **Debug labels**: `TEMPORAL_CORE_PROFILE=1` prints per process: process CPU (PHP + Rust threads), time waiting for events, time in the PHP SDK dispatch, time per workflow activation and per activity task.

## 4. Benchmarks

RESULTS_PLACEHOLDER

## 5. Where the time goes (debug labels, 1000 wf × 1 activity at 100 wf/s)

PROFILE_PLACEHOLDER

## 6. Limitations and differences to RoadRunner

LIMITS_PLACEHOLDER

## 7. Next steps

NEXT_PLACEHOLDER
