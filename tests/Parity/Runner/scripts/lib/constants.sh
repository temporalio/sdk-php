#!/usr/bin/env bash
# Shared constants for the Parity test tier.
#
# Sourced by manifest.sh, setup-temporal-once.sh, run-{php,java,go}.sh.
# Each value is overridable via the matching env var — useful for CI runs
# that need to partition the shared namespace.
#
# CONTRACT: callers MUST source `lib/log.sh` BEFORE this file (we don't
# emit any log lines from here, but if a future addition does, it should
# fall back to no-op gracefully).

set -u

if [[ -n "${PARITY_CONSTANTS_SOURCED:-}" ]]; then
    return 0
fi
PARITY_CONSTANTS_SOURCED=1

PARITY_NAMESPACE="${PARITY_NAMESPACE:-parity}"
PARITY_PHP_TASK_QUEUE="${PARITY_PHP_TASK_QUEUE:-parity-php-task-queue}"
PARITY_JAVA_TASK_QUEUE="${PARITY_JAVA_TASK_QUEUE:-parity-java-task-queue}"
PARITY_GO_TASK_QUEUE="${PARITY_GO_TASK_QUEUE:-parity-go-task-queue}"

# Filesystem anchors. PARITY_ROOT is Runner/, SCENARIOS_ROOT is the sibling Scenarios/,
# FRAMEWORK_ROOT is the sibling Framework/. constants.sh sits at Runner/scripts/lib/,
# so go up two levels from BASH_SOURCE to land in Runner/.
_PARITY_CONSTANTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_ROOT="$(cd "${_PARITY_CONSTANTS_DIR}/../.." && pwd)"
SCENARIOS_ROOT="$(cd "${PARITY_ROOT}/../Scenarios" && pwd)"
FRAMEWORK_ROOT="$(cd "${PARITY_ROOT}/../Framework" && pwd)"

export PARITY_NAMESPACE
export PARITY_PHP_TASK_QUEUE
export PARITY_JAVA_TASK_QUEUE
export PARITY_GO_TASK_QUEUE
export PARITY_ROOT
export SCENARIOS_ROOT
export FRAMEWORK_ROOT
