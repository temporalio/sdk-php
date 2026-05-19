#!/usr/bin/env bash
# Phase 0 of the Parity test workflow: build every SDK fixture-runner.
#
# For each discovered scenario.env, iterate over $SDKS and build each one:
#   java  -> ./gradlew -q build in $JAVA_DIR
#   php   -> sanity-check vendor/bin/phpunit is present
#   go|ts -> reserved (not yet supported, error out)
#
# Exit non-zero if ANY scenario fails to build.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PROJECT_ROOT="$(cd "${PARITY_ROOT}/../.." && pwd)"
PARITY_PHASE="parity-build"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=lib/manifest.sh
. "${SCRIPT_DIR}/lib/manifest.sh"

cleanup_build() {
    pkill -KILL -f 'com.temporal.parity' 2>/dev/null || true
    pkill -KILL -f 'gradle.*tests/Parity' 2>/dev/null || true
    exit 130
}
trap cleanup_build INT TERM

parity_log "scanning ${PARITY_ROOT} for scenario manifests"
if [[ -n "${PARITY_FILTER:-}" ]]; then
    parity_log "PARITY_FILTER='${PARITY_FILTER}' — only matching scenarios will build"
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
    rel="${scenario_dir#${PARITY_ROOT}/}"

    if [[ -n "${PARITY_FILTER:-}" && "${rel}" != *${PARITY_FILTER}* ]]; then
        parity_debug "skipping ${rel} (PARITY_FILTER=${PARITY_FILTER})"
        continue
    fi
    FILTERED+=("${scenario_dir}")
    for sdk in ${PARITY_SDKS}; do
        case "${sdk}" in
            java)
                JAVA_TASKS+=(":${rel//\//:}:java:installDist")
                ;;
            go)
                GO_TASKS+=("./${rel}/${GO_DIR}|${PARITY_GO_BIN}")
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
        # GOWORK=off keeps this build self-contained — the parent
        # /Users/.../temporalio/go.work points at a local sdk-go used by velox
        # builds and would otherwise refuse to build packages outside that workspace.
        ( cd "${PARITY_ROOT}" && GOWORK=off go build -o "${go_out}" "${go_pkg}" ) || parity_warn "    go build failed for ${go_pkg}"
    done
fi

for scenario_dir in ${FILTERED[@]+"${FILTERED[@]}"}; do
    parity_load_manifest "${scenario_dir}"
    rel="${scenario_dir#${PARITY_ROOT}/}"

    parity_log "==> ${rel}"

    scenario_failed=0
    for sdk in ${PARITY_SDKS}; do
        case "${sdk}" in
            java)
                install_dir="${PARITY_JAVA_DIR}/build/install"
                if [[ -d "${install_dir}" && -n "$(ls -A "${install_dir}" 2>/dev/null)" ]]; then
                    parity_log "    java: installDist present"
                else
                    scenario_failed=1
                    parity_warn "    ${rel}: java installDist output missing/empty"
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
                    parity_warn "    ${rel}: go binary missing at ${PARITY_GO_BIN}"
                fi
                ;;
            *)
                parity_warn "    ${rel}: unknown SDK '${sdk}' (manifest validation should have caught this)"
                scenario_failed=1
                ;;
        esac
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
