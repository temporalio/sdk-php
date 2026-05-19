#!/usr/bin/env bash
# DEPRECATED COMPATIBILITY SHIM.
#
# Older callers (IDE run-configs, external scripts) invoked this with a
# scenario directory to start Temporal and create the per-scenario namespace.
# Namespaces are now shared (see lib/constants.sh) and the server lifecycle is
# session-level (setup-temporal-once.sh + teardown-temporal.sh, hooked from
# run-fixtures.sh). This wrapper exists only so old call sites still work.
#
# Any positional argument is ignored; environment overrides (TEMPORAL_BIN,
# TEMPORAL_ADDRESS, PARITY_NAMESPACE) still apply.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARITY_PHASE="parity-setup"
# shellcheck source=lib/log.sh
. "${SCRIPT_DIR}/lib/log.sh"

parity_debug "setup-temporal.sh is a deprecated shim; delegating to setup-temporal-once.sh"

exec "${SCRIPT_DIR}/setup-temporal-once.sh"
