#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 5 ]; then
    echo "usage: $0 <transport> <scenario> <workflows> <activities> <payload>" >&2
    exit 1
fi

TRANSPORT=$1 SCENARIO=$2 WORKFLOWS=$3 ACTIVITIES=$4 PAYLOAD=$5
BENCH_DIR=$(cd "$(dirname "$0")" && pwd)
cd "$BENCH_DIR"

RR_BIN=${RR_BIN:-$BENCH_DIR/../rr}
TEMPORAL_BIN=${TEMPORAL_BIN:-$BENCH_DIR/../temporal}
CONCURRENCY=${BENCH_CONCURRENCY:-8}
WARMUP=${BENCH_WARMUP:-20}
RATE=${BENCH_RATE:-0}
RESULTS_FILE=${BENCH_RESULTS:-results.jsonl}
PHP="php -dxdebug.mode=off -dpcov.enabled=0"

export XDEBUG_MODE=off
export TEMPORAL_ADDRESS=${TEMPORAL_ADDRESS:-127.0.0.1:7557}
export BENCH_TRANSPORT=$TRANSPORT
export BENCH_ACTIVITY_WORKERS=${BENCH_ACTIVITY_WORKERS:-4}
export BENCH_TASK_QUEUE="bench-$(date +%s)-$$"

TMP=$(mktemp -d)
WORKER_PID=
SAMPLER_PID=

cleanup() {
    [ -n "$SAMPLER_PID" ] && kill "$SAMPLER_PID" 2>/dev/null || true
    if [ -n "$WORKER_PID" ]; then
        kill -TERM "$WORKER_PID" 2>/dev/null || true
        wait "$WORKER_PID" 2>/dev/null || true
    fi
    rm -rf "$TMP"
}
trap cleanup EXIT

case "$TRANSPORT" in
    rr) "$RR_BIN" serve -c .rr.yaml > "$TMP/worker.log" 2>&1 & ;;
    core) $PHP -dopcache.enable_cli=1 worker.php > "$TMP/worker.log" 2>&1 & ;;
    *) echo "unknown transport: $TRANSPORT" >&2; exit 1 ;;
esac
WORKER_PID=$!

descendants() {
    local child
    for child in $(pgrep -P "$1" || true); do
        echo "$child"
        descendants "$child"
    done
}

worker_pids() {
    echo "$WORKER_PID" $(descendants "$WORKER_PID") | tr ' ' ','
}

cpu_seconds() {
    if [ -d /proc ]; then
        for pid in $(worker_pids | tr ',' ' '); do cat "/proc/$pid/stat" 2>/dev/null; done \
            | awk -v hz="$(getconf CLK_TCK)" '{sub(/.*\) /, ""); t+=$12+$13} END {printf "%.2f", t/hz}'
        return
    fi
    ps -o time= -p "$(worker_pids)" | awk '{n=split($1,a,":"); s=0; for(i=1;i<=n;i++) s=s*60+a[i]; t+=s} END {printf "%.2f", t}'
}

for _ in $(seq 1 120); do
    types=$("$TEMPORAL_BIN" task-queue describe --address "$TEMPORAL_ADDRESS" --task-queue "$BENCH_TASK_QUEUE" -o json 2>/dev/null \
        | jq '[.pollers[]?.taskQueueType] | unique | length' || echo 0)
    [ "$types" = "2" ] && break
    kill -0 "$WORKER_PID" 2>/dev/null || { cat "$TMP/worker.log" >&2; exit 1; }
    sleep 0.5
done
[ "$types" = "2" ] || { echo "worker is not ready" >&2; cat "$TMP/worker.log" >&2; exit 1; }

$PHP starter.php --scenario="$SCENARIO" --workflows="$WARMUP" --activities="$ACTIVITIES" --payload="$PAYLOAD" --concurrency=2 > /dev/null

(
    while kill -0 "$WORKER_PID" 2>/dev/null; do
        ps -o %cpu=,rss= -p "$(worker_pids)" | awk '{c+=$1; r+=$2} END {print c, r}'
        sleep 0.5
    done
) > "$TMP/samples" &
SAMPLER_PID=$!

CPU_BEFORE=$(cpu_seconds)
RESULT=$($PHP starter.php --scenario="$SCENARIO" --workflows="$WORKFLOWS" --activities="$ACTIVITIES" --payload="$PAYLOAD" --concurrency="$CONCURRENCY" --rate="$RATE")
CPU_AFTER=$(cpu_seconds)
WORKER_PROCESSES=$(descendants "$WORKER_PID" | wc -l | tr -d ' ')

kill "$SAMPLER_PID" 2>/dev/null || true
wait "$SAMPLER_PID" 2>/dev/null || true
SAMPLER_PID=

STATS=$(awk '{c+=$1; n++; if ($2>r) r=$2} END {printf "{\"cpu_pct_avg\":%.1f,\"rss_mb_max\":%.1f,\"samples\":%d}", c/n, r/1024, n}' "$TMP/samples")

echo "$RESULT" | jq -c \
    --arg transport "$TRANSPORT" \
    --arg time "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --argjson stats "$STATS" \
    --argjson cpu "$(awk -v a="$CPU_AFTER" -v b="$CPU_BEFORE" 'BEGIN {printf "%.2f", a - b}')" \
    --argjson workers "$BENCH_ACTIVITY_WORKERS" \
    --argjson procs "$WORKER_PROCESSES" \
    --arg label "${BENCH_LABEL:-$TRANSPORT}" \
    --argjson wfprocs "${TEMPORAL_CORE_WORKFLOW_PROCESSES:-1}" \
    --argjson concurrency "${TEMPORAL_CORE_ACTIVITY_CONCURRENCY:-1}" \
    '{time: $time, transport: $transport, label: $label, activity_workers: $workers, workflow_processes: $wfprocs, activity_concurrency: $concurrency, worker_child_processes: $procs} + . + {worker: ($stats + {cpu_seconds: $cpu})}' \
    | tee -a "$RESULTS_FILE"
