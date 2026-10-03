# Worker benchmark

Throughput, latency, CPU and memory of a PHP worker under RoadRunner.

```bash
docker compose -f bench/server/compose.yaml up -d
bench/run.sh rr seq 1000 1 100
BENCH_RATE=100 bench/run.sh rr seq 1000 1 100
bench/matrix.sh
docker compose -f bench/server/compose.yaml down
```

`run.sh <transport> <scenario> <workflows> <activities> <payload>`:

| scenario | workflow |
|---|---|
| `seq` | `activities` sequential activities with a `payload`-byte string |
| `par` | `activities` parallel activities |
| `noact` | no activities |
| `io` | `activities` parallel activities that block for `payload` ms |
| `cpu` | `activities` sequential activities, `payload` ms of CPU work in the workflow per activation |

| env | default | meaning |
|---|---|---|
| `TEMPORAL_ADDRESS` | `127.0.0.1:7557` | server (the Docker server from `server/compose.yaml`) |
| `BENCH_ACTIVITY_WORKERS` | `4` | activity worker processes |
| `BENCH_RATE` | `0` | workflow starts per second, `0` = as fast as possible |
| `BENCH_CONCURRENCY` | `8` | starter processes |
| `BENCH_RESULTS` | `bench/results.jsonl` | JSON lines output |
| `RR_BIN`, `TEMPORAL_BIN` | `./rr`, `./temporal` | binaries (`vendor/bin/dload get`) |

The result JSON has server-side wall time, workflows/s, activities/s, latency percentiles and the CPU seconds and max RSS of all worker processes.
Results and observations: `RESULTS.md`.
