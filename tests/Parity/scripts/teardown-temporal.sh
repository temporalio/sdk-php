#!/usr/bin/env bash
# Stop the parity-owned Temporal dev server, if any.
#
# This script is a no-op when /tmp/temporal-parity/server.pid does not exist —
# it only kills the server if setup-temporal-once.sh started it (PID marker
# convention), so a server you launched yourself in another terminal survives.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-teardown"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"

PARITY_TEMPORAL_PID="/tmp/temporal-parity/server.pid"

if [[ ! -f "${PARITY_TEMPORAL_PID}" ]]; then
    parity_debug "no PID marker at ${PARITY_TEMPORAL_PID}; temporal was not started by this run"
    exit 0
fi

server_pid="$(cat "${PARITY_TEMPORAL_PID}" 2>/dev/null || true)"
rm -f "${PARITY_TEMPORAL_PID}"

if [[ -z "${server_pid}" ]]; then
    parity_warn "PID marker was empty; nothing to stop"
    exit 0
fi

if ! kill -0 "${server_pid}" 2>/dev/null; then
    parity_log "temporal pid=${server_pid} already gone"
    exit 0
fi

parity_log "stopping temporal pid=${server_pid}"
kill -TERM "${server_pid}" 2>/dev/null || true

for _ in {1..10}; do
    if ! kill -0 "${server_pid}" 2>/dev/null; then
        parity_log "temporal pid=${server_pid} stopped"
        exit 0
    fi
    sleep 0.5
done

parity_warn "temporal pid=${server_pid} did not exit on SIGTERM; sending SIGKILL"
kill -KILL "${server_pid}" 2>/dev/null || true
