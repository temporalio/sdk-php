#!/usr/bin/env bash
# Run the Java fixture-runner for a scenario as TWO coordinated JVMs — one worker
# (background, blocks on shutdown hook) and one starter (foreground, runs the
# workflow then exits). This mirrors the canonical samples-java worker/starter
# split and avoids the "halt mid-poll" race that flakes shared-task-queue runs.
#
# Usage:
#   run-java.sh <scenario-dir>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-java"
# shellcheck source=../lib/log.sh
. "${SCRIPT_DIR}/../lib/log.sh"
# shellcheck source=../lib/manifest.sh
. "${SCRIPT_DIR}/../lib/manifest.sh"

if [[ $# -lt 1 ]]; then
    parity_die "usage: run-java.sh <scenario-dir>"
fi

parity_load_manifest "$1"

if [[ -z "${PARITY_JAVA_DIR:-}" ]]; then
    parity_die "scenario ${PARITY_SCENARIO_NAME}: SDKS does not include 'java'"
fi

if [[ ! -d "${PARITY_JAVA_DIR}" ]]; then
    parity_die "java dir not found: ${PARITY_JAVA_DIR}"
fi

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../../.." && pwd)"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"
WORKER_READY_TIMEOUT="${PARITY_WORKER_READY_TIMEOUT:-10}"
WORKER_SHUTDOWN_TIMEOUT="${PARITY_WORKER_SHUTDOWN_TIMEOUT:-10}"

WORKER_LOG="$(mktemp -t parity-java-worker.XXXXXX)"
STARTER_LOG="$(mktemp -t parity-java-starter.XXXXXX)"
WORKER_PID=""
STARTER_PID=""

cleanup_java() {
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
trap cleanup_java EXIT INT TERM

# Locate the installDist'ed bin script (single non-.bat entry under build/install/*/bin)
BIN_DIR_GLOB=("${PARITY_JAVA_DIR}"/build/install/*/bin)
BIN_DIR="${BIN_DIR_GLOB[0]}"
if [[ ! -d "${BIN_DIR}" ]]; then
    parity_die "installDist output not found at ${PARITY_JAVA_DIR}/build/install/*/bin — run 'composer test:parity:build' first"
fi
BIN_SCRIPT=""
for f in "${BIN_DIR}"/*; do
    case "${f}" in
        *.bat) ;;
        *) BIN_SCRIPT="${f}"; break ;;
    esac
done
if [[ -z "${BIN_SCRIPT}" || ! -x "${BIN_SCRIPT}" ]]; then
    parity_die "no executable launcher found in ${BIN_DIR}"
fi

parity_log "starting java worker"
"${BIN_SCRIPT}" \
    --mode worker \
    --address "${TEMPORAL_ADDRESS}" \
    --namespace "${PARITY_NAMESPACE}" \
    --task-queue "${PARITY_JAVA_TASK_QUEUE}" \
    > "${WORKER_LOG}" 2>&1 &
WORKER_PID=$!

# Wait for the worker to announce readiness.
ready=0
for _ in $(seq 1 $((WORKER_READY_TIMEOUT * 10))); do
    if grep -q '^WORKER_READY' "${WORKER_LOG}" 2>/dev/null; then
        ready=1
        break
    fi
    if ! kill -0 "${WORKER_PID}" 2>/dev/null; then
        parity_die "java worker died before becoming ready (last 30 lines):
$(tail -n 30 "${WORKER_LOG}")"
    fi
    sleep 0.1
done
if [[ ${ready} -ne 1 ]]; then
    parity_die "java worker did not announce WORKER_READY within ${WORKER_READY_TIMEOUT}s (last 30 lines):
$(tail -n 30 "${WORKER_LOG}")"
fi
parity_log "java worker ready (pid=${WORKER_PID})"

parity_log "running java starter"
"${BIN_SCRIPT}" \
    --mode starter \
    --address "${TEMPORAL_ADDRESS}" \
    --namespace "${PARITY_NAMESPACE}" \
    --task-queue "${PARITY_JAVA_TASK_QUEUE}" \
    > "${STARTER_LOG}" 2>&1 &
STARTER_PID=$!
wait "${STARTER_PID}"
STARTER_RC=$?
STARTER_PID=""

if [[ ${STARTER_RC} -ne 0 ]]; then
    parity_die "java starter exited with code ${STARTER_RC} (last 30 lines):
$(tail -n 30 "${STARTER_LOG}")"
fi

WORKFLOW_ID="$(grep -E '^WORKFLOW_ID=' "${STARTER_LOG}" | tail -n 1 | cut -d= -f2-)"
if [[ -z "${WORKFLOW_ID:-}" ]]; then
    parity_die "failed to parse WORKFLOW_ID from Java starter output (last 30 lines):
$(tail -n 30 "${STARTER_LOG}")"
fi

RUN_ID=""
if [[ "${PARITY_CAPTURE_RUN:-latest}" == "first" ]]; then
    RUN_ID="$(grep -E '^RUN_ID=' "${STARTER_LOG}" | head -n 1 | cut -d= -f2-)"
    if [[ -z "${RUN_ID}" ]]; then
        parity_die "CAPTURE_RUN=first but Java starter did not emit RUN_ID="
    fi
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} RUN_ID=${RUN_ID} (first run)"
else
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} (latest run)"
fi

# Gracefully stop the worker before dumping history so the next scenario starts
# with a clean task queue (cleanup_java would catch this too, but doing it
# explicitly here gives Temporal time to register the disconnect).
parity_log "stopping java worker (pid=${WORKER_PID})"
kill -TERM "${WORKER_PID}" 2>/dev/null || true
for _ in $(seq 1 "${WORKER_SHUTDOWN_TIMEOUT}"); do
    kill -0 "${WORKER_PID}" 2>/dev/null || break
    sleep 1
done
if kill -0 "${WORKER_PID}" 2>/dev/null; then
    parity_warn "java worker did not exit on SIGTERM within ${WORKER_SHUTDOWN_TIMEOUT}s; SIGKILL"
    kill -KILL "${WORKER_PID}" 2>/dev/null || true
fi
WORKER_PID=""

"${SCRIPT_DIR}/dump-history.sh" "${PARITY_NAMESPACE}" "${WORKFLOW_ID}" "${PARITY_FIXTURE_JAVA}" "${RUN_ID}"
