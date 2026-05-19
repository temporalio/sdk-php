#!/usr/bin/env bash
# Run the Go fixture-runner for a scenario. Captures WORKFLOW_ID from stdout,
# then dumps the workflow's history to FIXTURE_GO.
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

LOG="$(mktemp -t parity-go.XXXXXX)"
GO_CHILD_PID=""

cleanup_go() {
    if [[ -n "${GO_CHILD_PID}" ]] && kill -0 "${GO_CHILD_PID}" 2>/dev/null; then
        parity_log "interrupted — killing go pid=${GO_CHILD_PID}"
        kill -TERM "${GO_CHILD_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${GO_CHILD_PID}" 2>/dev/null || true
    fi
    rm -f "${LOG}"
}
trap cleanup_go EXIT INT TERM

parity_log "running ${PARITY_GO_BIN}"
(
    exec "${PARITY_GO_BIN}" \
        --address "${TEMPORAL_ADDRESS}" \
        --namespace "${PARITY_NAMESPACE}" \
        --task-queue "${PARITY_GO_TASK_QUEUE}" 2>&1
) | tee "${LOG}" &
GO_CHILD_PID=$!
wait "${GO_CHILD_PID}"

WORKFLOW_ID="$(grep -E '^WORKFLOW_ID=' "${LOG}" | tail -n 1 | cut -d= -f2-)"
if [[ -z "${WORKFLOW_ID:-}" ]]; then
    parity_die "failed to parse WORKFLOW_ID from Go output (last 30 lines below)
$(tail -n 30 "${LOG}")"
fi

RUN_ID=""
if [[ "${PARITY_CAPTURE_RUN:-latest}" == "first" ]]; then
    RUN_ID="$(grep -E '^RUN_ID=' "${LOG}" | head -n 1 | cut -d= -f2-)"
    if [[ -z "${RUN_ID}" ]]; then
        parity_die "CAPTURE_RUN=first but Go output did not emit RUN_ID= (last 30 lines below)
$(tail -n 30 "${LOG}")"
    fi
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} RUN_ID=${RUN_ID} (first run)"
else
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} (latest run)"
fi

"${SCRIPT_DIR}/dump-history.sh" "${PARITY_NAMESPACE}" "${WORKFLOW_ID}" "${PARITY_FIXTURE_GO}" "${RUN_ID}"
