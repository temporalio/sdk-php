#!/usr/bin/env bash
# Run the PHP fixture-runner for a scenario via the shared CLI launcher,
# then dump the resulting caller-workflow history.
#
# Usage:
#   run-php.sh <scenario-dir>
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-php"
# shellcheck source=../lib/log.sh
. "${SCRIPT_DIR}/../lib/log.sh"
# shellcheck source=../lib/manifest.sh
. "${SCRIPT_DIR}/../lib/manifest.sh"

if [[ $# -lt 1 ]]; then
    parity_die "usage: run-php.sh <scenario-dir>"
fi

parity_load_manifest "$1"

if [[ -z "${PARITY_PHP_FIXTURE_RUNNER:-}" ]]; then
    parity_die "scenario ${PARITY_SCENARIO_NAME}: SDKS does not include 'php'"
fi

if [[ ! -f "${PARITY_PHP_FIXTURE_RUNNER}" ]]; then
    parity_die "PHP fixture runner not found: ${PARITY_PHP_FIXTURE_RUNNER}"
fi

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"
LAUNCHER="${SCRIPT_DIR}/../php/run-scenario.php"

if [[ ! -f "${LAUNCHER}" ]]; then
    parity_die "PHP CLI launcher not found: ${LAUNCHER}"
fi

LOG="$(mktemp -t parity-php.XXXXXX)"
PHP_CHILD_PID=""

cleanup_php() {
    if [[ -n "${PHP_CHILD_PID}" ]] && kill -0 "${PHP_CHILD_PID}" 2>/dev/null; then
        parity_log "interrupted — killing php pid=${PHP_CHILD_PID}"
        kill -TERM "${PHP_CHILD_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${PHP_CHILD_PID}" 2>/dev/null || true
    fi
    pkill -KILL -f 'rr serve.*tests/Parity' 2>/dev/null || true
    rm -f "${LOG}"
}
trap cleanup_php EXIT INT TERM

parity_log "running php launcher: ${LAUNCHER}"
(
    cd "${PROJECT_ROOT}"
    exec php "${LAUNCHER}" \
        --scenario "${PARITY_SCENARIO_DIR}" \
        --address "${TEMPORAL_ADDRESS}" \
        --namespace "${PARITY_NAMESPACE}" \
        --task-queue "${PARITY_PHP_TASK_QUEUE}" 2>&1
) | tee "${LOG}" &
PHP_CHILD_PID=$!
wait "${PHP_CHILD_PID}"

WORKFLOW_ID="$(grep -E '^WORKFLOW_ID=' "${LOG}" | tail -n 1 | cut -d= -f2-)"
if [[ -z "${WORKFLOW_ID:-}" ]]; then
    parity_die "failed to parse WORKFLOW_ID from PHP output (last 30 lines below)
$(tail -n 30 "${LOG}")"
fi

RUN_ID=""
if [[ "${PARITY_CAPTURE_RUN:-latest}" == "first" ]]; then
    RUN_ID="$(grep -E '^RUN_ID=' "${LOG}" | head -n 1 | cut -d= -f2-)"
    if [[ -z "${RUN_ID}" ]]; then
        parity_die "CAPTURE_RUN=first but PHP output did not emit RUN_ID= (last 30 lines below)
$(tail -n 30 "${LOG}")"
    fi
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} RUN_ID=${RUN_ID} (first run)"
else
    parity_log "captured WORKFLOW_ID=${WORKFLOW_ID} (latest run)"
fi

"${SCRIPT_DIR}/dump-history.sh" "${PARITY_NAMESPACE}" "${WORKFLOW_ID}" "${PARITY_FIXTURE_PHP}" "${RUN_ID}"
