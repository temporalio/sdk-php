#!/usr/bin/env bash
# Phase 0 of the Parity test workflow: build every SDK fixture-runner.
#
# For each discovered scenario.env under SCENARIOS_ROOT, iterate over $SDKS and build:
#   java -> one root-level ./gradlew installDist covering :Scenarios:<Name>:java subprojects
#   php  -> composer-managed (sanity-checks vendor/bin/phpunit is present)
#   go   -> GOWORK=off go build per scenario into Runner/build/go-bin/<slug>
#   ts/typescript -> reserved
#
# Exit non-zero if ANY scenario fails to build.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-build"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=lib/manifest.sh
. "${SCRIPT_DIR}/lib/manifest.sh"

# PARITY_ROOT, SCENARIOS_ROOT come from lib/constants.sh (sourced by manifest.sh)
PROJECT_ROOT="$(cd "${PARITY_ROOT}/../../.." && pwd)"
PARITY_TREE_ROOT="$(cd "${PARITY_ROOT}/.." && pwd)"

cleanup_build() {
    pkill -KILL -f 'com.temporal.parity' 2>/dev/null || true
    pkill -KILL -f 'gradle.*tests/Parity' 2>/dev/null || true
    exit 130
}
trap cleanup_build INT TERM

parity_log "scanning ${SCENARIOS_ROOT} for scenario manifests"
if [[ -n "${PARITY_FILTER:-}" ]]; then
    parity_log "PARITY_FILTER='${PARITY_FILTER}' — only matching scenarios will build"
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

if [[ ! -x "${PROJECT_ROOT}/vendor/bin/phpunit" ]]; then
    parity_warn "vendor/bin/phpunit not found at ${PROJECT_ROOT} — Phase 2 'make assert' will fail until 'composer install' is run"
fi

declare -a SUCCEEDED=()
declare -a FAILED=()
declare -a FILTERED=()
declare -a JAVA_TASKS=()
declare -a GO_TASKS=()

for scenario_dir in "${SCENARIOS[@]}"; do
    parity_load_manifest "${scenario_dir}"
    short="$(basename "${scenario_dir}")"

    if [[ -n "${PARITY_FILTER:-}" && "${short}" != *${PARITY_FILTER}* ]]; then
        parity_debug "skipping ${short} (PARITY_FILTER=${PARITY_FILTER})"
        continue
    fi
    FILTERED+=("${scenario_dir}")
    for sdk in ${PARITY_SDKS}; do
        case "${sdk}" in
            java)
                JAVA_TASKS+=(":Scenarios:${short}:java:installDist")
                ;;
            go)
                GO_TASKS+=("./Scenarios/${short}/${GO_DIR}|${PARITY_GO_BIN}")
                ;;
        esac
    done
done

if [[ ${#JAVA_TASKS[@]} -gt 0 ]]; then
    parity_log "==> building ${#JAVA_TASKS[@]} java scenario(s) via root gradle"
    ( cd "${PARITY_ROOT}" && ./gradlew --no-daemon -q --continue "${JAVA_TASKS[@]}" ) || true
fi

if [[ ${#GO_TASKS[@]} -gt 0 ]]; then
    parity_log "==> building ${#GO_TASKS[@]} go scenario(s)"
    mkdir -p "${PARITY_ROOT}/build/go-bin"
    for entry in "${GO_TASKS[@]}"; do
        go_pkg="${entry%%|*}"
        go_out="${entry##*|}"
        parity_log "    go build ${go_pkg}"
        # go.mod lives at the parity tree root (one above Runner/ and Scenarios/).
        # GOWORK=off keeps this build self-contained — the parent temporalio/go.work
        # points at a local sdk-go for velox builds and would otherwise reject this
        # module as out-of-workspace.
        ( cd "${PARITY_TREE_ROOT}" && GOWORK=off go build -o "${go_out}" "${go_pkg}" ) || parity_warn "    go build failed for ${go_pkg}"
    done
fi

for scenario_dir in ${FILTERED[@]+"${FILTERED[@]}"}; do
    parity_load_manifest "${scenario_dir}"
    short="$(basename "${scenario_dir}")"

    parity_log "==> ${short}"

    scenario_failed=0
    for sdk in ${PARITY_SDKS}; do
        case "${sdk}" in
            java)
                install_dir="${PARITY_JAVA_DIR}/build/install"
                if [[ -d "${install_dir}" && -n "$(ls -A "${install_dir}" 2>/dev/null)" ]]; then
                    parity_log "    java: installDist present"
                else
                    scenario_failed=1
                    parity_warn "    ${short}: java installDist output missing/empty"
                fi
                ;;
            php)
                parity_log "    php: no per-scenario build (composer-managed)"
                ;;
            go)
                if [[ -x "${PARITY_GO_BIN}" ]]; then
                    parity_log "    go: binary present (${PARITY_GO_BIN#${PARITY_ROOT}/})"
                else
                    scenario_failed=1
                    parity_warn "    ${short}: go binary missing at ${PARITY_GO_BIN}"
                fi
                ;;
            *)
                parity_warn "    ${short}: unknown SDK '${sdk}' (manifest validation should have caught this)"
                scenario_failed=1
                ;;
        esac
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
