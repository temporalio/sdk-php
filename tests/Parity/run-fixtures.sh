#!/usr/bin/env bash
# Phase 1 of the Parity test workflow.
#
# Discovers every scenario.env under tests/Parity/, runs setup-temporal.sh,
# then runs scripts/sdk/run-<sdk>.sh for each SDK declared in $SDKS.
#
# Exit code is non-zero if ANY scenario fails.
set -euo pipefail

PARITY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_DIR="${PARITY_ROOT}/scripts"
PARITY_PHASE="parity-runner"
# shellcheck source=scripts/lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=scripts/lib/manifest.sh
. "${SCRIPT_DIR}/lib/manifest.sh"

TIMEOUT_SECS="${PARITY_FIXTURE_TIMEOUT:-600}"

CURRENT_SDK_PID=""
cleanup_runner() {
    if [[ -n "${CURRENT_SDK_PID}" ]] && kill -0 "${CURRENT_SDK_PID}" 2>/dev/null; then
        parity_log "interrupted — killing scenario pid=${CURRENT_SDK_PID}"
        kill -TERM "${CURRENT_SDK_PID}" 2>/dev/null || true
        sleep 1
        kill -KILL "${CURRENT_SDK_PID}" 2>/dev/null || true
    fi
    pkill -KILL -f 'com.temporal.parity\|rr serve.*tests/Parity\|gradle.*tests/Parity' 2>/dev/null || true
    exit 130
}
trap cleanup_runner INT TERM

parity_log "scanning ${PARITY_ROOT} for scenario manifests"
if [[ -n "${PARITY_FILTER:-}" ]]; then
    parity_log "PARITY_FILTER='${PARITY_FILTER}' — only matching scenarios will run"
fi

SCENARIOS=()
while IFS= read -r line; do
    SCENARIOS+=("$line")
done < <(parity_discover_scenarios "${PARITY_ROOT}")

if [[ ${#SCENARIOS[@]} -eq 0 ]]; then
    parity_log "no scenarios found under ${PARITY_ROOT}"
    parity_log "add one with: ${SCRIPT_DIR}/new-scenario.sh <area> <Name>"
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
    rel="${scenario_dir#${PARITY_ROOT}/}"

    if [[ -n "${PARITY_FILTER:-}" && "${rel}" != *${PARITY_FILTER}* ]]; then
        parity_debug "skipping ${rel} (PARITY_FILTER=${PARITY_FILTER})"
        continue
    fi

    parity_log "==> ${rel}"

    scenario_failed=0

    if ! "${SCRIPT_DIR}/setup-temporal.sh" "${scenario_dir}"; then
        parity_warn "    ${rel}: setup-temporal failed"
        FAILED+=("${rel} (setup)")
        continue
    fi

    for sdk in ${PARITY_SDKS}; do
        if ! run_sdk "${sdk}" "${scenario_dir}"; then
            rc=$?
            parity_warn "    ${rel}: ${sdk} runner failed (exit ${rc})"
            scenario_failed=1
        fi
    done

    if [[ ${scenario_failed} -eq 0 ]]; then
        SUCCEEDED+=("${rel}")
        parity_log "    OK ${rel}"
    else
        FAILED+=("${rel}")
        parity_log "    FAIL ${rel}"
    fi
done

parity_log "----------------------------------------"
parity_log "summary: ${#SUCCEEDED[@]} succeeded, ${#FAILED[@]} failed"
for s in ${SUCCEEDED[@]+"${SUCCEEDED[@]}"}; do parity_log "  OK   ${s}"; done
for f in ${FAILED[@]+"${FAILED[@]}"};       do parity_log "  FAIL ${f}"; done

if [[ ${#FAILED[@]} -gt 0 ]]; then
    exit 1
fi
