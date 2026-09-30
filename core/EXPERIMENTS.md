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

## Throughput (RPS) series: E6–E11

Setup: the Docker server (`bench/server`, Temporal 1.29.3 + PostgreSQL on tmpfs) in a Docker VM with **4 CPUs**, shared with another benchmark. The server was recreated after every repetition: the tmpfs database grows to ~2 GB after ~40k workflows and then slows the server 3–10×.
Two harnesses, interleaved A/B, 3 repetitions each:
- **burst**: `bench/run.sh` (the starter starts the workflows while the worker runs, 16 starter processes).
- **drain**: all workflows are started first, then the worker starts; rate = workflows / (last close − worker start). This removes the start phase from the measurement.
"server CPU" = CPU of the Temporal and PostgreSQL containers (cgroup `usage_usec`).

### E6. What limits throughput

| drain | wf/s | worker CPU (all processes) | server CPU |
|---|---|---|---|
| noact 4000, 1 workflow process, 20 pollers (ratio 1) | 956 | peak ~55 % of one core | **~3.5 of 4 cores** |
| noact 4000, 2 workflow processes | 916 | peak ~58 % | ~3.5 |
| noact 4000, `TEMPORAL_CORE_THREADS=2` | 947 | peak ~63 % | ~3.5 |
| seq 2000×1 (all poller variants) | 200–300 | peak 45–75 % | 3–3.5 |

The server is the limit: more workflow processes or tokio threads do not change the rate, the server VM is saturated, and the worker uses about half a core.
With the old defaults, a single workflow process was the limit for new workflows, with low CPU: see E7.

### E7. Workflow task pollers (adopted)

sdk-core splits `max_concurrent_workflow_task_polls` between the normal queue and the sticky queue with `nonsticky_to_sticky_poll_ratio` (`max(1, floor(n × ratio))` normal pollers). The old default (4 pollers, ratio 0.2) gave **1 normal-queue poller**. Every new workflow is taken from the normal queue, so one poll round trip (~2.5 ms) capped the pickup at ~400 new workflows/s per process, with the worker at 20–30 % CPU.

| variant (pollers normal/sticky) | drain noact 3000 | drain seq 2000×1 | drain seq 400×5 | drain seq 200×10 |
|---|---|---|---|---|
| 4, ratio 0.2 (1/3), old | 340 | 199 | 64 | 31.8 |
| **8, ratio 0.5 (4/4), new** | **612 (+80 %)** | **233 (+17 %)** | 64 | 39.9 |
| autoscaling (min 1, initial 5, max 100) | 693 | 225 | 67 | 40.0 |

| burst | variant | wf/s | p50 ms | p99 ms | worker CPU s |
|---|---|---|---|---|---|
| noact 2000 | 4 / 0.2 | 339 | 2955 | 4276 | 1.32 |
| | **8 / 0.5** | **589 (+74 %)** | **1510** | **1697** | 1.22 |
| seq 1000×1 | 4 / 0.2 | 249 | 1953 | 3112 | 1.75 |
| | **8 / 0.5** | **281 (+13 %)** | 2006 | **2312** | 1.80 |
| seq 1000×1 @100/s | 4 / 0.2 | 101 | 14 | 45 | 2.00 |
| | 8 / 0.5 | 101 | 14 | 45 | 1.98 |
| seq 200×10 | 4 / 0.2 | 50.9 | 3579 | 3735 | 2.20 |
| | 8 / 0.5 | 50.6 | 3651 | 3724 | 2.24 |

- 16 pollers (8/8) against 8 (4/4): noact +2 %, seq and par equal. Not adopted.
- Scenarios with many activities are limited by the 4 activity processes, not by the pollers.
- The CPU per workflow and the latency at a fixed rate do not change.
- **Autoscaling is not adopted**: on the Fibers scenario (io 300×10, 1 activity process, 200 Fibers) it was −40 % (68 vs 114 wf/s, all 3 repetitions), it adds a completion tail (on `seq 2000×1` the rate between the first and the last completion is 20–25 % below the p5–p95 rate, 0–3 % with fixed pollers), and it pulls work so fast that a drain of 2000 `seq ×1` workflows crashed the workflow process at the default `memory_limit=128M` (see E10).

