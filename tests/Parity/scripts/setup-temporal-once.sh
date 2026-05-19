#!/usr/bin/env bash
# Idempotently start the shared Temporal dev server and ensure the parity namespace exists.
#
# Lifecycle contract:
#   - If Temporal is already listening on ${TEMPORAL_ADDRESS}, leave it alone (and do NOT write
#     the PID marker — we want teardown-temporal.sh to be a no-op in that case).
#   - If Temporal is not listening, start `temporal server start-dev` in the background and
#     write its PID to /tmp/temporal-parity/server.pid. teardown-temporal.sh uses that marker
#     to know whether THIS run owns the server.
#   - Always ensure the shared `${PARITY_NAMESPACE}` namespace exists.
#
# Environment:
#   TEMPORAL_BIN     path to `temporal` CLI (default: $PROJECT_ROOT/temporal)
#   TEMPORAL_ADDRESS gRPC address           (default: 127.0.0.1:7233)
#   PARITY_NAMESPACE shared namespace       (default: parity)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-setup"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=lib/constants.sh
. "${SCRIPT_DIR}/lib/constants.sh"

PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TEMPORAL_BIN="${TEMPORAL_BIN:-${PROJECT_ROOT}/temporal}"
TEMPORAL_ADDRESS="${TEMPORAL_ADDRESS:-127.0.0.1:7233}"
PARITY_TEMPORAL_DIR="/tmp/temporal-parity"
PARITY_TEMPORAL_PID="${PARITY_TEMPORAL_DIR}/server.pid"

if [[ ! -x "${TEMPORAL_BIN}" ]]; then
    parity_die "temporal binary not found or not executable: ${TEMPORAL_BIN}
hint: run 'composer get:binaries' from the project root, or set TEMPORAL_BIN explicitly"
fi

port="${TEMPORAL_ADDRESS##*:}"
if lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
    parity_log "temporal already listening on ${TEMPORAL_ADDRESS}; leaving as-is"
    if [[ -f "${PARITY_TEMPORAL_PID}" ]]; then
        parity_debug "stale PID marker ${PARITY_TEMPORAL_PID} removed — server we tracked is no longer ours"
        rm -f "${PARITY_TEMPORAL_PID}"
    fi
else
    mkdir -p "${PARITY_TEMPORAL_DIR}"
    parity_log "starting temporal dev server in background"
    "${TEMPORAL_BIN}" server start-dev --http-port 7243 --log-level warn \
        > "${PARITY_TEMPORAL_DIR}/server.log" 2>&1 &
    server_pid=$!
    echo "${server_pid}" > "${PARITY_TEMPORAL_PID}"

    parity_log "waiting up to 10s for temporal to accept connections on ${TEMPORAL_ADDRESS}"
    for _ in {1..20}; do
        if lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
            parity_log "temporal up (pid=${server_pid})"
            break
        fi
        sleep 0.5
    done
    if ! lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
        parity_die "temporal failed to start within 10s; see ${PARITY_TEMPORAL_DIR}/server.log"
    fi
fi

if "${TEMPORAL_BIN}" --address "${TEMPORAL_ADDRESS}" operator namespace describe \
        --namespace "${PARITY_NAMESPACE}" >/dev/null 2>&1; then
    parity_log "namespace ${PARITY_NAMESPACE} already exists"
else
    parity_log "creating namespace ${PARITY_NAMESPACE}"
    "${TEMPORAL_BIN}" --address "${TEMPORAL_ADDRESS}" operator namespace create \
        --namespace "${PARITY_NAMESPACE}"
fi
