#!/usr/bin/env bash
set -u
cd "$(dirname "$0")"
export BENCH_RESULTS=${BENCH_RESULTS:-$PWD/matrix.jsonl}
FAILED=0
run() {
  echo "::group::${BENCH_LABEL:-$2} $3 $4 $5 $6 ${BENCH_RATE:+@${BENCH_RATE}/s}"
  local code=0
  timeout --kill-after=15 "${BENCH_RUN_TIMEOUT:-600}" "$@" 2>&1 || code=$?
  echo "::endgroup::"
  if [ "$code" -ne 0 ]; then
    echo "FAILED ($code): $*"
    FAILED=1
  fi
}
for r in $(seq 1 "${BENCH_RUNS:-2}"); do
  for t in rr rr-core; do
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
  BENCH_LABEL=core-4wf BENCH_WORKFLOW_WORKERS=4 run ./run.sh rr-core cpu 300 1 5
  BENCH_LABEL=core-4wf BENCH_WORKFLOW_WORKERS=4 run ./run.sh rr-core noact 2000 0 100
done
echo MATRIX DONE
exit $FAILED