**Adopted:** 8 workflow task pollers, ratio 0.5 (`WorkerOptions::$maxConcurrentWorkflowTaskPollers` still overrides the number).

### E8. Workflow processes

| burst | 1 process, 8 pollers | 4 processes, 8 pollers each | 4 processes, old 4 pollers each |
|---|---|---|---|
| noact 2000 wf/s (p50 ms) | 444 (2257) | 533 (298) | 522 (1727) |
| seq 1000×1 wf/s (CPU s) | 293 (1.70) | 265 (1.98) | 265 (2.02) |

At the server limit, more processes help only the pure-start workload (more normal-queue pollers) and cost 15–20 % more CPU on real workflows. The earlier "×1.9 with 4 processes" on `noact` (REPORT §4) came mostly from 4 × 1 normal-queue pollers. **No change**: keep 1 workflow process and add processes for CPU-heavy workflow code (REPORT §4: ×2.6 at 5 ms per activation).

### E9. Per-process capacity

The server did not saturate the workflow process, so the capacity is derived from the CPU per activation, measured in three ways:

| method | PHP main thread | tokio thread | rate |
|---|---|---|---|
| drain noact 4000 at ~950 wf/s (8000 activations incl. evictions), `ps -M` per thread | ~125 µs / activation | ~75 µs / activation | main thread 25–30 % busy |
| query probe: 64–128 clients query cached workflows (no history writes) | 69 µs / query | 123 µs / query | 3300 queries/s, main 23 %, tokio 41 % busy; the server stops at ~3300/s |
| replay of a 200-activity history (no network) | serial: ~90 µs / activation | | ~11 000 activations/s |

One workflow process with 1 tokio thread handles about **5 000–8 000 activations/s** of light workflow code (2 500–4 000 `noact` wf/s), 2.5–4× what this 4-CPU server can deliver.
The tokio thread is the first to saturate on query-heavy loads (123 µs per query); `TEMPORAL_CORE_THREADS=4` almost doubled the Rust CPU (7.6 s for 30 300 queries vs 4.1 s for 32 300 queries) without a higher rate.
Side finding: replay cost per activation grows with the history length (90 µs at 1 200 events, 295 µs at 12 000 events, after the 0.33 s JSON decode is removed). Not examined further.

### E10. Sticky cache and `memory_limit`

A cached workflow costs ~50 KB of PHP heap (2000 cached `QueryProbeWorkflow` instances: `memory_get_usage()` 120 MB vs ~20 MB idle; RSS +183 MB including sdk-core). With the default `memory_limit=128M` one workflow process holds ~2000 open workflows, but `max_cached_workflows` is 10 000. When more workflows are open (a backlog with slow activities), the process dies with "Allowed memory size exhausted", the supervisor restarts it, and its in-flight workflow tasks wait for the 10 s workflow task timeout.
Observed with autoscaling at 2000 backlog, and with the new 8/0.5 default at 5000 backlog (`seq 5000×1` drain; the old default did not crash there because it picked up work slower). RoadRunner has the same exposure (sdk-go default sticky cache 10 000, the same PHP workflow objects).
**No change made.** Options: raise `memory_limit` for workflow processes, set `TEMPORAL_CORE_MAX_CACHED_WORKFLOWS` to about `memory_limit / 64 KB`, or derive the default cache size from `memory_limit`.

### E11. Activity side

| variant | scenario | act/s | p99 ms | worker CPU s |
|---|---|---|---|---|
| 1 slot per activity process | par 100×20 | 430 | 4480 | 1.46 |
| 2 slots (prefetch 1 task) | par 100×20 | 515 (+19 %) | 3735 | 1.35 |
| 1 slot | seq 200×10 | 525 | 3598 | 2.33 |
| 2 slots | seq 200×10 | 550 (+5 %) | 3440 | 2.29 |
| 8 pollers per Fibers process | io 300×10 | 968 | 2755 | 1.82 |
| 16 pollers | io 300×10 | 1053 (+9 %) | 2510 | 1.76 |
| 32 pollers | io 300×10 | 956 | 2667 | 1.87 |

