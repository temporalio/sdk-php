# Performance experiments (core transport)

Setup for all experiments unless noted: Apple M5 Max (18 cores), PHP 8.5, opcache on, `temporal server start-dev` on `127.0.0.1:7557` with 1 task-queue partition (the Docker server was not available).
Default worker: 1 workflow process + 4 activity processes.
Scenarios per repetition: `seq 1000×1 @100/s` (fixed rate: worker CPU and latency), `seq 1000×1` burst, `seq 200×5` burst.
Main metric: **worker CPU seconds at the fixed rate** (noise between repetitions: ±5 %). Latency at the fixed rate is set by the dev server: an idle workflow with one activity takes 10.7 ms end to end, at 100 wf/s the p50 is ~140 ms because the server queues tasks.

## Baseline

| scenario | runs | wf/s | act/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|---|
| seq 1000×1 @100/s | 3 | 99.8 | 100 | 140 | 166 | 2.36 | 337 |
| seq 1000×1 | 3 | 221.6 | 222 | 2160 | 3218 | 2.18 | 341 |
| seq 200×5 | 3 | 99.6 | 498 | 1662 | 1849 | 1.54 | 358 |

## E1. Eager activity execution

Idea: sdk-core requests eager execution for activities on the same task queue when the worker has a free activity slot, so the server returns the activity task in the workflow task response and skips one matching round trip.
In the split layout the workflow process has `no_remote_activities`, so eager can never happen there. Tried the `all` layout (every process runs workflows and activities), with and without `system.enableActivityEagerExecution=true` on the server.

| variant | scenario | wf/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|
| baseline (1 wf + 4 act) | @100/s | 100 | 140 | 164 | 2.34 | 337 |
| 1 `all` process | @100/s | 100 | 139 | 165 | **2.00** | **65** |
| 4 `all` processes | @100/s | 100 | 134 | 161 | 2.54 | 290 |
| baseline | seq 200×5 | 98.2 | 1663 | 1851 | 1.61 | 356 |
| 1 `all` process | seq 200×5 | 87.9 | 1995 | 2158 | 1.11 | 89 |

Result: **no latency gain**. An idle activity starts 1.9 ms after it is scheduled, so the matching hop eager removes is small, and under load the dev server queue dominates.
Side finding: one `all` process uses 15 % less CPU and 5× less memory than 1 + 4 processes for light activities, but it runs activities and workflow tasks one after another, so throughput drops with more activities (−10 % on `seq 200×5`). Not adopted as a default.

## E2. Tokio worker threads per process

Profile first: under 250 wf/s the workflow process is ~20 % busy, `sample` shows almost no Rust code on top of the stacks, only waits on ~18 tokio worker threads (one per core).
`getrusage` split (1000 wf @100/s): workflow process user 1260 ms / **sys 542 ms**, each activity process user 235 ms / sys 92 ms. The sys time is thread wake-ups and parking of an 18-thread runtime that has little work.

Interleaved A/B on a fresh server, 3 repetitions each:

| variant | scenario | wf/s | p50 ms | p99 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|
| default (threads = cores) | @100/s | 99.8 | 142 | 167 | 2.28 | 337 |
| `TEMPORAL_CORE_THREADS=1` | @100/s | 99.8 | 142 | 168 | **1.78 (−22 %)** | 334 |
| default | seq 1000×1 | 225.4 | 2154 | 3101 | 2.08 | 341 |
| threads = 1 | seq 1000×1 | 216.8 | 2213 | 3478 | **1.75 (−16 %)** | 339 |
| default | seq 200×5 | 77.3 | 1597 | 3105 | 1.49 | 358 |
| threads = 1 | seq 200×5 | 69.0 | 1748 | 3209 | **1.26 (−15 %)** | 355 |
| threads = 2 (separate series) | @100/s | 99.8 | 146 | 171 | 2.19 (−3 %) | 334 |
| 1 thread only in activity processes | @100/s | 99.8 | 143 | 167 | 2.12 (−7 %) | 335 |

