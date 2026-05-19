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

export PARITY_NAMESPACE
export PARITY_PHP_TASK_QUEUE
export PARITY_JAVA_TASK_QUEUE
export PARITY_GO_TASK_QUEUE
