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
| B. Pure PHP, ext-grpc (blocking) | Activities only: works. A process blocks in each poll, so N activities need N processes. CPU per 1000 activities 0.63–0.65 s (sdk-core: 0.45–0.51 s). |
| C. Pure PHP, non-blocking gRPC (`thesis/grpc-client` 0.3.3 on amphp/Revolt + Fibers, `google/protobuf` encoder) | Activities only: works, 1 process with 8 polls = 4 blocking processes, 52 MB vs 170 MB RSS. CPU per 1000 activities 0.46–0.65 s, the same order as sdk-core. The server rejects a long poll without a deadline, so both clients send a 70 s gRPC timeout. |
| D. Pure PHP workflow worker | Not viable as a short project: it re-implements about 15 000 lines of sdk-core state machines (estimate 3–6 person-months + replay test suite). |
| E. Fibers + event loop on top of option A | **Implemented** for activities: one PHP process runs hundreds of I/O-bound activities at the same time. |

Direct blocking gRPC calls from the PHP worker (option B for workflows) would make PHP block in the poll and would still need the state machines of option D.
Option A keeps all I/O in Rust threads. PHP blocks only when it has nothing to do, exactly like it blocks on the goridge pipe today.

## 3. What was built

```
            PHP process (one per role, started by CoreWorkerFactory::run())
 ┌─────────────────────────────────────────────────────────────────────┐
 │ user workflows/activities                                            │
 │ PHP SDK internals (unchanged): Router, WorkflowProcess, Client, …    │
 │ src/Worker/Core: WorkflowActivations / ActivityTasks  (adapter)      │
 │ src/Internal/Bridge: Bridge (FFI), CoreEnvironment, BridgeConnection │
 ├──────────────────────── FFI (C ABI, no callbacks) ──────────────────┤
 │ core/bridge (Rust cdylib): tokio runtime + completion queue          │
 │ sdk-core Worker: pollers, state machines, sticky cache, heartbeats   │
 └─────────────────────────────────────────────────────────────────────┘
                              │ gRPC
                        Temporal server
```

- **Rust bridge** (`core/bridge`, ~1500 lines, modules `ffi`, `queue`, `runtime`, `config`, `worker`, `replay`, `client`). Async operations (`poll_workflow_activation`, `poll_activity_task`, `complete_*`) are spawned on tokio. Results go to a mutex+condvar queue. PHP drains up to 256 events per FFI call with `tpb_next_events(timeout)`. No FFI callback ever runs on a tokio thread, which is what makes it safe for PHP. A pipe fd (`tpb_event_fd`) signals new events to an event loop. Successful completions produce no event (fewer wake-ups). The PHP side is `Temporal\Internal\Bridge\Bridge` (one per process, shared by the worker and the gRPC client). `CoreEnvironment` reads the `TEMPORAL_CORE_*` variables, `BridgeConnection` builds the connection and TLS options for the worker and the client.
- **Adapter** (`src/Worker/Core/WorkflowActivations.php` with `CommandTranslator`, `ResolutionMapper`, `PatchVersions`, `LocalActivityRetries`, `InfoFactory`, `PayloadMapper`, `ProtoTime`; and `ActivityTasks.php`). It converts sdk-core activation jobs into the same `ServerRequest`/`ServerResponse` objects that RoadRunner produced (`StartWorkflow`, `InvokeSignal`, responses by request id, …). So the whole PHP workflow machinery is reused without change. Outgoing PHP commands become sdk-core `WorkflowCommand`s. Commands that RR answered in the same workflow task (`GetVersion`, `SideEffect`, `Cancel`) are answered by the adapter in a loop inside one activation.
- **CoreWorkerFactory** (`src/Worker/Core/CoreWorkerFactory.php`) extends `WorkerFactory` and wires `CoreOptions` (arguments and env), `CoreWorkerConfig` (the bridge JSON), `Supervisor` + `ChildProcesses`, `CoreWorkerLoop` (one process) and `CoreReplayer`. `run()` is a supervisor: it starts one fresh `php` process per role (workflow / activity) with the same script, arguments and changed ini settings (`TEMPORAL_CORE_ROLE` env). A child that exits in the first second of the initial start stops all children. A child that crashes later is restarted with a delay of 1, 2, 4 … 30 s; the delay resets when the child runs for 60 s. SIGTERM/SIGINT stop all children; a child that does not stop in `stopTimeoutSeconds` gets SIGKILL. The exit code of `run()` is the highest exit code of the children (128 + signal for a signal). On shutdown the bridge waits up to 15 s in total for the sdk-core workers to finalize. Each child has its own sdk-core worker per task queue. It replaces the RoadRunner pool and `.rr.yaml`. On Linux without ext-grpc it forks the children instead (shared memory, E14); ext-grpc hangs after `fork()`, and on macOS TLS system roots crash a forked child, so these cases start fresh processes.
- **Fibers + Revolt** (`TEMPORAL_CORE_ACTIVITY_CONCURRENCY`): an activity process runs each activity task in its own Fiber on the Revolt loop, driven by the bridge pipe fd. The Facade context became fiber-local for isolated fibers (`Facade::isolateFiber()`), otherwise concurrent activities overwrite each other's `Activity::getCurrentContext()`.
- **Debug labels**: `TEMPORAL_CORE_PROFILE=1` prints per process: process CPU (PHP + Rust threads), time waiting for events, time in the PHP SDK dispatch, time per workflow activation and per activity task.

