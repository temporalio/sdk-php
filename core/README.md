# Temporal PHP worker without RoadRunner (sdk-core via FFI)

This directory holds the native part of the RoadRunner-free worker transport.

| path | content |
|---|---|
| `bridge/` | Rust `cdylib` over Temporal sdk-core (`temporalio-sdk-core`, pinned git revision). Completion-queue C API, no callbacks into PHP. |
| `bridge/include/temporal_php_bridge.h` | The header that `FFI::cdef()` loads. |
| `docs/protocol-mapping.md` | RoadRunner ↔ PHP SDK ↔ sdk-core message mapping, gaps and risks. |
| `REPORT.md` | Why RoadRunner exists, what was tried, benchmark results, limitations. |

The PHP side is in `src/Worker/Core/`.

The PHP classes for the `coresdk.*` protos (`Coresdk\...`) come from `roadrunner-php/roadrunner-api-dto`, generated from the same sdk-core revision as the bridge (`sdk-core` submodule there). Bump both together.

## Build

```bash
cd core/bridge && cargo build --release
```

The result is `core/bridge/target/release/libtemporal_php_bridge.dylib` (`.so` on Linux).
Set `TEMPORAL_CORE_BRIDGE_LIB` to use a library from another path.
Linux image (Rust build stage + `php:8.5-cli` with ffi, pcntl, sockets, protobuf): `docker build -f core/docker/Dockerfile -t temporal-php-core .` from the repository root.
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
| `TEMPORAL_CORE_THREADS` | `1` | tokio worker threads per process (1 thread uses 15–22 % less CPU than one per core, see `EXPERIMENTS.md` E2) |
| `TEMPORAL_CORE_PROFILE` | off | `1` logs per-phase timings and process CPU to stderr every 10 s and at shutdown |
| `TEMPORAL_CORE_LOG` | off | sdk-core log filter, for example `info` |

The worker sends the `client-name: temporal-php-2` and `client-version: <temporal/sdk version>` headers, as RoadRunner does.

`SIGTERM`/`SIGINT` to the parent process stops all children gracefully. Running activities get `WorkerOptions::$workerStopTimeout` to finish (0 by default, as in sdk-go), then they are cancelled. A child that is still alive 10 s after that gets `SIGKILL`.
A child that exits unexpectedly is started again, with a growing delay if it keeps crashing right after start. A child that crashes during the first start stops the whole worker (a configuration error). A child stops by itself when the supervisor process is gone.
The exit code is non-zero when a child crashed, was killed, or an sdk-core worker shut down unexpectedly.

Mapped `WorkerOptions`: workflow/activity pollers and concurrency, `workerActivitiesPerSecond`, `taskQueueActivitiesPerSecond`, `stickyScheduleToStartTimeout`, `workerStopTimeout`, `disableWorkflowWorker`, `localActivityWorkerOnly`, `workflowPanicPolicy` (for panics in workflow code), `identity`, `buildID`, `deploymentOptions`. Worker plugins (`WorkerPluginInterface::run()`) wrap every worker process.

`run()` starts the role processes in one of two ways:
- On Linux without ext-grpc, it forks them. The children share the compiled code, opcache and the objects created before `run()` (copy-on-write): 35 % less memory on Linux (PSS), 50 % on macOS (footprint), see `EXPERIMENTS.md` E14. A client created before `run()` connects again in each child.
- Otherwise (ext-grpc loaded, macOS, or a client call before `run()` that started the sdk-core runtime), it starts each child as a fresh `php` process with the same script, arguments and changed ini settings, like RoadRunner starts its workers. The script runs again in every child. ext-grpc cannot be used after `fork()`, and on macOS TLS with the system roots crashes a forked child.

## gRPC client without ext-grpc

When ext-grpc is not loaded, `ServiceClient`, `OperatorClient`, `CloudClient` and the testing `TestService` send their calls through the sdk-core bridge (tonic, rustls with the system roots). `create()` and `createSSL()` work as before; the SDK retries, deadlines, metadata and API key are unchanged. Inside a Fibers activity process a call suspends only its own Fiber.

## Benchmarks

See `bench/` (`bench/run.sh <rr|core> <scenario> <workflows> <activities> <payload>`, `bench/matrix.sh`) and `REPORT.md`.
