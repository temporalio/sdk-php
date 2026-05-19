#!/usr/bin/env bash
# Phase 1 of the Parity test workflow.
#
# Starts the shared Temporal dev server (once, via setup-temporal-once.sh),
# discovers every scenario.env under tests/Parity/Scenarios/, runs
# scripts/sdk/run-<sdk>.sh for each SDK declared in $SDKS, and stops the server
# on exit IFF this run started it (PID-marker convention — see teardown-temporal.sh).
#
# Exit code is non-zero if ANY scenario fails.
set -euo pipefail

PARITY_ROOT_HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_DIR="${PARITY_ROOT_HERE}/scripts"
PARITY_PHASE="parity-runner"
# shellcheck source=scripts/lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=scripts/lib/manifest.sh
. "${SCRIPT_DIR}/lib/manifest.sh"

# SCENARIOS_ROOT and PARITY_ROOT come from lib/constants.sh (sourced via manifest.sh)
TIMEOUT_SECS="${PARITY_FIXTURE_TIMEOUT:-60}"

CURRENT_SDK_PID=""

teardown_temporal() {
    "${SCRIPT_DIR}/teardown-temporal.sh" || true
}

cleanup_on_signal() {
    if [[ -n "${CURRENT_SDK_PID}" ]] && kill -0 "${CURRENT_SDK_PID}" 2>/dev/null; then
        parity_log "interrupted — killing scenario pid=${CURRENT_SDK_PID}"
        kill -TERM "${CURRENT_SDK_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${CURRENT_SDK_PID}" 2>/dev/null || true
    fi
    pkill -KILL -f 'com.temporal.parity\|rr serve.*tests/Parity\|gradle.*tests/Parity\|tests/Parity/Runner/build/go-bin/' 2>/dev/null || true
    teardown_temporal
    exit 130
}
trap cleanup_on_signal INT TERM
trap teardown_temporal EXIT

if ! "${SCRIPT_DIR}/setup-temporal-once.sh"; then
    parity_die "setup-temporal-once.sh failed"
fi

parity_log "scanning ${SCENARIOS_ROOT} for scenario manifests"
if [[ -n "${PARITY_FILTER:-}" ]]; then
    parity_log "PARITY_FILTER='${PARITY_FILTER}' — only matching scenarios will run"
fi

SCENARIOS=()
while IFS= read -r line; do
    SCENARIOS+=("$line")
done < <(parity_discover_scenarios "${SCENARIOS_ROOT}")

if [[ ${#SCENARIOS[@]} -eq 0 ]]; then
    parity_log "no scenarios found under ${SCENARIOS_ROOT}"
    parity_log "add one with: ${SCRIPT_DIR}/new-scenario.sh <Name>"
    exit 0
fi

declare -a SUCCEEDED=()
declare -a FAILED=()

run_sdk() {
    local sdk="$1" scenario_dir="$2"
    timeout "${TIMEOUT_SECS}" "${SCRIPT_DIR}/sdk/run-${sdk}.sh" "${scenario_dir}" &
    CURRENT_SDK_PID=$!
    wait "${CURRENT_SDK_PID}"
    local rc=$?
    CURRENT_SDK_PID=""
    return $rc
}

for scenario_dir in "${SCENARIOS[@]}"; do
    parity_load_manifest "${scenario_dir}"
    short="$(basename "${scenario_dir}")"

    if [[ -n "${PARITY_FILTER:-}" && "${short}" != *${PARITY_FILTER}* ]]; then
        parity_debug "skipping ${short} (PARITY_FILTER=${PARITY_FILTER})"
        continue
    fi

    parity_log "==> ${short}"

    scenario_failed=0
    for sdk in ${PARITY_SDKS}; do
        if ! run_sdk "${sdk}" "${scenario_dir}"; then
            rc=$?
            parity_warn "    ${short}: ${sdk} runner failed (exit ${rc})"
            scenario_failed=1
        fi
    done

    if [[ ${scenario_failed} -eq 0 ]]; then
        SUCCEEDED+=("${short}")
        parity_log "    OK ${short}"
    else
        FAILED+=("${short}")
        parity_log "    FAIL ${short}"
    fi
done

parity_log "----------------------------------------"
parity_log "summary: ${#SUCCEEDED[@]} succeeded, ${#FAILED[@]} failed"
for s in ${SUCCEEDED[@]+"${SUCCEEDED[@]}"}; do parity_log "  OK   ${s}"; done
for f in ${FAILED[@]+"${FAILED[@]}"};       do parity_log "  FAIL ${f}"; done

if [[ ${#FAILED[@]} -gt 0 ]]; then
    exit 1
fi