## 4. Benchmarks

Final code on sdk-core 0.9.0 (crates.io), gzip on the worker's gRPC calls (the sdk-core default; `TEMPORAL_CORE_GRPC_COMPRESSION=none` is 7–15 % cheaper, E17).
Setup: Apple M5 Max (18 cores), PHP 8.5.6 NTS, opcache on, JIT/xdebug/pcov off. Temporal 1.29.3 + PostgreSQL in Docker (`bench/server`, 1 task-queue partition, recreated before the run). RoadRunner 2025.1.15.
Both transports: 1 workflow process + 4 activity processes unless the label says otherwise. Mean of 2 runs per row, rr and core interleaved in each repetition, 0 failed workflows. Harness: `bench/matrix.sh`, table: `bench/summary.sh`.
"worker CPU s" = CPU time of all worker processes (rr + PHP, or the PHP supervisor + children incl. Rust threads) during the measured run.

**Fixed rate (server not saturated): worker cost**

| scenario | transport | p50 ms | p99 ms | worker CPU s |
|---|---|---|---|---|
| 1000 wf × 1 activity @ 50/s | rr | 13 | 25 | 3.75 |
| | **core** | 13 | 21 | **2.05 (−45 %)** |
| 1000 wf × 1 activity @ 100/s | rr | 13 | 48 | 3.28 |
| | **core** | 13 | 34 | **1.76 (−46 %)** |
| 1000 wf, no activities @ 100/s | rr | 6 | 19 | 1.61 |
| | **core** | 5 | 14 | **0.85 (−47 %)** |

**Full speed**

| scenario | transport | wf/s | act/s | p50 ms | p99 ms | worker CPU s |
|---|---|---|---|---|---|---|
| seq 1000 × 1 | rr | 265.9 | 266 | 1806 | 2935 | 2.44 |
| | core | **286.4 (+8 %)** | 286 | 1717 | 1939 | **1.56** |
| par 100 × 20 | rr | 23.2 | 464 | 2268 | 4188 | 1.96 |
| | core | 22.6 (−3 %) | 451 | 2370 | 4256 | **1.18** |
| | core, 1 activity process, 200 Fibers | **42.9** | **857** | 1297 | 2210 | 0.85 |
| seq 200 × 10 | rr | 48.4 | 484 | 1964 | 3703 | 2.95 |
| | core | **54.8 (+13 %)** | 548 | 3391 | **3470** | **1.91** |
| noact 2000 | rr | 378.3 | – | 2486 | 3864 | 2.05 |
| | core | **540.7 (+43 %)** | – | 1290 | 1601 | **1.09** |
| | core, 4 workflow processes | 611.7 | – | 31 | 75 | 1.43 |

