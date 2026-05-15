# Top-level convenience driver for the Cross-SDK Parity test tier.
#
# Three-phase execution model:
#   make build      - Phase 0: build every SDK fixture-runner per scenario manifest
#   make fixtures   - Phase 1: setup Temporal + run every SDK runner -> *.json artifacts
#   make assert     - Phase 2: run PHPUnit against ./phpunit.xml against the JSONs
#   make all        - build + fixtures + assert
#   make clean      - drop captured *.json from every scenario fixtures/ dir
#   make clean-build - delete per-scenario build artifacts (e.g. java/build, .gradle)
#   make clean-deep - clean + clean-build + stop the local Temporal dev server (DESTRUCTIVE)
#
# See README.md for the full layout.

PARITY_ROOT  := $(abspath .)
PROJECT_ROOT := $(abspath ../..)
SCRIPTS      := $(PARITY_ROOT)/scripts
PHPUNIT      := $(PROJECT_ROOT)/vendor/bin/phpunit

.PHONY: all build fixtures assert clean clean-build clean-deep help

all: build fixtures assert

help:
	@echo "Targets:"
	@echo "  build        Phase 0 — build every SDK fixture-runner (gradle, etc.)"
	@echo "  fixtures     Phase 1 — capture JSON fixtures by running every SDK runner"
	@echo "  assert       Phase 2 — run PHPUnit (loads fixtures, normalizes, asserts parity)"
	@echo "  all          build + fixtures + assert"
	@echo "  clean        drop captured *.json fixtures from every scenario"
	@echo "  clean-build  delete per-scenario build artifacts (java/build, java/.gradle)"
	@echo "  clean-deep   clean + clean-build + stop Temporal dev server (DESTRUCTIVE)"

build:
	@$(SCRIPTS)/build.sh

fixtures:
	@$(PARITY_ROOT)/run-fixtures.sh

assert:
	@$(PHPUNIT) -c $(PARITY_ROOT)/phpunit.xml --testdox --colors=never

clean:
	@echo "[parity-clean] dropping *.json from every scenario fixtures/ dir"
	@find $(PARITY_ROOT) -type d -name fixtures -not -path '$(PARITY_ROOT)/Framework/*' -print0 \
		| xargs -0 -I {} sh -c 'rm -f {}/*.json && echo "==> {}"'

clean-build:
	@echo "[parity-clean-build] removing per-scenario build artifacts"
	@find $(PARITY_ROOT) -type d \( -name build -o -name .gradle \) -not -path '$(PARITY_ROOT)/Framework/*' -print0 \
		| xargs -0 -I {} sh -c 'echo "==> rm -rf {}" && rm -rf {}'

clean-deep: clean clean-build
	@echo "[parity-clean-deep] stopping Temporal dev server (if running)"
	@pkill -f 'temporal server start-dev' 2>/dev/null || true
