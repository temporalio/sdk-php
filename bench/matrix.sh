#!/usr/bin/env bash
set -u
cd "$(dirname "$0")"
export BENCH_ACTIVITY_WORKERS=4 BENCH_RESULTS=${BENCH_RESULTS:-$PWD/matrix.jsonl}
run() { "$@" 2>&1 | grep -E '^\{' >/dev/null || echo "FAILED: $*" >&2; }
for r in 1 2; do
  for t in rr core; do
    run ./run.sh $t seq 1000 1 100
    run ./run.sh $t seq 200 10 100
    run ./run.sh $t noact 2000 0 100
    run ./run.sh $t par 100 20 100
    BENCH_RATE=50 run ./run.sh $t seq 1000 1 100
    BENCH_RATE=100 run ./run.sh $t seq 1000 1 100
    BENCH_RATE=100 run ./run.sh $t noact 1000 0 100
    run ./run.sh $t io 100 10 100
    run ./run.sh $t cpu 300 1 5
  done
  BENCH_LABEL=core-fibers BENCH_ACTIVITY_WORKERS=1 TEMPORAL_CORE_ACTIVITY_CONCURRENCY=200 run ./run.sh core io 100 10 100
  BENCH_LABEL=core-fibers BENCH_ACTIVITY_WORKERS=1 TEMPORAL_CORE_ACTIVITY_CONCURRENCY=200 run ./run.sh core par 100 20 100
  BENCH_LABEL=core-4wf TEMPORAL_CORE_WORKFLOW_PROCESSES=4 run ./run.sh core cpu 300 1 5
  BENCH_LABEL=core-4wf TEMPORAL_CORE_WORKFLOW_PROCESSES=4 run ./run.sh core noact 2000 0 100
done
echo MATRIX DONE
