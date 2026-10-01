# Worker benchmark: RoadRunner baseline (historical)

The first RoadRunner baseline, measured on the CLI dev server with 4 task-queue partitions (the cause of the ~2 s tails below). It is not comparable with the current results in `core/REPORT.md` §4, which use the `bench/server` Docker server with 1 partition.

## Environment

- Machine: Apple M5 Max, 18 cores (6 performance + 12 efficiency), 128 GB RAM, macOS 27.0
- PHP 8.5.6 (cli, NTS, Homebrew). Xdebug and pcov are loaded but off: `XDEBUG_MODE=off`, `-dxdebug.mode=off -dpcov.enabled=0` for the worker and the starter.
- Worker: `-dopcache.enable_cli=1`. JIT is off.
- RoadRunner 2025.1.15 (temporal plugin, proto codec: the plugin always sets `RR_CODEC=protobuf`, there is no JSON codec option).
- Temporal CLI 1.4.2 dev server (Server 1.29.0), in-memory, `127.0.0.1:7556`, on the same machine.
- `BENCH_ACTIVITY_WORKERS=4` (1 workflow PHP process + 4 activity PHP processes), default poller counts.
- Starter: 8 forked processes, a warmup of 20 workflows before each measured run, a new task queue per run.

## Results (transport `rr`, payload 100 B)

Wall time is server-side: first `startTime` to last `closeTime`. Latency is `closeTime - startTime` per workflow.
Worker CPU and RSS are the sum over `rr` and its PHP children, sampled every 0.5 s. CPU s is the CPU time the worker processes used during the measured run.

| scenario | workflows | activities/wf | run | wall s | wf/s | act/s | p50 ms | p95 ms | p99 ms | start phase s | worker CPU % avg | worker CPU s | worker RSS MB max |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| (a) seq | 1000 | 1 | 1 | 7.49 | 133.5 | 133.5 | 1773 | 2799 | 4859 | 0.72 | 24.2 | 1.92 | 338 |
| (a) seq | 1000 | 1 | 2 | 11.76 | 85.0 | 85.0 | 1839 | 6963 | 9584 | 0.80 | 17.8 | 2.19 | 339 |
| (b) seq | 200 | 10 | 1 | 8.93 | 22.4 | 224.0 | 3576 | 8287 | 8787 | 0.17 | 29.5 | 2.78 | 348 |
| (b) seq | 200 | 10 | 2 | 9.97 | 20.1 | 200.5 | 5254 | 7557 | 7968 | 0.21 | 25.2 | 2.89 | 345 |
| (c) noact | 2000 | 0 | 1 | 7.97 | 250.9 | 0 | 1842 | 2628 | 2729 | 1.36 | 16.7 | 1.38 | 300 |
| (c) noact | 2000 | 0 | 2 | 8.76 | 228.4 | 0 | 1930 | 2699 | 4780 | 1.41 | 14.4 | 1.38 | 301 |
| (d) par | 100 | 20 | 1 | 11.61 | 8.6 | 172.3 | 1207 | 7185 | 9555 | 0.11 | 13.2 | 1.68 | 390 |
| (d) par | 100 | 20 | 2 | 6.72 | 14.9 | 297.6 | 1174 | 2669 | 6676 | 0.08 | 19.9 | 1.50 | 378 |

No workflow failed.

## Observations

- The worker is not the bottleneck. The worker used 14-30 % CPU (of 1800 %) while the dev server used 250-320 % CPU.
- Throughput and tail latency vary up to 1.7x between identical runs. The dev server (in-memory SQLite) and task matching limit the result.
- The starter is not the bottleneck: 1000-1500 starts/s, the start phase is 1-15 % of the wall time.
- More pollers (8 workflow + 8 activity) gave the same result for (a): 137 wf/s.
- An idle workflow step is 2-3 ms, but some steps wait about 50 ms for a poller (the task goes to the backlog when no poll is open).
- For the comparison with a new transport, "worker CPU s" and "worker RSS" measure the worker. Wall time and latency measure mostly the server.

## Docker server (`server/compose.yaml`, port 7557)

Temporal 1.29.3 (`auto-setup`) + PostgreSQL 16 on tmpfs (`fsync=off`), 512 history shards, high RPS limits.
The dynamic config sets `matching.numTaskqueueReadPartitions=1` and `matching.numTaskqueueWritePartitions=1`.
With the default 4 partitions and few pollers, tasks wait on partitions that no poller reads. This caused the 2 s tails in the table above.
Docker Desktop has 4 CPUs and 6 GB. Start: `docker compose -f bench/server/compose.yaml up -d`. Stop: `docker compose -f bench/server/compose.yaml down`.
`run.sh` uses `127.0.0.1:7557` by default and passes it to the worker, the starter and `.rr.yaml`. Measure both transports on this server.

`rr seq 1000x1`, burst:

| server | wall s | wf/s | p50 ms | p95 ms | p99 ms |
|---|---|---|---|---|---|
| dev server, 4 partitions (7556) | 7.26 | 137.8 | 1934 | 3142 | 5924 |
| dev server, 1 partition | 3.49 | 286.7 | 1684 | 2731 | 2801 |
| dev server, 1 partition | 3.58 | 279.7 | 1726 | 2744 | 2812 |
| Docker, 1 partition (7557) | 3.27 | 305.4 | 1511 | 2512 | 2568 |
| Docker, 1 partition (7557) | 3.43 | 291.4 | 1661 | 2588 | 2670 |

`rr seq 50x3`, burst: dev server with 1 partition p50 362 ms / p95 417 ms, Docker p50 221 ms / p95 362 ms.

At full speed the server is still the limit. In a 2000x1 burst, rr used about 47 % CPU and the workflow PHP process about 13 %. In a 1000x1 burst, the Docker server used about 290 % of its 4 CPUs.
Use `BENCH_RATE` (for example 50 and 100 wf/s) to compare the worker below saturation.


## Core transport comparison

The RoadRunner vs sdk-core comparison is in `core/REPORT.md` section 4 (`matrix.sh`).
