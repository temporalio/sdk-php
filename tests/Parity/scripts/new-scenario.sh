#!/usr/bin/env bash
# Scaffold a new parity scenario from the Framework/Skel/basic templates.
#
# Usage:
#   new-scenario.sh <area> <ScenarioShort>
#
# Example:
#   new-scenario.sh Basic FailureRetry
#     -> creates tests/Parity/Basic/FailureRetry/{scenario.env, php/scenario.php,
#                 java/*, FailureRetryTest.php, fixtures/}
#
# Placeholders substituted:
#   __SCENARIO_NAME__         "Basic/FailureRetry"
#   __SCENARIO_SHORT__        "FailureRetry"
#   __SCENARIO_SLUG__         "basic-failure-retry"   (kebab-case)
#   __AREA__                  "Basic"
#   __PHP_NAMESPACE__         "Temporal\\Tests\\Parity\\Basic\\FailureRetry\\Php"
#   __PHP_NAMESPACE_PARENT__  "Temporal\\Tests\\Parity\\Basic\\FailureRetry"
#   __WORKFLOW_TYPE__         "Parity_Basic_FailureRetry"
#
# Namespace + per-language task queues are SHARED (see scripts/lib/constants.sh)
# and not baked into scenario.env anymore.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PARITY_PHASE="parity-scaffold"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"

if [[ $# -ne 2 ]]; then
    parity_die "usage: new-scenario.sh <area> <ScenarioShort>"
fi

AREA="$1"
SHORT="$2"

SKEL="${PARITY_ROOT}/Framework/Skel/basic"

if [[ ! "${AREA}" =~ ^[A-Z][A-Za-z0-9]*$ ]]; then
    parity_die "area must be PascalCase: '${AREA}'"
fi
if [[ ! "${SHORT}" =~ ^[A-Z][A-Za-z0-9]*$ ]]; then
    parity_die "scenario name must be PascalCase: '${SHORT}'"
fi

if [[ ! -d "${SKEL}" ]]; then
    parity_die "skeleton dir not found: ${SKEL}"
fi

TARGET="${PARITY_ROOT}/${AREA}/${SHORT}"
if [[ -e "${TARGET}" ]]; then
    parity_die "target already exists: ${TARGET}"
fi

ROOT_SETTINGS="${PARITY_ROOT}/settings.gradle"
if [[ ! -f "${ROOT_SETTINGS}" ]]; then
    parity_die "root settings.gradle not found at ${ROOT_SETTINGS}"
fi
if grep -qE "['\"]${SHORT}['\"]" "${ROOT_SETTINGS}"; then
    parity_die "scenario name '${SHORT}' is already referenced in ${ROOT_SETTINGS} (default list or prior registration)"
fi

to_slug() {
    echo "$1" \
        | sed -E 's/([A-Z]+)([A-Z][a-z])/\1-\2/g' \
        | sed -E 's/([a-z0-9])([A-Z])/\1-\2/g' \
        | tr '[:upper:]' '[:lower:]'
}

AREA_SLUG="$(to_slug "${AREA}")"
SHORT_SLUG="$(to_slug "${SHORT}")"
SCENARIO_SLUG="${AREA_SLUG}-${SHORT_SLUG}"
SCENARIO_NAME="${AREA}/${SHORT}"
PHP_NAMESPACE_PARENT="Temporal\\Tests\\Parity\\${AREA}\\${SHORT}"
PHP_NAMESPACE="${PHP_NAMESPACE_PARENT}\\Php"
WORKFLOW_TYPE="Parity_${AREA}_${SHORT}"

SETTINGS_BACKUP="$(mktemp -t parity-settings.XXXXXX)"
cp "${ROOT_SETTINGS}" "${SETTINGS_BACKUP}"
TARGET_CREATED=0

rollback_scaffold() {
    local code=$?
    if [[ ${code} -ne 0 ]]; then
        parity_warn "scaffold failed (exit=${code}) — rolling back"
        if [[ ${TARGET_CREATED} -eq 1 && -d "${TARGET}" ]]; then
            rm -rf "${TARGET}"
            parity_warn "  removed ${TARGET}"
        fi
        if [[ -f "${SETTINGS_BACKUP}" ]]; then
            cp "${SETTINGS_BACKUP}" "${ROOT_SETTINGS}"
            parity_warn "  restored ${ROOT_SETTINGS}"
        fi
    fi
    rm -f "${SETTINGS_BACKUP}"
}
trap rollback_scaffold EXIT

parity_log "creating ${TARGET}"
mkdir -p "${TARGET}/php" "${TARGET}/java/src/main/java/com/temporal/parity" "${TARGET}/go" "${TARGET}/fixtures"
TARGET_CREATED=1

INCLUDE_LINE="include ':${AREA}:${SHORT}:java'"
PROJECT_DIR_LINE="project(':${AREA}:${SHORT}:java').projectDir = file('${AREA}/${SHORT}/java')"
parity_log "registering scenario in ${ROOT_SETTINGS}"
printf '\n%s\n%s\n' "${INCLUDE_LINE}" "${PROJECT_DIR_LINE}" >> "${ROOT_SETTINGS}"

substitute() {
    local src="$1" dest="$2"
    sed \
        -e "s|__SCENARIO_NAME__|${SCENARIO_NAME}|g" \
        -e "s|__SCENARIO_SHORT__|${SHORT}|g" \
        -e "s|__SCENARIO_SLUG__|${SCENARIO_SLUG}|g" \
        -e "s|__AREA__|${AREA}|g" \
        -e "s|__PHP_NAMESPACE__|${PHP_NAMESPACE}|g" \
        -e "s|__PHP_NAMESPACE_PARENT__|${PHP_NAMESPACE_PARENT}|g" \
        -e "s|__WORKFLOW_TYPE__|${WORKFLOW_TYPE}|g" \
        "${src}" > "${dest}"
    parity_log "wrote ${dest}"
}

substitute "${SKEL}/scenario.env.tmpl" "${TARGET}/scenario.env"
substitute "${SKEL}/php/scenario.php.tmpl" "${TARGET}/php/scenario.php"
substitute "${SKEL}/java/build.gradle.tmpl" "${TARGET}/java/build.gradle"
substitute "${SKEL}/java/src/main/java/com/temporal/parity/Main.java.tmpl" \
           "${TARGET}/java/src/main/java/com/temporal/parity/Main.java"
substitute "${SKEL}/go/main.go.tmpl" "${TARGET}/go/main.go"
substitute "${SKEL}/ScenarioTest.php.tmpl" "${TARGET}/${SHORT}Test.php"

touch "${TARGET}/fixtures/.gitkeep"

parity_log "----------------------------------------"
parity_log "scaffolded ${TARGET}"
parity_log ""
parity_log "next steps:"
parity_log "  1. flesh out the scenario in:"
parity_log "       ${TARGET}/php/scenario.php"
parity_log "       ${TARGET}/java/src/main/java/com/temporal/parity/Main.java"
parity_log "       ${TARGET}/go/main.go"
parity_log "  2. capture fixtures and compare:"
parity_log "       composer test:parity"