**Where RoadRunner cannot scale**

| scenario | transport | wf/s | act/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|---|
| I/O: 100 wf × 10 parallel activities of 100 ms | rr (4 activity processes) | 3.9 | 39 | 12910 | 25484 | 2.09 | 391 |
| | **core, 1 activity process, 200 Fibers** | **88.8** | **887 (×23)** | **724** | **993** | 0.53 | 209 |
| CPU-bound workflow code (5 ms per activation) | rr (1 workflow process, fixed) | 90.8 | 91 | 2365 | 2906 | 3.89 | 364 |
| | **core, 4 workflow processes** | **269.1 (×3.0)** | 269 | **527** | **581** | 3.55 | 538 |

Conclusions:
- Worker CPU is **45–47 % lower** than RoadRunner at the same throughput and latency (fixed rate), and 35–47 % lower at full speed.
- Throughput is higher or equal: +8 % `seq 1000×1`, +13 % `seq 200×10`, +43 % `noact` (8 workflow task pollers, E7), −3 % `par 100×20` (within run-to-run noise); p99 is lower in every scenario except `par 100×20`.
- `seq 200×10`: the p50 is higher for core, the p99 and the total time are lower — core interleaves all 200 workflows, RoadRunner finishes early ones first.
- Real memory: 11 % lower with fresh child processes (E5), 35 % lower (Linux PSS) with forked children without ext-grpc (E14).
- Several workflow processes (×3.0 on CPU-bound workflow code) and Fibers (×23 on I/O-bound activities) are not possible with RoadRunner.

## 5. Where the time goes (debug labels, 1000 wf × 1 activity at 100 wf/s)

`TEMPORAL_CORE_PROFILE=1`, Docker server, `seq` 1000 × 1 activity at 100 wf/s, 1 workflow + 4 activity processes:

| process | process CPU (PHP + Rust) | PHP SDK dispatch | adapter + protobuf (activation − dispatch) | Rust / sdk-core (rest) |
|---|---|---|---|---|
| workflow (3000 activations) | 1468 ms | 394 ms (131 µs / activation) | 174 ms (58 µs / activation) | ~900 ms (~300 µs / activation) |
| activity (250 tasks each) | 145 ms | 24 ms (96 µs / task) | 18 ms (70 µs / task) | ~100 ms |

- The PHP adapter costs 15–20 % of the PHP busy time. The rest of the PHP time is the SDK itself (the same code runs under RoadRunner).
- An xdebug profile of the workflow process shows the SDK hot spots, which are the same with RoadRunner: `Carbon\CarbonInterval` (duration parsing in the marshaller, ~20 % of the busy PHP time), promises, attribute reading.
- sdk-core (history processing, gRPC, protobuf) is the largest part of the process CPU, but it replaces the whole Go process: the total worker CPU is 46 % lower than `rr` + PHP in this scenario (§4).
- Tried and not helpful: fewer tokio threads (`TEMPORAL_CORE_THREADS=2`), no completion acknowledgements (kept: fewer wake-ups, no measurable CPU change).
- Found and fixed: task-queue partitions. With the default 4 partitions and few pollers, tasks waited up to 2 s on partitions that no poller read (both transports). The benchmark server uses 1 partition.

## 6. Limitations and differences to RoadRunner

Test suites on the core transport (`TEMPORAL_WORKER_TRANSPORT=core`):

| suite | core | RoadRunner |
|---|---|---|
| Unit | all green (transport independent) | |
| Functional | 182/184 (2 skipped, same as RR) | 182/184 |
| Acceptance (full) | 159/166 | not run in this work |
| Acceptance, `TEMPORAL_CORE_MAX_CACHED_WORKFLOWS=0` (every task replays) | 154/166 | – |

The 7 Acceptance failures on core:
- 4 by design: `SideEffectTest` ×3 and `ResetWorkerTest::resetWithSignal` look for the Go `SideEffect` marker in the history. On core the value is in a `core_local_activity` marker (replay-safe, checked with the cache off).
- 3 harness only: `ClassicTest::replayDifferentVersions` (Go-recorded JSON fixtures), `WorkerRestartTest` (RR KV storage and RR restart), `TranscriptWorkflowFailureTest` (expects RR wire frames).

