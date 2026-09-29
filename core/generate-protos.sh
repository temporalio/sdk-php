#!/usr/bin/env sh
set -eu

PROTOS="${SDK_CORE_PROTOS:-$HOME/IdeaProjects/temporalio/sdk-typescript/packages/core-bridge/sdk-core/crates/common/protos}"
OUT="$(cd "$(dirname "$0")" && pwd)/generated"

rm -rf "$OUT"
mkdir -p "$OUT"
cd "$PROTOS/local"
protoc -I . -I ../api_upstream -I ../google --php_out="$OUT" $(find temporal/sdk/core -name '*.proto')