- **Prefetch is not adopted**: the second task waits behind a running blocking activity, but its start-to-close timeout already runs, and an idle process cannot take it.
- Fibers pollers 8 → 16: +9 %, inside the run-to-run noise (±10 %). **No change.**

## E12. PHP runtime: opcache, JIT, preload, file cache, shared memory

Hot loop without FFI (`WorkflowActivations` start → activity result → evict, and `ActivityTasks::handle`), minimum of 6 runs, µs per iteration:

| variant | workflow path | activity path | peak memory MB |
|---|---|---|---|
| no opcache | 160.5 | 24.4 | 11.7 |
| opcache | 155.1 | 24.2 | 4.1 |
| opcache + JIT tracing | 104.6 (−33 %) | 19.3 (−20 %) | |
| opcache + JIT function | 119.2 | 22.7 | |

Process start (autoload, factory, bridge, one protobuf message), ms, 8 runs:

| variant | ms |
|---|---|
| no opcache | 25–35 |
| opcache (CLI, empty shared memory each start) | 41–48 |
| opcache + `file_cache` | 21–27 |
| opcache + `file_cache_only` | 19–26 |
| `opcache.preload` | exits with code 255 and no message on PHP 8.5.6 when the preload script compiles protobuf-generated classes or `src/` |

End to end, interleaved, 3 repetitions (the machine was loaded by other benchmarks, so the absolute rate is below 100/s):

| variant | scenario | wf/s | p50 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|
| opcache | @100/s | 86.9 | 879 | 2.55 | 334 |
| opcache + JIT tracing | @100/s | 83.9 | 2131 | 2.98 (+17 %) | 368 |
| opcache | seq 200×5 | 46.5 | 3779 | 1.59 | 354 |
| opcache + JIT tracing | seq 200×5 | 41.7 | 4907 | 2.00 (+26 %) | 386 |

Result:
- Opcache: 3 % faster hot path and 3× less heap, the benchmark and the CI job keep it on. Each CLI process has its own shared memory, so opcache does not share compiled code between the worker processes.
- `file_cache` is the only way to share compiled code between the fresh child processes (fork is not possible with ext-grpc), it saves ~20 ms per process start. A worker starts its processes once, so this is not a throughput lever.
- Preload: not usable (crash on PHP 8.5.6), and for a long-lived worker it only saves the one-time class load.
- JIT: the hot loop is 20–33 % faster, but in a 10-second run the trace compilation costs more CPU than it saves. A 60-second run (`seq 6000×1 @100/s`, 2 repetitions on a quiet machine) gives 10.23 vs 10.43 CPU s (−2 %, noise) and +32 MB RSS: PHP is a small part of the worker CPU. Not adopted.
- The supervisor passes the parent `opcache.*` settings to the child processes, so a user enables JIT or `file_cache` with `php -d ... worker.php` and no code change.

## E13. Bridge hot path

Metric: instructions retired by all worker processes (`proc_pid_rusage`), ±0.3–1.5 % between identical runs, while CPU seconds moved ±5–20 % on the loaded machine. Fixed rate `seq 600×1 @25/s`, interleaved, 3–4 runs each.

Time Profiler, workflow process: PHP main thread 45 %, tokio worker 42–46 %, sdk-core `workflow-processing` thread 11–12 %. `kevent` park/wake 7–9 %, gRPC `writev` 4.5–5.5 %, `recvfrom` 2–3.4 %, system malloc/free ~5 %. All bridge-owned code (FFI trampolines with the Rust work inside, condvar, encode/decode, `FFI::string`) is 2.7–3.9 %.

| experiment | fixed rate instr | noact burst instr | verdict |
|---|---|---|---|
| mimalloc as the global allocator | −4.6…−5.6 % (CPU −4…−7 %) | −4.3…−4.9 % | **adopted**, +1 MB RSS per process |
| fat LTO + `codegen-units = 1` (on top of mimalloc) | −1.7 % | −1.9 % | **adopted**, library 22 → 15 MB |
| jemalloc vs mimalloc | +2.3 % | +1.6 % | rejected |
| tokio `current_thread` on a dedicated thread | −0.1 % | −0.6 % | rejected, same park/unpark |
| spin 50 / 200 µs before the condvar wait | +20 % / +75 % | +19 % / +69 % | rejected |
| fused complete + poll in one FFI call | −0.7 % | +0.4 % | rejected, noise |

