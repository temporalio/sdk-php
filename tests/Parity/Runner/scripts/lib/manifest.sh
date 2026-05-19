#!/usr/bin/env bash
# Loads a per-scenario manifest (scenario.env) and validates required keys.
#
# Usage:
#   . "$(dirname "$0")/../lib/log.sh"
#   . "$(dirname "$0")/../lib/manifest.sh"
#   parity_load_manifest <scenario-dir>
#
# After a successful load, exports:
#   PARITY_SCENARIO_DIR        absolute path to the scenario dir
#   PARITY_SCENARIO_NAME       e.g. "Basic/HelloWorld"
#   PARITY_SDKS                whitespace-separated SDK list
#   PARITY_JAVA_DIR            absolute path (if java is in SDKS)
#   PARITY_PHP_FIXTURE_RUNNER  absolute path (if php is in SDKS)
#   PARITY_PHP_WORKFLOW_TYPE   workflow type string
#   PARITY_FIXTURE_JAVA        absolute path to expected java fixture
#   PARITY_FIXTURE_PHP         absolute path to expected php fixture
#   PARITY_GO_DIR              absolute path (if go is in SDKS)
#   PARITY_GO_BIN              absolute path to built go binary (if go is in SDKS)
#   PARITY_FIXTURE_GO          absolute path to expected go fixture
#   PARITY_CAPTURE_RUN         "latest" (default) or "first"
#
# The shared namespace and per-language task queues live in lib/constants.sh and
# are exported here as `PARITY_NAMESPACE`, `PARITY_PHP_TASK_QUEUE`,
# `PARITY_JAVA_TASK_QUEUE`, `PARITY_GO_TASK_QUEUE`. Per-scenario `NAMESPACE=` or
# `TASK_QUEUE=` lines in scenario.env are accepted for one release with a warning;
# they are dropped from the schema.

set -u

if [[ -n "${PARITY_MANIFEST_SOURCED:-}" ]]; then
    return 0
fi
PARITY_MANIFEST_SOURCED=1

_PARITY_MANIFEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=constants.sh
. "${_PARITY_MANIFEST_DIR}/constants.sh"