Burst throughput differs by up to ±8 % even between two identical variants (the dev server state), so the −4/−11 % throughput differences are within noise; the CPU saving is consistent in all three scenarios.
With 1 thread the workflow process sys time drops from 542 ms to 339–358 ms.
**Adopted:** the bridge now defaults to 1 tokio worker thread per process (`TEMPORAL_CORE_THREADS` overrides).

## E3. Adapter overhead (activity path)

Per-phase timings inside `ActivityTasks::handle` in the running worker (1000 wf @100/s, pcov off, 4 activity processes, ~25 tasks/s per process):
parse 14 µs, build request 44 µs, SDK dispatch 225 µs, result 14 µs, serialize completion 40 µs — ~340 µs per task.

The same code in a hot loop (`handle()` 20 000 times, no FFI):

| part | hot µs |
|---|---|
| whole `ActivityTasks::handle` | 22.9 |
| SDK dispatch (route, marshaller, activity, promises) | 14.6 |
| adapter `request()` | 4.2 |
| protobuf parse + serialize of the completion | < 1 |

An xdebug profile had pointed at `Marshaller->unmarshal` (65 % of the activity path); without xdebug it costs ~10 µs, so the profile was an instrumentation artefact.
Result: the adapter adds ~8 µs of real work per task. The 15× gap between hot (23 µs) and in-worker (340 µs) cost is cold execution: at a few dozen tasks per second per process every task starts with cold caches, a thread wake-up and (on this Mac) often an efficiency core. **No change adopted**; the lever is fewer wake-ups and fewer idle processes, not faster adapter code.
Side finding: the profiling helper had pcov enabled (the `run.sh` matrix did not), which inflated all per-phase numbers by ~30 %.

## E4. Number of activity processes

Interleaved A/B (2 repetitions each, 1 tokio thread per process):

| variant | scenario | wf/s | act/s | p50 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|---|
| 4 activity processes | @100/s | 99.9 | 100 | 139 | 1.85 | 334 |
| 1 activity process | @100/s | 99.7 | 100 | 141 | **1.68 (−9 %)** | **161** |
| 4 activity processes | seq 200×5 | 107.9 | 540 | 1544 | 1.21 | 356 |
| 1 activity process | seq 200×5 | 69.2 | 346 (−36 %) | 2538 | 1.19 | 185 |
| 2 activity processes (separate series) | @100/s | 99.8 | 100 | 143 | 1.96 (≈ 4 processes) | 218 |

Result: an idle activity process costs little CPU (a few %), and the process count is a capacity setting for blocking activities, not an efficiency one. **No change**: keep it a user setting (`activityProcesses`), and use Fibers for non-blocking activities.

## E5. Real memory: RSS vs physical footprint

The benchmark sums RSS, which counts shared file-backed pages (the php binary, the 30 MB bridge library, opcache code) once per process. macOS `footprint` (private dirty memory) after 300 workflows, 1 workflow + 4 activity processes:

| transport | process | RSS MB | footprint MB |
|---|---|---|---|
| rr | `rr` (Go) | 82 | 63 |
| rr | php workflow worker | 51 | 31 |
| rr | php activity worker ×4 | 49 each | 28 each |
| **rr total** | | **327** | **206** |
| core | supervisor (php, idle) | 41 | 20 |
| core | workflow process | 66 | 39 |
| core | activity process ×4 | 58 each | 31 each |
| **core total** | | **338** | **183 (−11 %)** |

Result: with fresh child processes the core transport uses slightly more RSS but **11 % less real memory** than RoadRunner: the Go process (63 MB) is gone, each PHP process pays ~3 MB for the tokio runtime and bridge data, and the idle supervisor costs 20 MB. No change adopted; the RSS column in the benchmarks overstates both transports.
