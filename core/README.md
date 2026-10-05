# Temporal PHP worker without RoadRunner (sdk-core via FFI)

This directory holds the native part of the RoadRunner-free worker transport.

| path | content |
|---|---|
| `bridge/` | Rust `cdylib` over Temporal sdk-core (`temporalio-sdk-core` 0.9.0 from crates.io). Completion-queue C API, no callbacks into PHP. |
| `bridge/include/temporal_php_bridge.h` | The header that `FFI::cdef()` loads. |
| `grpc/` | The ext-grpc classes and constants (`Grpc\Channel`, `Grpc\Call`, `Grpc\Timeval`, `Grpc\ChannelCredentials`, `Grpc\CallCredentials`) on the bridge, loaded only without ext-grpc. |

The PHP side is in `src/Worker/Core/` (worker, adapter) and `src/Internal/Bridge/` (FFI bridge, `TEMPORAL_CORE_*` env, connection options).

The PHP classes for the `coresdk.*` protos (`Coresdk\...`) come from `roadrunner-php/roadrunner-api-dto`, generated from the same sdk-core release as the bridge (`sdk-core` submodule there, `core-v0.9.0`). Bump both together.

## Build

```bash
cd core/bridge && cargo build --release
```

The result is `core/bridge/target/release/libtemporal_php_bridge.dylib` (`.so` on Linux).
Set `TEMPORAL_CORE_BRIDGE_LIB` to use a library from another path.
PHP needs `ext-ffi` (the default `ffi.enable=preload` allows FFI in the CLI), `ext-protobuf` is recommended, `ext-pcntl` and `ext-posix` for the graceful stop.

## Run a worker

```php
use Temporal\Worker\Core\CoreWorkerFactory;

$factory = CoreWorkerFactory::create(
    address: '127.0.0.1:7233',
    namespace: 'default',
);
$factory->newWorker('my-queue')
    ->registerWorkflowTypes(MyWorkflow::class)
    ->registerActivityImplementations(new MyActivity());

exit($factory->run());
```

