#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 4 ]; then
    echo "usage: $0 <poller: grpc|amp|core> <scenario> <workflows> <activities> [payload]" >&2
    exit 1
fi

POLLER=$1 SCENARIO=$2 WORKFLOWS=$3 ACTIVITIES=$4 PAYLOAD=${5:-100}
DIR=$(cd "$(dirname "$0")" && pwd)
BENCH_DIR=$(cd "$DIR/../.." && pwd)
TEMPORAL_BIN=${TEMPORAL_BIN:-/Users/xepozz/IdeaProjects/temporalio/sdk-php-fiber-runtime/temporal}
PROCESSES=${PROCESSES:-4}
PHP="php -dxdebug.mode=off -dpcov.enabled=0 -dopcache.enable_cli=1"

export XDEBUG_MODE=off
export TEMPORAL_ADDRESS=${TEMPORAL_ADDRESS:-127.0.0.1:7556}
export BENCH_TASK_QUEUE="pure-$POLLER-$(date +%s)-$$"

TMP=$(mktemp -d)
PIDS=()
POLLER_PIDS=()

cleanup() {
    for pid in "${PIDS[@]}"; do kill -TERM "$pid" 2>/dev/null || true; done
    for pid in "${PIDS[@]}"; do wait "$pid" 2>/dev/null || true; done
    rm -rf "$TMP"
}
trap cleanup EXIT

$PHP "$DIR/workflow-worker.php" > "$TMP/workflow.log" 2>&1 &
WORKFLOW_PID=$!
PIDS+=("$WORKFLOW_PID")

case "$POLLER" in
    grpc)
        for _ in $(seq 1 "$PROCESSES"); do
            $PHP "$DIR/poller-grpc.php" >> "$TMP/poller.log" 2>&1 &
            POLLER_PIDS+=($!)
        done
        ;;
    amp)
        $PHP "$DIR/poller-amp.php" > "$TMP/poller.log" 2>&1 &
        POLLER_PIDS+=($!)
        ;;
    core)
        TEMPORAL_CORE_WORKFLOW_PROCESSES=0 BENCH_TRANSPORT=core BENCH_ACTIVITY_WORKERS=$PROCESSES \
            $PHP "$BENCH_DIR/worker.php" > "$TMP/poller.log" 2>&1 &
        POLLER_PIDS+=($!)
        ;;
    *) echo "unknown poller: $POLLER" >&2; exit 1 ;;
esac
PIDS+=("${POLLER_PIDS[@]}")

tree_pids() {
    local out="" pid
    for pid in "$@"; do out="$out $pid $(pgrep -P "$pid" | tr '\n' ' ' || true)"; done
    echo $out | tr ' ' ','
}

cpu_seconds() {
    ps -o time= -p "$(tree_pids "$@")" | awk '{n=split($1,a,":"); s=0; for(i=1;i<=n;i++) s=s*60+a[i]; t+=s} END {printf "%.2f", t}'
}

rss_mb() {
    ps -o rss= -p "$(tree_pids "$@")" | awk '{r+=$1} END {printf "%.1f", r/1024}'
}

for _ in $(seq 1 120); do
    types=$("$TEMPORAL_BIN" task-queue describe --address "$TEMPORAL_ADDRESS" --task-queue "$BENCH_TASK_QUEUE" -o json 2>/dev/null \
        | jq '[.pollers[]?.taskQueueType] | unique | length' || echo 0)
    [ "$types" = "2" ] && break
    sleep 0.5
done
[ "$types" = "2" ] || { echo "workers are not ready" >&2; cat "$TMP"/*.log >&2; exit 1; }

cd "$BENCH_DIR"
$PHP starter.php --scenario="$SCENARIO" --workflows=20 --activities="$ACTIVITIES" --payload="$PAYLOAD" --concurrency=2 > /dev/null

POLLER_CPU_BEFORE=$(cpu_seconds "${POLLER_PIDS[@]}")
WORKFLOW_CPU_BEFORE=$(cpu_seconds "$WORKFLOW_PID")
RESULT=$($PHP starter.php --scenario="$SCENARIO" --workflows="$WORKFLOWS" --activities="$ACTIVITIES" --payload="$PAYLOAD" --concurrency=8)
POLLER_CPU=$(echo "$(cpu_seconds "${POLLER_PIDS[@]}") - $POLLER_CPU_BEFORE" | bc)
WORKFLOW_CPU=$(echo "$(cpu_seconds "$WORKFLOW_PID") - $WORKFLOW_CPU_BEFORE" | bc)
ACTIVITY_COUNT=$((WORKFLOWS * ACTIVITIES))

[ -s "$TMP/poller.log" ] && { echo "--- poller log:" >&2; tail -5 "$TMP/poller.log" >&2; }

echo "$RESULT" | jq -c \
    --arg poller "$POLLER" \
    --arg time "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --argjson processes "$([ "$POLLER" = amp ] && echo 1 || echo "$PROCESSES")" \
    --argjson poller_cpu "$POLLER_CPU" \
    --argjson workflow_cpu "$WORKFLOW_CPU" \
    --argjson poller_rss "$(rss_mb "${POLLER_PIDS[@]}")" \
    --argjson per1000 "$(echo "scale=3; $POLLER_CPU * 1000 / $ACTIVITY_COUNT" | bc)" \
    '{time: $time, poller: $poller, poller_processes: $processes} + . + {poller_cpu_s: $poller_cpu, poller_cpu_s_per_1000_act: $per1000, poller_rss_mb: $poller_rss, workflow_worker_cpu_s: $workflow_cpu}' \
    | tee -a "$DIR/results.jsonl"
