# Temporal PHP worker without RoadRunner (sdk-core via FFI)

This directory holds the native part of the RoadRunner-free worker transport.

| path | content |
|---|---|
| `bridge/` | Rust `cdylib` over Temporal sdk-core (`temporalio-sdk-core` 0.9.0 from crates.io). Completion-queue C API, no callbacks into PHP. |
| `bridge/include/temporal_php_bridge.h` | The header that `FFI::cdef()` loads. |
| `roadrunner/` | A RoadRunner build without the Temporal plugin: service, server, rpc, kv, jobs, lock, metrics, logger. |

The PHP side is in `src/Worker/Core/` (worker, supervisor, adapter), `src/Internal/Bridge/` (FFI bridge, `TEMPORAL_CORE_*` env, connection options) and `src/Client/GRPC/Core/` (gRPC client without ext-grpc).

The PHP classes for the `coresdk.*` protos (`Coresdk\...`) come from `roadrunner-php/roadrunner-api-dto`, generated from the same sdk-core release as the bridge (`sdk-core` submodule there, `core-v0.9.0`). Bump both together.

## Build

```bash
cd core/bridge && cargo build --release
```

The result is `core/bridge/target/release/libtemporal_php_bridge.dylib` (`.so` on Linux).
Set `TEMPORAL_CORE_BRIDGE_LIB` to use a library from another path.
PHP needs `ext-ffi` (the default `ffi.enable=preload` allows FFI in the CLI), `ext-protobuf` is recommended, `ext-pcntl` and `ext-posix` for the process supervisor.

## Run a worker

```php
use Temporal\Worker\Core\CoreWorkerFactory;

$factory = CoreWorkerFactory::create(
    address: '127.0.0.1:7233',
    namespace: 'default',
    workflowProcesses: 1,
    activityProcesses: 4,
);
$factory->newWorker('my-queue')
    ->registerWorkflowTypes(MyWorkflow::class)
    ->registerActivityImplementations(new MyActivity());

exit($factory->run());
```

Run it with plain `php worker.php`. No `rr` binary and no `.rr.yaml` are necessary.

| env | default | meaning |
|---|---|---|
| `TEMPORAL_ADDRESS` | `127.0.0.1:7233` | server address |
| `TEMPORAL_NAMESPACE` | `default` | namespace |
| `TEMPORAL_API_KEY` | none | API key, sent as `Authorization: Bearer <key>`. Turns TLS on. `ServiceCredentials::withApiKey()` passed to `create()` has priority |
| `TEMPORAL_TLS` | off | `true` turns TLS on with the system root certificates, `false` turns it off also with an API key |
| `TEMPORAL_TLS_SERVER_CA_CERT_PATH` / `_DATA` | system roots | server root CA (PEM file or PEM text). Turns TLS on |
| `TEMPORAL_TLS_CLIENT_CERT_PATH` / `_DATA` | none | client certificate for mTLS (PEM) |
| `TEMPORAL_TLS_CLIENT_KEY_PATH` / `_DATA` | none | client private key for mTLS (PEM) |
| `TEMPORAL_TLS_SERVER_NAME` | host of the address | server name for the certificate check |
| `TEMPORAL_PROFILE`, `TEMPORAL_CONFIG_FILE` | `default`, `temporal.toml` in the user config directory | TOML profile with the same settings (`Temporal\Common\EnvConfig\ConfigClient`). The env values override it. The `TEMPORAL_TLS*` and `TEMPORAL_API_KEY` values apply only with `TEMPORAL_ADDRESS`, `TEMPORAL_NAMESPACE` or a profile |
| `TEMPORAL_CORE_WORKFLOW_PROCESSES` | `1` | workflow processes (each has its own sticky cache) |
| `TEMPORAL_CORE_ACTIVITY_PROCESSES` | `1` | activity processes; `0` runs activities in the workflow process (they block workflow tasks while they run) |
| `TEMPORAL_CORE_ACTIVITY_CONCURRENCY` | `1` | activities that one activity process runs at the same time in Fibers on the Revolt event loop (only for non-blocking activity code, `revolt/event-loop` must be installed) |
| `TEMPORAL_CORE_MAX_CACHED_WORKFLOWS` | `10000` | sticky cache size per workflow process |
| `TEMPORAL_CORE_THREADS` | `1` | tokio worker threads per process, a positive integer (1 thread uses 15–22 % less CPU than one per core). Another value throws an exception |
| `TEMPORAL_CORE_GRPC_COMPRESSION` | `gzip` | `gzip` (sdk-core default) or `none`: gzip on the worker's gRPC calls saves network bytes and costs 7–15 % worker CPU. Another value stops the worker start with an error |
| `TEMPORAL_CORE_PROMETHEUS_ADDRESS` | off | `host:port` of the sdk-core Prometheus exporter (`/metrics`). Each worker process takes the first free port from this one, so 1 workflow + 4 activity processes on `127.0.0.1:9464` serve `9464`–`9468` |
| `TEMPORAL_CORE_POLLER_AUTOSCALING` | on | sdk-core scales the workflow and activity pollers from 1 up to the configured maximum, starting from 5 (burst latency −70 %); `0` keeps a fixed number of pollers |
| `TEMPORAL_CORE_LOG` | `warn` | sdk-core log filter (`off`, `error`, `warn`, `info`, `debug` or a `tracing` filter); the records go to the worker's PSR logger with `target` and the core fields as context |