Differences and limits:
- **History format.** sdk-go writes `Version`, `SideEffect` and `LocalActivity` markers, sdk-core writes `core_patch` and `core_local_activity`. A workflow started on RoadRunner cannot continue on core and the other way round. Drain running workflows (or use worker versioning / a new task queue) before the switch. Workflows without markers (only activities, timers, signals, children) are compatible in principle, but this was not tested.
- **`Workflow::sideEffect()`** is a local activity that the worker completes itself: one more activation per call, one marker per call (also `uuid*()`).
- **`Workflow::getVersion()`** maps to patches with id `<changeId>-<version>` and a per-run cache.
- **Local activities** run in the workflow process, not in the activity processes.
- **`WorkflowPanicPolicy::FailWorkflow`** applies to panics in workflow code; non-determinism detected by sdk-core still fails the workflow task (sdk-go also fails the workflow for it).
- **Fibers inside an activity fiber** (`Amp\async`, event loop callbacks) see the global Activity context, not the activity's own one; call `Activity::*` from the activity fiber itself.
- **Release blocker:** `composer.json` takes `roadrunner-api-dto` from a fork branch (`dev-feature/coresdk-protos`) until the coresdk messages are released upstream; a published package cannot resolve it.
- **Fiber concurrency** helps only activities that use non-blocking I/O (Revolt/amphp). A blocking call (PDO, curl, `sleep`) stops all activities of the process. It needs `revolt/event-loop`. `Facade` context is fiber-local only for fibers started by the worker.
- **fork() after the runtime started:** the sdk-core runtime (tokio threads) cannot be used in a process that forked after it started; `Bridge::shared()` throws a `LogicException` there. `run()` itself forks only before the runtime starts.
- **gRPC client without ext-grpc:** the calls go through the bridge (`CoreStub`). A failed call returns the status code, the message and `grpc-status-details-bin`; other response headers and trailers are not returned.
- **Connection:** TLS, mTLS, server name override and API key come from the standard env config (`TEMPORAL_TLS*`, `TEMPORAL_API_KEY`, TOML profile), checked through a TLS terminator. (On macOS a forked child crashes when it loads the TLS system roots, even when the parent never used TLS, so macOS always starts fresh processes; on Linux forked children load them without problems, checked in Docker.)
- **Linux:** builds and runs in Docker (`core/docker/Dockerfile`, linux/arm64): 304–330 wf/s on `seq 200 × 1`, 752 act/s with Fibers, graceful `docker stop`.
- **Not done yet:** prebuilt binaries; `temporal.UpdateAPIKey` at run time; the RR KV caches of the testing package (the Functional harness still starts `rr serve` as a KV store only); deadlock detection (`WorkerOptions::$deadlockDetectionTimeout` has no effect, sdk-go detects it inside RoadRunner); the `TemporalChangeVersion` search attribute that sdk-go upserts for `getVersion()`.
- **CI:** the `Core transport` workflow (`.github/workflows/core.yml`) runs the Rust checks (fmt, clippy, tests, header) and, without ext-grpc, Unit on PHP 8.2 and 8.4, Functional, Functional with time skipping, and Acceptance with activity concurrency 1 and 8. Acceptance there excludes exactly the 7 tests above.

## 7. Next steps

1. Distribution: prebuilt `libtemporal_php_bridge` for linux-x64/arm64 and macOS (GitHub release assets, downloaded by `dload` like `rr` today), or a PHP extension built from the same crate.
2. Memory: done for Linux without ext-grpc (fork, E14).
3. Replace the remaining RoadRunner users: `temporal.UpdateAPIKey`, the RR KV caches of the testing package. (Replay already has a core backend: `Temporal\Testing\Replay\CoreWorkflowReplayer`.)
4. SDK hot spots that help both transports: `CarbonInterval` in the marshaller, attribute reading per workflow start.
5. Fibers for workflows (the `sdk-php-fiber-runtime` branch) together with this transport.