Result: the bridge is not the cost. The rest is sdk-core gRPC syscalls, tokio and sdk-core thread hand-offs, and the PHP SDK. sdk-core also runs a `temporal-real-sysinfo` thread per worker (100 ms refresh) that `WorkerConfig` cannot turn off: 6 idle processes use 68 ms CPU in 30 s.

## E14. gRPC client through sdk-core, forked children

Idea: the worker path never used ext-grpc, only the SDK clients did (the starter, activities that signal or complete workflows). If the clients call the server through the bridge, a worker needs no ext-grpc, and without ext-grpc the supervisor can `fork()` its children, so they share opcache and the code loaded before `run()`.

The bridge got `tpb_client_new` / `tpb_client_call`: a tonic channel (TLS from the same settings as the worker) and a raw unary call with a byte codec. The PHP clients keep their retries, deadlines and metadata; `CoreStub` replaces `Grpc\BaseStub::_simpleRequest` and returns a call whose `wait()` takes the result from the event queue (a Fiber in a Fibers activity process suspends instead). A client-side deadline is reported as `DEADLINE_EXCEEDED`, as ext-grpc does (tonic reports `CANCELLED`).

What blocks `fork()`:
- ext-grpc: a call in a forked child hangs when the parent created a client before `fork()`, even without a call: `ServiceClient::create()` builds the channel at once, and gRPC core starts ~17 threads (5 → 22 in the process). The child gets the memory of these threads (locks, queued work) but not the threads. A child works when the parent never created a channel. `grpc.enable_fork_support=1` fails with "failed to shutdown gRPC Core after fork()": the extension's child handler expects `grpc_shutdown()` to drop the init count to 0, but the default EventEngine holds its own `grpc_init` reference (`KeepsGrpcInitialized`); gRPC supports fork only with the `epoll1`/`poll` pollers anyway.
- macOS: loading the TLS system roots in a forked child aborts (`objc ... initialize may have been in progress in another thread when fork() was called`), also when the parent never used TLS. On Linux the same test passes.
- An sdk-core runtime that already runs in the parent (threads). The supervisor forks only when none exists.

Memory, 1 workflow + 4 activity processes after 300 workflows:

| platform | children | total RSS MB | shared-aware MB |
|---|---|---|---|
| macOS | fresh processes (ext-grpc) | 332–336 | 195–201 (footprint) |
| macOS | forked (no ext-grpc, no TLS) | 186 | **98 (−50 %)** |
| Linux (Docker, arm64) | fresh processes | 336 | 209 (PSS) |
| Linux (Docker, arm64) | forked | 285–304 | **135–143 (−35 %)** |

Interleaved on macOS (2 repetitions; macOS then still forked):

| variant | scenario | wf/s | p50 ms | worker CPU s | RSS MB |
|---|---|---|---|---|---|
| ext-grpc, fresh processes | @100/s | 101.1 | 12 | 1.65 | 332 |
| no ext-grpc, forked | @100/s | 101.0 | 12 | 1.69 | 186 |
| ext-grpc, fresh processes | seq 200×10 | 48.6 | 3804 | 1.89 | 357 |
| no ext-grpc, forked | seq 200×10 | 52.1 | 3570 | 1.64 | 212 |
| ext-grpc, fresh processes | noact 2000 | 489.6 | 1376 | 1.05 | 294 |
| no ext-grpc, forked | noact 2000 | 459.7 | 1668 | 1.08 | 147 |

CPU and throughput are within the noise. The CI benchmark (Linux, no ext-grpc) runs every scenario with 0 failed workflows.

