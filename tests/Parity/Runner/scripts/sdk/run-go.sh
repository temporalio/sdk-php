#!/usr/bin/env bash
# Run the Go fixture-runner for a scenario as TWO coordinated processes — one
# worker (background, blocks on InterruptCh) and one starter (foreground, runs
# the workflow then exits). Mirrors the canonical samples-go worker/starter
# split and avoids the "os.Exit mid-poll" race that flakes shared-task-queue runs.
#
# Usage:
#   run-go.sh <scenario-dir>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-go"
# shellcheck source=../lib/log.sh
. "${SCRIPT_DIR}/../lib/log.sh"
# shellcheck source=../lib/manifest.sh
. "${SCRIPT_DIR}/../lib/manifest.sh"

if [[ $# -lt 1 ]]; then
    parity_die "usage: run-go.sh <scenario-dir>"
fi

parity_load_manifest "$1"

if [[ -z "${PARITY_GO_BIN:-}" ]]; then
    parity_die "scenario ${PARITY_SCENARIO_NAME}: SDKS does not include 'go'"
fi

if [[ ! -x "${PARITY_GO_BIN}" ]]; then
    parity_die "go binary not found or not executable: ${PARITY_GO_BIN} — run 'composer test:parity:build' first"
fi

TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"
export GOWORK="${GOWORK:-off}"
WORKER_READY_TIMEOUT="${PARITY_WORKER_READY_TIMEOUT:-10}"
WORKER_SHUTDOWN_TIMEOUT="${PARITY_WORKER_SHUTDOWN_TIMEOUT:-10}"

WORKER_LOG="$(mktemp -t parity-go-worker.XXXXXX)"
STARTER_LOG="$(mktemp -t parity-go-starter.XXXXXX)"
WORKER_PID=""
STARTER_PID=""

cleanup_go() {
    if [[ -n "${STARTER_PID}" ]] && kill -0 "${STARTER_PID}" 2>/dev/null; then
        kill -TERM "${STARTER_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${STARTER_PID}" 2>/dev/null || true
    fi
    if [[ -n "${WORKER_PID}" ]] && kill -0 "${WORKER_PID}" 2>/dev/null; then
        kill -TERM "${WORKER_PID}" 2>/dev/null || true
        for _ in $(seq 1 "${WORKER_SHUTDOWN_TIMEOUT}"); do
            kill -0 "${WORKER_PID}" 2>/dev/null || break
            sleep 1
        done
        kill -KILL "${WORKER_PID}" 2>/dev/null || true
    fi
    rm -f "${WORKER_LOG}" "${STARTER_LOG}"
}
trap cleanup_go EXIT INT TERM

parity_log "starting go worker"
"${PARITY_GO_BIN}" \
    --mode worker \
    --address "${TEMPORAL_ADDRESS}" \
    --namespace "${PARITY_NAMESPACE}" \
    --task-queue "${PARITY_GO_TASK_QUEUE}" \
    > "${WORKER_LOG}" 2>&1 &
WORKER_PID=$!

ready=0
for _ in $(seq 1 $((WORKER_READY_TIMEOUT * 10))); do
    if grep -q '^WORKER_READY' "${WORKER_LOG}" 2>/dev/null; then
        ready=1
        break
    fi
    if ! kill -0 "${WORKER_PID}" 2>/dev/null; then
        parity_die "go worker died before becoming ready (last 30 lines):
$(tail -n 30 "${WORKER_LOG}")"
    fi
    sleep 0.1
done
if [[ ${ready} -ne 1 ]]; then
    parity_die "go worker did not announce WORKER_READY within ${WORKER_READY_TIMEOUT}s (last 30 lines):
$(tail -n 30 "${WORKER_LOG}")"
fi
parity_log "go worker ready (pid=${WORKER_PID})"

parity_log "running go starter"
set +e
"${PARITY_GO_BIN}" \
    --mode starter \
    --address "${TEMPORAL_ADDRESS}" \
    --namespace "${PARITY_NAMESPACE}" \
    --task-queue "${PARITY_GO_TASK_QUEUE}" \
    > "${STARTER_LOG}" 2>&1
STARTER_RC=$?
set -e

if [[ ${STARTER_RC} -ne 0 ]]; then
    parity_die "go starter exited with code ${STARTER_RC} (last 30 lines):
$(tail -n 30 "${STARTER_LOG}")"
fi

WORKFLOW_ID="$(grep -E '^WORKFLOW_ID=' "${STARTER_LOG}" | tail -n 1 | cut -d= -f2-)"
if [[ -z "${WORKFLOW_ID:-}" ]]; then
    parity_die "failed to parse WORKFLOW_ID from Go starter output (last 30 lines):
$(tail -n 30 "${STARTER_LOG}")"
fi

RUN_ID=""
if [[ "${PARITY_CAPTURE_RUN:-latest}" == "first" ]]; then
    RUN_ID="$(grep -E '^RUN_ID=' "${STARTER_LOG}" | head -n 1 | cut -d= -f2-)"
    if [[ -z "${RUN_ID}" ]]; then
        parity_die "CAPTURE_RUN=first but Go starter did not emit RUN_ID="
    fi
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} RUN_ID=${RUN_ID} (first run)"
else
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} (latest run)"
fi

parity_log "stopping go worker (pid=${WORKER_PID})"
kill -TERM "${WORKER_PID}" 2>/dev/null || true
for _ in $(seq 1 "${WORKER_SHUTDOWN_TIMEOUT}"); do
    kill -0 "${WORKER_PID}" 2>/dev/null || break
    sleep 1
done
if kill -0 "${WORKER_PID}" 2>/dev/null; then
    parity_warn "go worker did not exit on SIGTERM within ${WORKER_SHUTDOWN_TIMEOUT}s; SIGKILL"
    kill -KILL "${WORKER_PID}" 2>/dev/null || true
fi
WORKER_PID=""

"${SCRIPT_DIR}/dump-history.sh" "${PARITY_NAMESPACE}" "${WORKFLOW_ID}" "${PARITY_FIXTURE_GO}" "${RUN_ID}"