The worker sends the `client-name: temporal-php-2` and `client-version: <temporal/sdk version>` headers, as RoadRunner does.

`SIGTERM`/`SIGINT` to the parent process stops all children gracefully. Running activities get `WorkerOptions::$workerStopTimeout` to finish (0 by default, as in sdk-go), then they are cancelled. A child that is still alive 10 s after that gets `SIGKILL`.
A child that exits unexpectedly is started again after 1, 2, 4 … 30 s; the delay goes back to 0 after the child runs for 60 s. A child that exits in the first second of the first start stops the whole worker (a configuration error). A child stops by itself when the supervisor process is gone.
The exit code is the highest exit code of the children (128 + signal number for a child stopped by a signal), so it is non-zero when a child crashed, was killed, or an sdk-core worker shut down unexpectedly.

Mapped `WorkerOptions`: workflow/activity pollers and concurrency, `workerActivitiesPerSecond`, `taskQueueActivitiesPerSecond`, `stickyScheduleToStartTimeout`, `workerStopTimeout`, `disableWorkflowWorker`, `localActivityWorkerOnly`, `workflowPanicPolicy` (for panics in workflow code), `identity`, `buildID`, `deploymentOptions`. Worker plugins (`WorkerPluginInterface::run()`) wrap every worker process.

`run()` starts the role processes in one of two ways:
- On Linux without ext-grpc, it forks them. The children share the compiled code, opcache and the objects created before `run()` (copy-on-write): 35 % less memory on Linux (PSS). A client created before `run()` connects again in each child.
- Otherwise (ext-grpc loaded, macOS, or a client call before `run()` that started the sdk-core runtime), it starts each child as a fresh `php` process with the same script, arguments and changed ini settings, like RoadRunner starts its workers. The script runs again in every child. ext-grpc cannot be used after `fork()`, and on macOS TLS with the system roots crashes a forked child.

## Run under RoadRunner

RoadRunner can manage the worker processes instead of `run()`. Temporal still goes through sdk-core; RoadRunner starts and restarts the processes and serves KV, jobs, locks and metrics over RPC as before.

```bash
cd core/roadrunner && GOWORK=off CGO_ENABLED=0 go build -trimpath -o rr .
```

```yaml
version: "3"

rpc:
    listen: tcp://127.0.0.1:6001

kv:
    cache:
        driver: memory
        config: {}

service:
    temporal-workflow:
        command: "php worker.php"
        process_num: 1
        remain_after_exit: true
        restart_sec: 1
        env:
            TEMPORAL_CORE_ROLE: workflow
            RR_RPC: tcp://127.0.0.1:6001
    temporal-activity:
        command: "php worker.php"
        process_num: 4
        remain_after_exit: true
        restart_sec: 1
        env:
            TEMPORAL_CORE_ROLE: activity
            RR_RPC: tcp://127.0.0.1:6001
```

`worker.php` is the same script as above. With `TEMPORAL_CORE_ROLE` set, `run()` serves only that role in the current process, stops when RoadRunner is gone and returns the exit code: `exit($factory->run())` is required. `remain_after_exit` restarts a process that exits. `rr serve` sends SIGINT on stop, and the worker shuts down gracefully within `timeout_stop_sec` (5 s by default). The RoadRunner PHP clients (`spiral/roadrunner-kv`, `spiral/roadrunner-jobs`, `roadrunner/psr-logger`) connect to `RR_RPC` from any of these processes.

## gRPC client without ext-grpc

When ext-grpc is not loaded, `ServiceClient`, `OperatorClient`, `CloudClient` and the testing `TestService` send their calls through the sdk-core bridge (tonic, rustls with the system roots). `create()` and `createSSL()` work as before; the SDK retries, deadlines, metadata and API key are unchanged. Inside a Fibers activity process a call suspends only its own Fiber.

## Tests

