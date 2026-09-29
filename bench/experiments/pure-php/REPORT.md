# Pure-PHP activity worker: experiment report

## Question

Can the PHP SDK talk gRPC to the Temporal server directly from PHP, without RoadRunner and without sdk-core?
The experiment covers activity workers only. A workflow worker is out of scope (see "Workflow worker" below).

## gRPC clients for PHP (PHP 8.5.6 NTS, macOS)

| client | non-blocking | status on this machine |
|---|---|---|
| ext-grpc 1.80 + `grpc/grpc` (generated `WorkflowServiceClient` from `roadrunner-php/roadrunner-api-dto`) | no (`->wait()` blocks the process) | installed, works |
| `thesis/grpc-client` 0.3.3 (amphp/http-client HTTP/2, Revolt, Fibers) | yes | installs with composer (PHP ^8.4), works |
| amphp/http-client with manual gRPC framing | yes | not necessary: `thesis/grpc-client` is this, done correctly |
| OpenSwoole / Swoole gRPC | yes (coroutines) | not tried: needs `ext-openswoole`/`ext-swoole`, a PECL build, not installed |

Notes about `thesis/grpc-client`:

- Use the split package `thesis/grpc-client`. The monorepo package `thesis/grpc` installs, but it fails at run time: `Package thesis/grpc-protocol is not installed` (the user-agent lookup reads the split package name).
- It uses `thesis/protobuf` by default, not `google/protobuf`. The encoder is pluggable (`Builder::withEncoding()`). A 20-line `Encoder` with `serializeToString()`/`mergeFromString()` makes the existing generated `Temporal\Api\*` classes work. No new code generation is necessary.
- HTTP/2 over cleartext (h2c, prior knowledge) works against the dev server.
- The Temporal server rejects a long poll without a deadline (`INVALID_ARGUMENT: Context timeout is not set`). Send `grpc-timeout` (`Metadata::withKey(Timeout::seconds(70))`). ext-grpc needs the same (`['timeout' => 70_000_000]`).

## What was built

All files are in this directory. The SDK `composer.json` and `src/` are not changed.

| file | content |
|---|---|
| `composer.json` | separate project: `thesis/grpc-client`, `grpc/grpc`, `google/protobuf`, `roadrunner-php/roadrunner-api-dto` 1.17.0 (the SDK version) |
| `tasks.php` | poll request, and `BenchActivity.echo` handler: `RespondActivityTaskCompleted` with `result = input`. An unknown type gets a non-retryable `RespondActivityTaskFailed`. |
| `poller-grpc.php` | (A) ext-grpc, blocking, one poll at a time per process |
| `poller-amp.php` | (B) thesis/amphp, one process, `POLLERS` (default 8) concurrent long polls, each task responded in its own Fiber |
| `workflow-worker.php` | the existing sdk-core worker in workflow-only role (`serve('workflow')`, `no_remote_activities`), so only the pollers above get the activity tasks |
| `run.sh` | `./run.sh <grpc\|amp\|core> <scenario> <workflows> <activities>`: starts the workflow worker and the pollers, uses `bench/starter.php`, measures the poller CPU and RSS separately, writes `results.jsonl` |

`core` in `run.sh` is the current sdk-core FFI activity worker (`bench/worker.php`, `TEMPORAL_CORE_WORKFLOW_PROCESSES=0`, 4 activity processes), as the reference.

## Results

Dev server 127.0.0.1:7556, payload 100 B, starter concurrency 8, warmup 20 workflows, new task queue per run.
"poller CPU s" is the CPU time of the activity poller processes during the measured run (the supervisor and its children for `core`).
The workflow side is the same sdk-core workflow worker for all rows (1.3-1.5 CPU s for seq, 0.3-0.4 CPU s for par).