parity_load_manifest() {
    local scenario_dir="${1:-}"
    if [[ -z "${scenario_dir}" ]]; then
        parity_die "parity_load_manifest: scenario dir argument missing"
    fi

    if [[ ! -d "${scenario_dir}" ]]; then
        parity_die "parity_load_manifest: not a directory: ${scenario_dir}"
    fi

    local manifest="${scenario_dir}/scenario.env"
    if [[ ! -f "${manifest}" ]]; then
        parity_die "parity_load_manifest: missing manifest: ${manifest}"
    fi

    unset SCENARIO_NAME NAMESPACE TASK_QUEUE SDKS \
          JAVA_DIR PHP_FIXTURE_RUNNER PHP_WORKFLOW_TYPE \
          GO_DIR GO_BIN \
          FIXTURE_JAVA FIXTURE_PHP FIXTURE_GO CAPTURE_RUN
    unset PARITY_JAVA_DIR PARITY_FIXTURE_JAVA \
          PARITY_PHP_FIXTURE_RUNNER PARITY_PHP_WORKFLOW_TYPE PARITY_FIXTURE_PHP \
          PARITY_GO_DIR PARITY_GO_BIN PARITY_FIXTURE_GO \
          PARITY_CAPTURE_RUN

    # shellcheck disable=SC1090
    . "${manifest}"

    local required=(SCENARIO_NAME SDKS)
    local missing=()
    local key
    for key in "${required[@]}"; do
        if [[ -z "${!key:-}" ]]; then
            missing+=("${key}")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        parity_die "manifest ${manifest} is missing required keys: ${missing[*]}"
    fi

    if [[ -n "${NAMESPACE:-}" ]]; then
        parity_warn "manifest ${manifest}: legacy 'NAMESPACE=' key is ignored — namespace is now shared (lib/constants.sh)"
    fi
    if [[ -n "${TASK_QUEUE:-}" ]]; then
        parity_warn "manifest ${manifest}: legacy 'TASK_QUEUE=' key is ignored — task queues are per-SDK (lib/constants.sh)"
    fi

    local abs_scenario
    abs_scenario="$(cd "${scenario_dir}" && pwd)"

    local capture_run="${CAPTURE_RUN:-latest}"
    if [[ "${capture_run}" != "latest" && "${capture_run}" != "first" ]]; then
        parity_die "manifest ${manifest}: CAPTURE_RUN must be 'latest' or 'first' (got '${capture_run}')"
    fi

    export PARITY_SCENARIO_DIR="${abs_scenario}"
    export PARITY_SCENARIO_NAME="${SCENARIO_NAME}"
    export PARITY_SDKS="${SDKS}"
    export PARITY_CAPTURE_RUN="${capture_run}"

    local sdk
    for sdk in ${PARITY_SDKS}; do
        case "${sdk}" in
            java)
                if [[ -z "${JAVA_DIR:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'java' but JAVA_DIR is empty"
                fi
                export PARITY_JAVA_DIR="${abs_scenario}/${JAVA_DIR}"
                if [[ -z "${FIXTURE_JAVA:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'java' but FIXTURE_JAVA is empty"
                fi
                export PARITY_FIXTURE_JAVA="${abs_scenario}/${FIXTURE_JAVA}"
                ;;
            php)
                if [[ -z "${PHP_FIXTURE_RUNNER:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'php' but PHP_FIXTURE_RUNNER is empty"
                fi
                if [[ -z "${PHP_WORKFLOW_TYPE:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'php' but PHP_WORKFLOW_TYPE is empty"
                fi
                export PARITY_PHP_FIXTURE_RUNNER="${abs_scenario}/${PHP_FIXTURE_RUNNER}"
                export PARITY_PHP_WORKFLOW_TYPE="${PHP_WORKFLOW_TYPE}"
                if [[ -z "${FIXTURE_PHP:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'php' but FIXTURE_PHP is empty"
                fi
                export PARITY_FIXTURE_PHP="${abs_scenario}/${FIXTURE_PHP}"
                ;;
            go)
                if [[ -z "${GO_DIR:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'go' but GO_DIR is empty"
                fi
                export PARITY_GO_DIR="${abs_scenario}/${GO_DIR}"
                if [[ -z "${GO_BIN:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'go' but GO_BIN is empty"
                fi
                # PARITY_ROOT is the Runner/ directory (set by constants.sh). GO_BIN paths
                # in scenario.env are relative to Runner/ (e.g. "build/go-bin/helloworld").
                export PARITY_GO_BIN="${PARITY_ROOT}/${GO_BIN}"
                if [[ -z "${FIXTURE_GO:-}" ]]; then
                    parity_die "manifest ${manifest}: SDKS contains 'go' but FIXTURE_GO is empty"
                fi
                export PARITY_FIXTURE_GO="${abs_scenario}/${FIXTURE_GO}"
                ;;
            ts|typescript)
                parity_die "manifest ${manifest}: SDK '${sdk}' is reserved but not yet supported by the parity framework"
                ;;
            *)
                parity_die "manifest ${manifest}: unknown SDK '${sdk}' in SDKS"
                ;;
        esac
    done

    parity_log "loaded manifest: ${PARITY_SCENARIO_NAME} (${manifest})"
    parity_log "  sdks=${PARITY_SDKS}  namespace=${PARITY_NAMESPACE}"
    parity_debug "  php_tq=${PARITY_PHP_TASK_QUEUE}  java_tq=${PARITY_JAVA_TASK_QUEUE}  go_tq=${PARITY_GO_TASK_QUEUE}"
}

# Find every scenario.env under a root, emit absolute scenario directories one per line, sorted.
parity_discover_scenarios() {
    local root="${1:?parity_discover_scenarios: root required}"
    local manifest
    while IFS= read -r -d '' manifest; do
        printf '%s\n' "$(dirname "${manifest}")"
    done < <(find "${root}" -type f -name 'scenario.env' -print0 | sort -z)
}