`TEMPORAL_WORKER_TRANSPORT=core` runs the Functional and Acceptance suites on this transport (`composer test:func`, `composer test:func-timeskip`, `composer test:accept`). The `Core transport` CI workflow runs them without ext-grpc, Acceptance with `TEMPORAL_CORE_ACTIVITY_CONCURRENCY` 1 and 8, without the 7 tests listed in [Limitations](#limitations-and-differences-to-roadrunner).

## Benchmarks

`bench/run.sh <rr|core|rr-core> <scenario> <workflows> <activities> <param>` starts a worker (`rr`: RoadRunner with the Temporal plugin, `core`: `run()` supervises the processes, `rr-core`: the `core/roadrunner` build starts them), a warmup and the measured run against a Temporal server, and appends one JSON line to the results file. `bench/matrix.sh` runs the whole matrix for both transports, `bench/summary.sh <file>` prints the table.

| scenario | workflow | `<param>` |
|---|---|---|
| `seq` | activities one after another | payload size, bytes |
| `par` | activities in parallel | payload size, bytes |
| `noact` | no activities | payload size, bytes |
| `io` | parallel activities that wait (non-blocking in Fibers) | wait per activity, ms |
| `cpu` | CPU-bound workflow code, one activity | CPU time per activation, ms |
| `kv` | activities that set and get RoadRunner KV keys over RPC (`core` also starts a KV-only `core/roadrunner` process and counts it) | KV set + get pairs per activity |

| variable | default | meaning |
|---|---|---|
| `TEMPORAL_ADDRESS` | `127.0.0.1:7557` (the `bench/server` Docker server); `run.sh` sets it, `worker.php` and `starter.php` require it | server address |
| `BENCH_RATE` | `0` (as fast as possible) | workflows started per second |
| `BENCH_CONCURRENCY` | `8` | starter processes |
| `BENCH_WARMUP` | `20` | warmup workflows |
| `BENCH_TIMEOUT` | `600` | seconds for one run |
| `BENCH_ACTIVITY_WORKERS` | `4` | activity processes (RoadRunner pool size, core activity processes) |
| `BENCH_RESULTS` | `results.jsonl` | results file |
| `BENCH_LABEL` | the transport | row label in the summary |
| `BENCH_WORKER_PHP_FLAGS` | – | extra `php` flags for the core worker (for example `-dopcache.jit=tracing`) |
| `BENCH_RUSAGE` | – | macOS: path to the `bench/rusage.c` binary (`cc -O2 -o bench/rusage bench/rusage.c`); adds instructions and cycles of the worker processes to the results |
| `RR_BIN` | `../rr` | RoadRunner binary |
| `RR_CORE_BIN` | `../core/roadrunner/rr` | RoadRunner build without the Temporal plugin |
| `BENCH_RUNS`, `BENCH_RUN_TIMEOUT` | `2`, `600` | `matrix.sh` only: repetitions and the timeout per run (needs GNU `timeout`, `brew install coreutils` on macOS) |

## Limitations and differences to RoadRunner

- **History format.** sdk-go writes `Version`, `SideEffect` and `LocalActivity` markers, sdk-core writes `core_patch` and `core_local_activity`. A workflow started on RoadRunner cannot continue on core and the other way round. Drain running workflows (or use worker versioning / a new task queue) before the switch.
- **`Workflow::sideEffect()`** is a local activity that the worker completes itself: one more activation per call, one marker per call (also `uuid*()`).
- **`Workflow::getVersion()`** maps to patches with id `<changeId>-<version>` and a per-run cache. The `TemporalChangeVersion` search attribute that sdk-go upserts is not written.
- **Local activities** run in the workflow process, not in the activity processes.
- **`WorkflowPanicPolicy::FailWorkflow`** fails the workflow on panics and on non-determinism detected by sdk-core, but sdk-core fails the workflow only on the first attempt of the workflow task; a task that already timed out keeps failing (sdk-go fails it on any attempt).
- **Deadlock detection:** `WorkerOptions::$deadlockDetectionTimeout` has no effect.
- **Fiber concurrency** helps only activities that use non-blocking I/O. A blocking call (PDO, curl, `sleep`) stops all activities of the process. Fibers started inside an activity fiber (`Amp\async`, event loop callbacks) see the global Activity context; call `Activity::*` from the activity fiber itself.
- **fork() after the runtime started:** the sdk-core runtime cannot be used in a process that forked after it started; `Bridge::shared()` throws a `LogicException` there.
- **gRPC client without ext-grpc:** a failed call returns the status code, the message and `grpc-status-details-bin`; other response headers and trailers are not returned. `temporal.UpdateAPIKey` at run time is not supported.
- **Testing package:** the RR KV caches are not replaced; the Functional harness still starts `rr serve` as a KV store.

Acceptance tests that fail on core:
- by design: `SideEffectTest` ×3 and `ResetWorkerTest::resetWithSignal` look for the Go `SideEffect` marker in the history; on core the value is in a `core_local_activity` marker;
- harness only: `ClassicTest::replayDifferentVersions` (Go-recorded JSON fixtures), `WorkerRestartTest` (RR KV storage and RR restart), `TranscriptWorkflowFailureTest` (expects RR wire frames).
