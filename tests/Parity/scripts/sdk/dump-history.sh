#!/usr/bin/env bash
# Dump a workflow history to JSON. Single canonical wrapper around
# `temporal workflow show --output json`.
#
# Usage:
#   dump-history.sh <namespace> <workflow-id> <out-file> [<run-id>]
#
# When <run-id> is provided and non-empty, --run-id is passed to `temporal
# workflow show`, capturing exactly that run. Without it, Temporal returns
# the latest run for the given workflow id.
#
# Environment:
#   TEMPORAL_BIN     path to `temporal` CLI (default: $PROJECT_ROOT/temporal)
#   TEMPORAL_ADDRESS gRPC address (default: 127.0.0.1:7233)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-dump"
# shellcheck source=../lib/log.sh
. "${SCRIPT_DIR}/../lib/log.sh"

if [[ $# -lt 3 || $# -gt 4 ]]; then
    parity_die "usage: dump-history.sh <namespace> <workflow-id> <out-file> [<run-id>]"
fi

NAMESPACE="$1"
WORKFLOW_ID="$2"
OUT_FILE="$3"
RUN_ID="${4:-}"

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
TEMPORAL_BIN="${TEMPORAL_BIN:-${PROJECT_ROOT}/temporal}"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"

if [[ ! -x "${TEMPORAL_BIN}" ]]; then
    parity_die "temporal binary not found or not executable: ${TEMPORAL_BIN}"
fi

mkdir -p "$(dirname "${OUT_FILE}")"

show_args=(--address "${TEMPORAL_ADDRESS}" --namespace "${NAMESPACE}" workflow show --workflow-id "${WORKFLOW_ID}" --output json)
if [[ -n "${RUN_ID}" ]]; then
    parity_log "dumping run ${RUN_ID} for workflow ${WORKFLOW_ID}"
    show_args+=(--run-id "${RUN_ID}")
else
    parity_log "dumping latest run for workflow ${WORKFLOW_ID}"
fi

"${TEMPORAL_BIN}" "${show_args[@]}" > "${OUT_FILE}"

if [[ ! -s "${OUT_FILE}" ]]; then
    parity_die "history dump is empty: ${OUT_FILE}"
fi

parity_log "wrote ${OUT_FILE} ($(wc -c < "${OUT_FILE}") bytes)"
