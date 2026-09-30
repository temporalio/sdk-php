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
- **CoreWorkerFactory** (`src/Worker/Core/CoreWorkerFactory.php`) extends `WorkerFactory`. `run()` is a supervisor: it starts one fresh `php` process per role (workflow / activity) with the same script, arguments and changed ini settings (`TEMPORAL_CORE_ROLE` env), restarts a crashed child, stops the supervisor when a child crashes at startup, and kills children that do not stop in time. Each child has its own sdk-core worker per task queue. It replaces the RoadRunner pool and `.rr.yaml`. The first version used `fork()`: less memory, but a gRPC channel or TLS system roots created before the fork hang or crash the children, so it was replaced.
- **Fibers + Revolt** (`TEMPORAL_CORE_ACTIVITY_CONCURRENCY`): an activity process runs each activity task in its own Fiber on the Revolt loop, driven by the bridge pipe fd. The Facade context became fiber-local for isolated fibers (`Facade::isolateFiber()`), otherwise concurrent activities overwrite each other's `Activity::getCurrentContext()`.
- **Debug labels**: `TEMPORAL_CORE_PROFILE=1` prints per process: process CPU (PHP + Rust threads), time waiting for events, time in the PHP SDK dispatch, time per workflow activation and per activity task.

## 4. Benchmarks

Setup: Apple M5 Max (18 cores), PHP 8.5.6 NTS, opcache on, JIT off, xdebug off. Temporal 1.29.3 + PostgreSQL in Docker (4 CPU), 1 task-queue partition (`bench/server`). RoadRunner 2025.1.15.
Both transports: 1 workflow process + 4 activity processes (RR: `num_workers: 4`), unless the label says otherwise. Every row is the mean of 2 runs, 0 failed workflows in all 44 runs. Harness: `bench/matrix.sh` (writes `bench/matrix.jsonl`).
"worker CPU s" = CPU time of all worker processes (rr + PHP, or the PHP supervisor + children, Rust threads included) during the measured run.

**Fixed rate (the server is not saturated): the worker cost**

| scenario | transport | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|
| 1000 wf × 1 activity @ 50/s | rr | 11 | 16 | 3.26 | 327 |
| | **core** | 11 | 15 | **2.21 (−32 %)** | **198 (−39 %)** |
| 1000 wf × 1 activity @ 100/s | rr | 11 | 16 | 2.54 | 327 |
| | **core** | 11 | 16 | **1.76 (−31 %)** | **197 (−40 %)** |
| 1000 wf, no activities @ 100/s | rr | 4 | 6 | 1.27 | 293 |
| | **core** | 4 | 7 | **0.89 (−30 %)** | **153 (−48 %)** |

**Full speed (the Docker server is the limit)**

| scenario | transport | wf/s | act/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|---|
| seq 1000 × 1 | rr | 297.8 | 298 | 1555 | 2560 | 1.94 | 334 |
| | core | 311.1 | 311 | 1501 | 2405 | 1.49 | 196 |
| seq 200 × 10 | rr | 53.9 | 538 | 1672 | 3053 | 2.33 | 336 |
| | core | 67.4 | 674 | 2718 | 2812 | 1.90 | 225 |
| noact 2000 | rr | 427.3 | – | 2261 | 3351 | 1.75 | 300 |
| | core | 430.6 | – | 2264 | 3291 | 1.32 | 155 |
| | core, 4 workflow processes | **726.6** | – | 1096 | 1180 | 1.34 | 244 |
| par 100 × 20 | rr | 26.6 | 531 | 1952 | 3674 | 1.51 | 387 |
| | core | 27.8 | 554 | 1831 | 3501 | 1.02 | 259 |
| | core, 1 activity process, 200 Fibers | 48.7 | 975 | 1117 | 1965 | 0.94 | 169 |

**Where RoadRunner cannot scale**

| scenario | transport | wf/s | act/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|---|
| I/O-bound: 100 wf × 10 parallel activities of 100 ms | rr (4 activity processes) | 3.8 | 38 | 13188 | 26092 | 1.73 | 391 |
| | core (4 activity processes) | 3.6 | 36 | 13995 | 27815 | 1.34 | 231 |
| | **core, 1 activity process, 200 Fibers** | **104** | **1040 (×27)** | **626** | **882** | 0.61 | 148 |
| CPU-bound workflow code: 300 wf, 5 ms CPU per activation | rr (1 workflow process, fixed) | 94.6 | 95 | 2303 | 2892 | 3.69 | 365 |
| | core, 1 workflow process | 95.3 | 95 | 1869 | 2921 | 3.45 | 206 |
| | **core, 4 workflow processes** | **294.6 (×3.1)** | 295 | **602** | **669** | 3.51 | 316 |

