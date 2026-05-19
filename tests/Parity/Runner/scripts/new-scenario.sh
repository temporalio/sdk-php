#!/usr/bin/env bash
# Scaffold a new parity scenario from the Framework/Templates files.
#
# Usage:
#   new-scenario.sh <ScenarioShort>
#
# Example:
#   new-scenario.sh FailureRetry
#     -> creates tests/Parity/Scenarios/FailureRetry/{scenario.env, php/scenario.php,
#                 java/src/main/java/com/temporal/parity/Main.java, go/main.go,
#                 FailureRetryTest.php, fixtures/}
#
# Placeholders substituted:
#   __SCENARIO_NAME__         "FailureRetry"
#   __SCENARIO_SHORT__        "FailureRetry"
#   __SCENARIO_SLUG__         "failure-retry"   (kebab-case)
#   __PHP_NAMESPACE__         "Temporal\\Tests\\Parity\\FailureRetry\\Php"
#   __PHP_NAMESPACE_PARENT__  "Temporal\\Tests\\Parity\\FailureRetry"
#   __WORKFLOW_TYPE__         "Parity_FailureRetry"
#
# Namespace + per-language task queues are SHARED (see scripts/lib/constants.sh)
# and not baked into scenario.env anymore. No per-scenario java/build.gradle is
# generated — the shared subprojects {} block in Runner/build.gradle handles it.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-scaffold"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"
# shellcheck source=lib/constants.sh
. "${SCRIPT_DIR}/lib/constants.sh"

if [[ $# -ne 1 ]]; then
    parity_die "usage: new-scenario.sh <ScenarioShort>"
fi

SHORT="$1"
TEMPLATES="${FRAMEWORK_ROOT}/Templates"

if [[ ! "${SHORT}" =~ ^[A-Z][A-Za-z0-9]*$ ]]; then
    parity_die "scenario name must be PascalCase: '${SHORT}'"
fi

if [[ ! -d "${TEMPLATES}" ]]; then
    parity_die "templates dir not found: ${TEMPLATES}"
fi

TARGET="${SCENARIOS_ROOT}/${SHORT}"
if [[ -e "${TARGET}" ]]; then
    parity_die "target already exists: ${TARGET}"
fi

ROOT_SETTINGS="${PARITY_ROOT}/settings.gradle"
if [[ ! -f "${ROOT_SETTINGS}" ]]; then
    parity_die "root settings.gradle not found at ${ROOT_SETTINGS}"
fi
if grep -qE "['\"]${SHORT}['\"]" "${ROOT_SETTINGS}"; then
    parity_die "scenario name '${SHORT}' is already referenced in ${ROOT_SETTINGS}"
fi

to_slug() {
    echo "$1" \
        | sed -E 's/([A-Z]+)([A-Z][a-z])/\1-\2/g' \
        | sed -E 's/([a-z0-9])([A-Z])/\1-\2/g' \
        | tr '[:upper:]' '[:lower:]'
}

SCENARIO_SLUG="$(to_slug "${SHORT}")"
SCENARIO_NAME="${SHORT}"
PHP_NAMESPACE_PARENT="Temporal\\Tests\\Parity\\${SHORT}"
PHP_NAMESPACE="${PHP_NAMESPACE_PARENT}\\Php"
WORKFLOW_TYPE="Parity_${SHORT}"

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

INCLUDE_LINE="include ':Scenarios:${SHORT}:java'"
PROJECT_DIR_LINE="project(':Scenarios:${SHORT}:java').projectDir = file('../Scenarios/${SHORT}/java')"
parity_log "registering scenario in ${ROOT_SETTINGS}"
printf '\n%s\n%s\n' "${INCLUDE_LINE}" "${PROJECT_DIR_LINE}" >> "${ROOT_SETTINGS}"

substitute() {
    local src="$1" dest="$2"
    sed \
        -e "s|__SCENARIO_NAME__|${SCENARIO_NAME}|g" \
        -e "s|__SCENARIO_SHORT__|${SHORT}|g" \
        -e "s|__SCENARIO_SLUG__|${SCENARIO_SLUG}|g" \
        -e "s|__PHP_NAMESPACE__|${PHP_NAMESPACE}|g" \
        -e "s|__PHP_NAMESPACE_PARENT__|${PHP_NAMESPACE_PARENT}|g" \
        -e "s|__WORKFLOW_TYPE__|${WORKFLOW_TYPE}|g" \
        "${src}" > "${dest}"
    parity_log "wrote ${dest}"
}

substitute "${TEMPLATES}/scenario.env.tmpl" "${TARGET}/scenario.env"
substitute "${TEMPLATES}/php/scenario.php.tmpl" "${TARGET}/php/scenario.php"
substitute "${TEMPLATES}/java/src/main/java/com/temporal/parity/Main.java.tmpl" \
           "${TARGET}/java/src/main/java/com/temporal/parity/Main.java"
substitute "${TEMPLATES}/go/main.go.tmpl" "${TARGET}/go/main.go"
substitute "${TEMPLATES}/ScenarioTest.php.tmpl" "${TARGET}/${SHORT}Test.php"

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
