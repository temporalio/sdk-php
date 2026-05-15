#!/usr/bin/env bash
# Run the Java fixture-runner for a scenario. Captures WORKFLOW_ID from stdout,
# then dumps the workflow's history to FIXTURE_JAVA.
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

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"

LOG="$(mktemp -t parity-java.XXXXXX)"
JAVA_CHILD_PID=""

cleanup_java() {
    if [[ -n "${JAVA_CHILD_PID}" ]] && kill -0 "${JAVA_CHILD_PID}" 2>/dev/null; then
        parity_log "interrupted — killing java pid=${JAVA_CHILD_PID}"
        kill -TERM "${JAVA_CHILD_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${JAVA_CHILD_PID}" 2>/dev/null || true
    fi
    rm -f "${LOG}"
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

parity_log "running ${BIN_SCRIPT}"
(
    exec "${BIN_SCRIPT}" \
        --address "${TEMPORAL_ADDRESS}" \
        --namespace "${PARITY_NAMESPACE}" \
        --task-queue "${PARITY_TASK_QUEUE}" 2>&1
) | tee "${LOG}" &
JAVA_CHILD_PID=$!
wait "${JAVA_CHILD_PID}"

WORKFLOW_ID="$(grep -E '^WORKFLOW_ID=' "${LOG}" | tail -n 1 | cut -d= -f2-)"
if [[ -z "${WORKFLOW_ID:-}" ]]; then
    parity_die "failed to parse WORKFLOW_ID from Java output (last 30 lines below)
$(tail -n 30 "${LOG}")"
fi

RUN_ID=""
if [[ "${PARITY_CAPTURE_RUN:-latest}" == "first" ]]; then
    RUN_ID="$(grep -E '^RUN_ID=' "${LOG}" | head -n 1 | cut -d= -f2-)"
    if [[ -z "${RUN_ID}" ]]; then
        parity_die "CAPTURE_RUN=first but Java output did not emit RUN_ID= (last 30 lines below)
$(tail -n 30 "${LOG}")"
    fi
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} RUN_ID=${RUN_ID} (first run)"
else
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} (latest run)"
fi

"${SCRIPT_DIR}/dump-history.sh" "${PARITY_NAMESPACE}" "${WORKFLOW_ID}" "${PARITY_FIXTURE_JAVA}" "${RUN_ID}"
