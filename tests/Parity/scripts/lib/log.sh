#!/usr/bin/env bash
# Shared logging helpers for the Parity test tier scripts.
# Source from any script:  . "$(dirname "$0")/../lib/log.sh"  (path varies)

set -u

if [[ -n "${PARITY_LOG_SOURCED:-}" ]]; then
    return 0
fi
PARITY_LOG_SOURCED=1

PARITY_PHASE="${PARITY_PHASE:-parity}"

parity_log() {
    echo "[${PARITY_PHASE}] $*"
}

parity_warn() {
    echo "[${PARITY_PHASE}] WARN: $*" >&2
}

parity_die() {
    echo "[${PARITY_PHASE}] ERROR: $*" >&2
    exit 1
}

parity_debug() {
    if [[ "${PARITY_DEBUG:-0}" == "1" ]]; then
        echo "[${PARITY_PHASE}] DEBUG: $*" >&2
    fi
}
