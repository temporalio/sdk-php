# Temporal PHP worker without RoadRunner (sdk-core via FFI)

This directory holds the native part of the RoadRunner-free worker transport.

| path | content |
|---|---|
| `bridge/` | Rust `cdylib` over Temporal sdk-core (`temporalio-sdk-core`, pinned git revision). Completion-queue C API, no callbacks into PHP. |
| `bridge/include/temporal_php_bridge.h` | The header that `FFI::cdef()` loads. |
| `generated/` | PHP protobuf classes for the `coresdk.*` protos (`Coresdk\...`). `temporal.api.*` classes come from `roadrunner-php/roadrunner-api-dto`. |
| `generate-protos.sh` | Regenerates `generated/`. |
| `docs/protocol-mapping.md` | RoadRunner ↔ PHP SDK ↔ sdk-core message mapping, gaps and risks. |
| `REPORT.md` | Why RoadRunner exists, what was tried, benchmark results, limitations. |

The PHP side is in `src/Worker/Core/`.

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
| `TEMPORAL_CORE_WORKFLOW_PROCESSES` | `1` | workflow processes (each has its own sticky cache) |
| `TEMPORAL_CORE_ACTIVITY_PROCESSES` | `0` | activity processes; `0` runs activities in the workflow process |
| `TEMPORAL_CORE_ACTIVITY_CONCURRENCY` | `1` | activities that one activity process runs at the same time in Fibers on the Revolt event loop (only for non-blocking activity code, `revolt/event-loop` must be installed) |
| `TEMPORAL_CORE_MAX_CACHED_WORKFLOWS` | `10000` | sticky cache size per workflow process |
| `TEMPORAL_CORE_THREADS` | CPU count | tokio worker threads per process |
| `TEMPORAL_CORE_PROFILE` | off | `1` logs per-phase timings and process CPU to stderr every 10 s and at shutdown |
| `TEMPORAL_CORE_LOG` | off | sdk-core log filter, for example `info` |

`SIGTERM`/`SIGINT` to the parent process stops all children gracefully. A child that does not stop within the largest `WorkerOptions` stop timeout (10 s by default) gets `SIGKILL`. A child that exits unexpectedly is started again.

`run()` forks the role processes. Create gRPC clients (`WorkflowClient` used inside activities), database connections and other sockets lazily, after the fork: a gRPC channel created before `fork()` hangs in the children (ext-grpc limitation).

## Benchmarks

See `bench/` (`bench/run.sh <rr|core> <scenario> <workflows> <activities> <payload>`, `bench/matrix.sh`) and `REPORT.md`.
