# Top-level convenience driver for the Cross-SDK Parity test tier.
#
# Three-phase execution model:
#   make build           - Phase 0: build every SDK fixture-runner per scenario manifest
#   make fixtures        - Phase 1: start Temporal + run every SDK runner -> *.json artifacts;
#                          temporal stops on exit IFF this run started it (PID-marker)
#   make assert          - Phase 2: run PHPUnit against ./phpunit.xml against the JSONs
#   make all             - build + fixtures + assert
#   make temporal-start  - start the shared Temporal dev server + ensure parity namespace
#   make temporal-stop   - stop the parity-owned Temporal server (no-op if not started by us)
#   make clean           - drop captured *.json from every scenario fixtures/ dir
#   make clean-build     - delete build artifacts (Runner/build, per-scenario java/build, .gradle)
#   make clean-deep      - clean + clean-build + stop the parity-owned Temporal server
#
# See README.md for the full layout.

PARITY_ROOT      := $(abspath .)
PARITY_TREE_ROOT := $(abspath ..)
SCENARIOS_ROOT   := $(abspath ../Scenarios)
FRAMEWORK_ROOT   := $(abspath ../Framework)
PROJECT_ROOT     := $(abspath ../../..)
SCRIPTS          := $(PARITY_ROOT)/scripts
PHPUNIT          := $(PROJECT_ROOT)/vendor/bin/phpunit

.PHONY: all build fixtures assert clean clean-build clean-deep help temporal-start temporal-stop

all: build fixtures assert

help:
	@echo "Targets:"
	@echo "  build           Phase 0 — build every SDK fixture-runner (gradle, etc.)"
	@echo "  fixtures        Phase 1 — capture JSON fixtures by running every SDK runner"
	@echo "  assert          Phase 2 — run PHPUnit (loads fixtures, normalizes, asserts parity)"
	@echo "  all             build + fixtures + assert"
	@echo "  temporal-start  start shared Temporal dev server + ensure parity namespace"
	@echo "  temporal-stop   stop the parity-owned Temporal server (no-op if not ours)"
	@echo "  clean           drop captured *.json fixtures from every scenario"
	@echo "  clean-build     delete build artifacts (Runner/build/, java/build/, .gradle/)"
	@echo "  clean-deep      clean + clean-build + temporal-stop"

build:
	@$(SCRIPTS)/build.sh

fixtures:
	@$(PARITY_ROOT)/run-fixtures.sh

assert:
	@$(PHPUNIT) -c $(PARITY_ROOT)/phpunit.xml --testdox --colors=never

temporal-start:
	@$(SCRIPTS)/setup-temporal-once.sh

temporal-stop:
	@$(SCRIPTS)/teardown-temporal.sh

clean:
	@echo "[parity-clean] dropping *.json from every scenario fixtures/ dir"
	@find $(SCENARIOS_ROOT) -type f -name '*.json' -delete

clean-build:
	@echo "[parity-clean-build] removing per-scenario build artifacts"
	@find $(SCENARIOS_ROOT) -type d \( -name build -o -name .gradle \) -prune -exec rm -rf {} +
	@rm -rf $(PARITY_ROOT)/build $(PARITY_ROOT)/.gradle

clean-deep: clean clean-build temporal-stop
	@echo "[parity-clean-deep] done"