| poller | processes | scenario | runs | act/s avg (min-max) | poller CPU s | CPU s / 1000 act | poller RSS MB |
|---|---|---|---|---|---|---|---|
| (A) ext-grpc blocking | 4 | seq 200 x 10 | 2 | 242 (200-284) | 1.30 | 0.65 | 170 |
| (B) thesis/amphp | 1 | seq 200 x 10 | 2 | 271 (268-275) | 1.29 | 0.65 | 52 |
| sdk-core FFI | 4 | seq 200 x 10 | 2 | 280 (274-285) | 1.02 | 0.51 | 169 |
| (A) ext-grpc blocking | 4 | par 100 x 20 | 2 | 314 (275-354) | 1.27 | 0.63 | 170 |
| (B) thesis/amphp | 1 | par 100 x 20 | 4 | 366 (366-367) | 0.93 | 0.46 | 52 |
| sdk-core FFI | 4 | par 100 x 20 | 2 | 271 (269-272) | 0.89 | 0.45 | 169 |

No workflow failed. Raw data: `results.jsonl`. One `amp par` run of the matrix exited non-zero with stderr discarded. Three repeats of it passed, and the cause is not known.

Observations:

- All three pollers are faster than the server. Throughput is set by the dev server. The difference in act/s between pollers is inside the run-to-run noise (see `bench/RESULTS.md`: up to 1.7x).
- CPU per activity: sdk-core 0.45-0.51 ms, thesis/amphp 0.46-0.65 ms, ext-grpc 0.63-0.65 ms. The pure-PHP transports cost the same order of CPU as sdk-core. HTTP/2 framing, HPACK and Fiber switches in PHP are not a significant cost at this rate.
- One amphp process with 8 outstanding polls replaces 4 blocking processes. It uses 52 MB RSS alternative to 170 MB, and it runs activities concurrently in one process.
- ext-grpc gives concurrency only with more processes. A slow activity blocks its process and its poll.

## Verdict

**Activity worker in pure PHP: viable.** The protocol is small: `PollActivityTaskQueue`, `RespondActivityTaskCompleted/Failed/Canceled`, `RecordActivityTaskHeartbeat`. `thesis/grpc-client` on Revolt works on PHP 8.5, uses the existing generated API classes, and gives N concurrent polls and Fiber-concurrent activities in one process at sdk-core CPU cost. ext-grpc works as a simple blocking baseline.

Remaining work for a production activity worker (about 1-2 weeks): heartbeats with throttling, and cancellation from the heartbeat response; poll errors with backoff and reconnect; concurrency and rate limits (slots); graceful shutdown (stop polls, drain tasks); TLS and API key credentials; the `DataConverter`, interceptors and `ActivityInfo` context from the SDK; async completion; eager activity dispatch is not possible without a workflow worker in the same process.

Blocking PHP activity code (PDO, curl, `sleep`) blocks the whole event loop in variant (B). Variant (B) is only concurrent for activities that use amphp-compatible I/O. For ordinary blocking activities, run several (B) processes or use (A).

**Workflow worker in pure PHP: not viable as a short project.** The workflow side needs the workflow task state machine that sdk-go (under RoadRunner) or sdk-core now provide: history events to activation jobs, command-to-event matching for each command type, non-determinism detection, sticky queues and the workflow cache with eviction, history pagination, query and update handling, local activities (markers, workflow task heartbeat), patches/versioning markers, SDK flags, child workflows, continue-as-new, cancellation of each command type, and worker versioning. In the local sdk-core checkout, `core/src/worker/workflow` is about 15 000 lines of Rust (8 600 of them in `machines/`). A port to PHP is an estimated 3-6 person-months to reach parity, plus a replay test corpus (the `temporalio/features` suite) and continuous maintenance for each new server feature. The sdk-core FFI path gives this for free.

## Recommendation

Keep sdk-core FFI as the main path for workflows. A pure-PHP (thesis/amphp) activity worker is a realistic option only if a reason exists to run activity-only workers without the Rust library, for example a platform where FFI or the native library is not available. It gives no measurable throughput or CPU gain over the sdk-core activity worker.
