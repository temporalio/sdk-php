#!/usr/bin/env bash
# Idempotently ensure the shared Temporal dev server is up and the namespace exists.
#
# Usage:
#   setup-temporal.sh <scenario-dir>
#
# Reads scenario.env via lib/manifest.sh. Environment overrides:
#   TEMPORAL_BIN     path to `temporal` CLI (default: $PROJECT_ROOT/temporal)
#   TEMPORAL_ADDRESS gRPC address (default: 127.0.0.1:7233)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-setup"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=lib/manifest.sh
. "${SCRIPT_DIR}/lib/manifest.sh"

if [[ $# -lt 1 ]]; then
    parity_die "usage: setup-temporal.sh <scenario-dir>"
fi

parity_load_manifest "$1"

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TEMPORAL_BIN="${TEMPORAL_BIN:-${PROJECT_ROOT}/temporal}"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"

if [[ ! -x "${TEMPORAL_BIN}" ]]; then
    parity_die "temporal binary not found or not executable: ${TEMPORAL_BIN}
hint: run 'composer get:binaries' from the project root, or set TEMPORAL_BIN explicitly"
fi

port="${TEMPORAL_ADDRESS##*:}"
if ! lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
    parity_log "temporal not listening on ${TEMPORAL_ADDRESS}; starting dev server in background"
    mkdir -p /tmp/temporal-parity
    "${TEMPORAL_BIN}" server start-dev --http-port 7243 --log-level warn \
        > /tmp/temporal-parity/server.log 2>&1 &
    sleep 4
else
    parity_log "temporal already listening on ${TEMPORAL_ADDRESS}"
fi

if "${TEMPORAL_BIN}" --address "${TEMPORAL_ADDRESS}" operator namespace describe \
        --namespace "${PARITY_NAMESPACE}" >/dev/null 2>&1; then
    parity_log "namespace ${PARITY_NAMESPACE} already exists"
else
    parity_log "creating namespace ${PARITY_NAMESPACE}"
    "${TEMPORAL_BIN}" --address "${TEMPORAL_ADDRESS}" operator namespace create \
        --namespace "${PARITY_NAMESPACE}"
fi