Result: **adopted**. Without ext-grpc the SDK clients use the bridge, and on Linux the supervisor forks. With ext-grpc nothing changes.
Side finding, fixed: when PHP runs without a php.ini but with a scan dir (the official Docker images), fresh children were started with `-n` and `-d extension=FFI`, which does not load (`ffi.so`); now `-n` is used only when neither is loaded.
Side finding, fixed: in a Fibers activity process a heartbeat did not read pending events, so an activity that blocks between heartbeats never saw its cancellation or pause (`CancelTryCancel`, `ActivityPaused` failed with concurrency > 1 on both clients).

## E16. Boundary format and thread hand-offs

Question: the data crosses the bridge as protobuf bytes (Rust encode → PHP decode, and back), and every event moves between the tokio thread and the PHP thread. Can C structs or fewer thread hops remove this cost?

Other SDKs (sources at their main branches, sdk-core at our rev):
- Python, TypeScript, .NET and Ruby all send protobuf bytes for activations, completions, activity tasks, heartbeats and client calls. The .NET C bridge uses `#[repr(C)]` structs only for options, and only removes one copy (it parses Rust-owned memory in place). No SDK found a protobuf cost worth an issue.
- All four run sdk-core on a multi-thread tokio runtime with one thread per core and deliver results through 4–5 thread hops (callback → language event loop → worker pool → back). The PHP bridge has 2 hops and 1 tokio thread. No SDK drives tokio from the language thread.
- Their defaults (cache 1000–10000, 5 pollers, ratio 0.2, fixed slots, no autoscaling) match ours; ours now uses 8 workflow pollers split 4/4 (E7).
- The per-worker `temporal-real-sysinfo` thread (E13) is fixed in sdk-core after our rev (temporalio/sdk-rust#1393, 83 commits later, coresdk protos changed on the way).

Protobuf vs C structs, hot loop (PHP 8.5, ext-protobuf, one `ResolveActivity` activation of 109 bytes):

| path | µs per activation |
|---|---|
| `mergeFromString` + read run id, timestamp, history length, job seq, payload | 0.95–1.03 |
| the same fields from a C struct through FFI | 0.22–0.26 |
| + building the `Payload` object the SDK needs | +0.23 |

The saving is ~0.5 µs of 200–400 µs per activation in a running worker (≤ 0.25 %), for a hand-kept C mirror of dozens of nested messages. JSON is not on the worker path (only worker/client creation and client-call metadata). **Not changed.**

Tokio driven by the PHP thread (`current_thread` runtime, `block_on` inside `tpb_next_events`, no tokio worker thread), interleaved, 3 repetitions, server recreated before each; metric: instructions and cycles of all worker processes (`BENCH_RUSAGE`, `bench/rusage.c`):

| variant | scenario | wf/s | worker CPU s | G instructions | G cycles |
|---|---|---|---|---|---|
| tokio thread | @100/s | 101.0 | 1.68 | 4.40 | 6.85 |
| PHP-driven | @100/s | 101.0 | 1.61 | 4.22 (−4 %) | 6.61 (−4 %) |
| tokio thread | seq 200×10 | 58.0 | 1.83 | 5.39 | 7.65 |
| PHP-driven | seq 200×10 | 53.5 (−8 %) | 1.75 | 5.15 (−4 %) | 7.28 (−5 %) |
| tokio thread | noact 2000 | 562.8 | 1.09 | 3.54 | 4.45 |
| PHP-driven | noact 2000 | 531.6 (−6 %) | 0.99 | 3.35 (−5 %) | 4.11 (−8 %) |
| PHP-driven + one runtime tick after each complete/poll call | seq 200×10 | 56.4 vs 60.4 (−7 %) | 1.76 vs 1.74 | −3 % | +2 % |
| same | noact 2000 | 562.8 vs 569.3 (−1 %) | 0.99 vs 1.05 | −4 % | −5 % |

While PHP runs workflow code the network does not move, so bursts lose the overlap between PHP work and gRPC. The mode also cannot work with the Revolt loop (nobody drives tokio while PHP waits in `stream_select`), and a long local activity or a blocking activity would stop workflow-task and activity heartbeats. **Rejected**: 3–5 % fewer instructions do not pay for lower burst throughput and these limits.

Result: the boundary and the hand-offs are already the cheapest of the five SDKs; the remaining cost is gRPC/h2 work in sdk-core and the PHP SDK itself.