Conclusions:
- Same throughput and latency as RoadRunner wherever the server is the limit, with 20–35 % less worker CPU (no Go process, no pipe protocol, no re-encoding between two processes).
- Memory: the matrix above ran with `fork()`-ed children (35–50 % less RSS than RoadRunner). The final code starts children as fresh `php` processes (see below); then the RSS is the same as RoadRunner: `seq 1000 × 1 @ 100/s` core 335 MB / 1.84 CPU s vs rr 330 MB / 2.49 CPU s (2 runs each), Fibers `io` 204 MB.
- RoadRunner has exactly one workflow PHP process. The core transport can run several (each with its own sticky cache): ×3.1 for CPU-bound workflow code, ×1.7 for `noact` bursts.
- For I/O-bound activities, one PHP process with Fibers replaces dozens of blocking processes (×27 here with 4× fewer processes).
- In `seq 200 × 10` core has higher p50 but lower p99 and +25 % throughput: both are server queueing at saturation.

## 5. Where the time goes (debug labels, 1000 wf × 1 activity at 100 wf/s)

`TEMPORAL_CORE_PROFILE=1`, Docker server, `seq` 1000 × 1 activity at 100 wf/s, 1 workflow + 4 activity processes:

| process | process CPU (PHP + Rust) | PHP SDK dispatch | adapter + protobuf (activation − dispatch) | Rust / sdk-core (rest) |
|---|---|---|---|---|
| workflow (3000 activations) | 1468 ms | 394 ms (131 µs / activation) | 174 ms (58 µs / activation) | ~900 ms (~300 µs / activation) |
| activity (250 tasks each) | 145 ms | 24 ms (96 µs / task) | 18 ms (70 µs / task) | ~100 ms |

- The PHP adapter costs 15–20 % of the PHP busy time. The rest of the PHP time is the SDK itself (the same code runs under RoadRunner).
- An xdebug profile of the workflow process shows the SDK hot spots, which are the same with RoadRunner: `Carbon\CarbonInterval` (duration parsing in the marshaller, ~20 % of the busy PHP time), promises, attribute reading.
- sdk-core (history processing, gRPC, protobuf) is the largest part of the process CPU, but it replaces the whole Go process: the total worker CPU is still 25–35 % lower than `rr` + PHP.
- Tried and not helpful: fewer tokio threads (`TEMPORAL_CORE_THREADS=2`), no completion acknowledgements (kept: fewer wake-ups, no measurable CPU change).
- Found and fixed: task-queue partitions. With the default 4 partitions and few pollers, tasks waited up to 2 s on partitions that no poller read (both transports). The benchmark server uses 1 partition.

## 6. Limitations and differences to RoadRunner

Test suites on the core transport (`TEMPORAL_WORKER_TRANSPORT=core`):

| suite | core | RoadRunner |
|---|---|---|
| Unit | 1521/1521 (transport independent) | |
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
- **Connection:** TLS, mTLS, server name override and API key come from the standard env config (`TEMPORAL_TLS*`, `TEMPORAL_API_KEY`, TOML profile), checked through a TLS terminator. (With `fork()`-ed children, TLS with system roots crashed on macOS; with fresh child processes it works: checked through the TLS terminator, the children load the system roots and report the expected `UnknownIssuer` for the private test CA.)
- **Linux:** builds and runs in Docker (`core/docker/Dockerfile`, linux/arm64): 304–330 wf/s on `seq 200 × 1`, 752 act/s with Fibers, graceful `docker stop`.
- **Not done yet:** prebuilt binaries; `temporal.UpdateAPIKey` at run time; the RR KV caches of the testing package (the Functional harness still starts `rr serve` as a KV store only).

## 7. Next steps

1. Distribution: prebuilt `libtemporal_php_bridge` for linux-x64/arm64 and macOS (GitHub release assets, downloaded by `dload` like `rr` today), or a PHP extension built from the same crate.
2. Memory: share opcache between the children (`opcache.file_cache`) to get back the fork-mode RSS.
3. Replace the remaining RoadRunner users: `temporal.UpdateAPIKey`, the RR KV caches of the testing package. (`WorkflowReplayer` already has a core backend: `new WorkflowReplayer($coreWorkerFactory)`.)
5. SDK hot spots that help both transports: `CarbonInterval` in the marshaller, attribute reading per workflow start.
6. Fibers for workflows (the `sdk-php-fiber-runtime` branch) together with this transport.