Run it with plain `php worker.php`. No `rr` binary and no `.rr.yaml` are necessary. The process serves all roles: workflows, local activities and activities. To run several processes, see [Run under RoadRunner](#run-under-roadrunner).

| env | default | meaning |
|---|---|---|
| `TEMPORAL_ADDRESS` | `127.0.0.1:7233` | server address |
| `TEMPORAL_NAMESPACE` | `default` | namespace |
| `TEMPORAL_API_KEY` | none | API key, sent as `Authorization: Bearer <key>`. Turns TLS on. `ServiceCredentials::withApiKey()` passed to `create()` has priority. `$factory->updateApiKey($key)` replaces it at run time, as the RoadRunner `temporal.UpdateAPIKey` RPC: the worker polls send the new key. A `\Stringable` key is read again at least every 0.5 s in each worker process |
| `TEMPORAL_TLS` | off | `true` turns TLS on with the system root certificates, `false` turns it off also with an API key |
| `TEMPORAL_TLS_SERVER_CA_CERT_PATH` / `_DATA` | system roots | server root CA (PEM file or PEM text). Turns TLS on |
| `TEMPORAL_TLS_CLIENT_CERT_PATH` / `_DATA` | none | client certificate for mTLS (PEM) |
| `TEMPORAL_TLS_CLIENT_KEY_PATH` / `_DATA` | none | client private key for mTLS (PEM) |
| `TEMPORAL_TLS_SERVER_NAME` | host of the address | server name for the certificate check |
| `TEMPORAL_PROFILE`, `TEMPORAL_CONFIG_FILE` | `default`, `temporal.toml` in the user config directory | TOML profile with the same settings (`Temporal\Common\EnvConfig\ConfigClient`). The env values override it. The `TEMPORAL_TLS*` and `TEMPORAL_API_KEY` values apply only with `TEMPORAL_ADDRESS`, `TEMPORAL_NAMESPACE` or a profile |
| `TEMPORAL_CORE_MAX_CACHED_WORKFLOWS` | from `memory_limit` | sticky cache size per workflow process. The default is (`memory_limit` − 64 MiB) / 64 KiB, from 10 to 10000 (`10000` with `memory_limit=-1`; 1024 with 128M), as TypeScript sizes `maxCachedWorkflows` from the heap limit: a cached workflow takes about 56 KB of PHP memory |
| `TEMPORAL_CORE_THREADS` | `1` | tokio worker threads per process, a positive integer (1 thread uses 15–22 % less CPU than one per core). Another value throws an exception |
| `TEMPORAL_CORE_GRPC_COMPRESSION` | `gzip` | `gzip` (sdk-core default) or `none`: gzip on the worker's gRPC calls saves network bytes and costs 7–15 % worker CPU. Another value stops the worker start with an error |
| `TEMPORAL_CORE_PROMETHEUS_ADDRESS` | off | `host:port` of the sdk-core Prometheus exporter (`/metrics`): the worker metrics and the `temporal_request*` and `temporal_long_request*` metrics of its gRPC connection. Each worker process takes the first free port from this one, so 1 workflow + 4 activity processes on `127.0.0.1:9464` serve `9464`–`9468` |
| `TEMPORAL_CORE_OTEL_URL` | off | OTLP collector URL for the same sdk-core metrics (`http://host:4317` for gRPC, `http://host:4318/v1/metrics` for HTTP). An alternative to `TEMPORAL_CORE_PROMETHEUS_ADDRESS`: both stop the worker start with an error |
| `TEMPORAL_CORE_OTEL_PROTOCOL` | `grpc` | `grpc` or `http` (OTLP/HTTP protobuf) |
| `TEMPORAL_CORE_OTEL_HEADERS` | none | `key=value,key=value` headers of every export request, for example an API key |
| `TEMPORAL_CORE_OTEL_METRIC_PERIODICITY_MS` | `1000` | interval of the OTLP metric export |
| `TEMPORAL_CORE_OTEL_USE_SECONDS_FOR_DURATIONS` | off | `true` exports durations as float seconds, not integer milliseconds |
| `TEMPORAL_CORE_METRIC_PREFIX` | `temporal_` | prefix of the sdk-core metric names (Prometheus and OTLP) |
| `TEMPORAL_CORE_METRIC_GLOBAL_TAGS` | none | `key=value,key=value` labels on every sdk-core metric (Prometheus and OTLP) |
| `TEMPORAL_CORE_POLLER_AUTOSCALING` | on | sdk-core scales the workflow and activity pollers from 1 up to the configured maximum, starting from 5 (burst latency −70 %); `0` keeps a fixed number of pollers |
| `TEMPORAL_CORE_TUNER_TARGET_MEMORY_USAGE`, `TEMPORAL_CORE_TUNER_TARGET_CPU_USAGE` | off (fixed slots) | target host memory and CPU usage from 0 to 1, set both: sdk-core's resource-based tuner gives out workflow and activity slots while the host stays below them. Local activities stay at 1 slot |
| `TEMPORAL_CORE_TUNER_WORKFLOW_MIN_SLOTS` / `_MAX_SLOTS` / `_RAMP_THROTTLE_MS` | `5` / `500` / `0` | workflow task slots of the resource-based tuner: always given, at most, minimum time between two new slots (Python and .NET defaults) |
| `TEMPORAL_CORE_TUNER_ACTIVITY_MIN_SLOTS` / `_MAX_SLOTS` / `_RAMP_THROTTLE_MS` | `1` / `500` / `50` | activity slots of the resource-based tuner; both are capped at 1, the process runs one activity at a time |
| `TEMPORAL_CORE_WORKER_HEARTBEAT_INTERVAL_MS` | `60000` | interval of the sdk-core worker heartbeats that the server shows in `temporal worker list` / `describe`, 1000–60000 (as in the other SDKs); `0` turns them off |
| `TEMPORAL_CORE_LOG` | `warn` | sdk-core log filter (`off`, `error`, `warn`, `info`, `debug` or a `tracing` filter); the records go to the worker's PSR logger with `target` and the core fields as context. At most 10000 records wait for the worker; more are dropped and a WARN record tells how many |

The worker sends the `client-name: temporal-php-2` and `client-version: <temporal/sdk version>` headers, as RoadRunner does.

`SIGTERM`/`SIGINT` stops the process gracefully. Running activities get `WorkerOptions::$workerStopTimeout` to finish (0 by default, as in sdk-go), then they are cancelled. The exit code is non-zero when an sdk-core worker shut down unexpectedly.

Mapped `WorkerOptions`: workflow/activity pollers and concurrency, `workerActivitiesPerSecond`, `taskQueueActivitiesPerSecond`, `stickyScheduleToStartTimeout`, `workerStopTimeout`, `disableWorkflowWorker`, `localActivityWorkerOnly`, `workflowPanicPolicy` (for panics in workflow code), `identity`, `buildID`, `deploymentOptions`. Worker plugins (`WorkerPluginInterface::run()`) wrap every worker process.

## Run under RoadRunner

The standard RoadRunner binary (`rr` from `dload.xml`) starts several worker processes with its `service` plugin, restarts them and serves KV, jobs, locks and metrics over RPC as before. Temporal still goes through sdk-core: the config has no `temporal` and no `server` section, so the RoadRunner Temporal plugin does not start.

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

`worker.php` is the same script as above. `TEMPORAL_CORE_ROLE` (`workflow`, `activity` or `all`) tells `run()` the role of the process: a workflow process runs workflows and local activities, an activity process runs activities. Each workflow process has its own sticky cache. With `TEMPORAL_CORE_ROLE` set, `run()` serves only that role in the current process, stops when RoadRunner is gone and returns the exit code: `exit($factory->run())` is required. `remain_after_exit` restarts a process that exits. `rr serve` sends SIGINT on stop, and the worker shuts down gracefully within `timeout_stop_sec` (5 s by default). The RoadRunner PHP clients (`spiral/roadrunner-kv`, `spiral/roadrunner-jobs`, `roadrunner/psr-logger`) connect to `RR_RPC` from any of these processes.

## gRPC client without ext-grpc

When ext-grpc is not loaded, Composer autoloads the ext-grpc API from `core/grpc/` and defines the `Grpc\*` constants. The generated gRPC stubs of `grpc/grpc` then send their calls through the sdk-core bridge (tonic, rustls). `ServiceClient`, `OperatorClient`, `CloudClient`, the testing `TestService` and stubs that user code creates work without changes. As in ext-grpc, `ChannelCredentials::createSsl()` without root certificates uses the roots of `grpc/grpc` (`etc/roots.pem`).

## Tests

`TEMPORAL_WORKER_TRANSPORT=core` runs the Functional and Acceptance suites on this transport (`composer test:func`, `composer test:func-timeskip`, `composer test:accept`). Both start the worker processes under the standard `rr` (`tests/Functional/.rr.core.yaml`, `tests/Acceptance/.rr.core.yaml`). The `Core transport` CI workflow runs them without ext-grpc.

## Benchmarks

`bench/run.sh <rr|core|rr-core> <scenario> <workflows> <activities> <param>` starts a worker (`rr`: RoadRunner with the Temporal plugin, `core`: one `php worker.php` process, `rr-core`: the standard `rr` starts the core worker processes with `bench/.rr.core.yaml`), a warmup and the measured run against a Temporal server, and appends one JSON line to the results file. `bench/matrix.sh` runs the whole matrix for `rr` and `rr-core`, `bench/summary.sh <file>` prints the table.

| scenario | workflow | `<param>` |
|---|---|---|
| `seq` | activities one after another | payload size, bytes |
| `par` | activities in parallel | payload size, bytes |
| `noact` | no activities | payload size, bytes |
| `io` | parallel activities that wait (`usleep`) | wait per activity, ms |
| `cpu` | CPU-bound workflow code, one activity | CPU time per activation, ms |
| `kv` | activities that set and get RoadRunner KV keys over RPC (`core` also starts a KV-only `rr` process and counts it) | KV set + get pairs per activity |

| variable | default | meaning |
|---|---|---|
| `TEMPORAL_ADDRESS` | `127.0.0.1:7557` (the `bench/server` Docker server); `run.sh` sets it, `worker.php` and `starter.php` require it | server address |
| `BENCH_RATE` | `0` (as fast as possible) | workflows started per second |
| `BENCH_CONCURRENCY` | `8` | starter processes |
| `BENCH_WARMUP` | `20` | warmup workflows |
| `BENCH_TIMEOUT` | `600` | seconds for one run |
| `BENCH_ACTIVITY_WORKERS` | `4` | activity processes (RoadRunner pool size, `rr-core` activity processes) |
| `BENCH_WORKFLOW_WORKERS` | `1` | `rr-core` workflow processes |
| `BENCH_RESULTS` | `results.jsonl` | results file |
| `BENCH_LABEL` | the transport | row label in the summary |
| `BENCH_WORKER_PHP_FLAGS` | – | extra `php` flags for the core worker (for example `-dopcache.jit=tracing`) |
| `BENCH_RUSAGE` | – | macOS: path to the `bench/rusage.c` binary (`cc -O2 -o bench/rusage bench/rusage.c`); adds instructions and cycles of the worker processes to the results |
| `RR_BIN` | `../rr` | RoadRunner binary |
| `BENCH_RUNS`, `BENCH_RUN_TIMEOUT` | `2`, `600` | `matrix.sh` only: repetitions and the timeout per run (needs GNU `timeout`, `brew install coreutils` on macOS) |

## Limitations and differences to RoadRunner

- **History format.** sdk-go writes `Version`, `SideEffect` and `LocalActivity` markers, sdk-core writes `core_patch` and `core_local_activity`. A workflow started on RoadRunner cannot continue on core and the other way round. Drain running workflows (or use worker versioning / a new task queue) before the switch.
- **`Workflow::sideEffect()`** is a local activity that the worker completes itself: one more activation per call, one marker per call (also `uuid*()`).
- **`Workflow::getVersion()`** maps to patches with id `<changeId>-<version>` and a per-run cache. The `TemporalChangeVersion` search attribute that sdk-go upserts is not written.
- **Local activities** run in the workflow process, not in the activity processes.
- **`WorkflowPanicPolicy::FailWorkflow`** fails the workflow on panics and on non-determinism detected by sdk-core, but sdk-core fails the workflow only on the first attempt of the workflow task; a task that already timed out keeps failing (sdk-go fails it on any attempt).
- **Deadlock detection:** `WorkerOptions::$deadlockDetectionTimeout` has no effect.
- **One activity at a time:** a worker process runs one activity at a time. For parallel activities, start more activity processes under RoadRunner.
- **fork() after the runtime started:** the sdk-core runtime cannot be used in a process that forked after it started; `Bridge::shared()` throws a `LogicException` there.
- **gRPC client without ext-grpc:** a failed call returns the status code, the message and `grpc-status-details-bin`; other response headers and trailers are not returned.
- **Testing package:** the RR KV caches are not replaced.
