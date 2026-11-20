#!/usr/bin/env bash
set -euo pipefail
FILE=${1:-$(dirname "$0")/matrix.jsonl}
echo "| scenario | label | runs | wf/s | act/s | p50 ms | p99 ms | worker CPU s | RSS MB | failed |"
echo "|---|---|---|---|---|---|---|---|---|---|"
jq -rs '
  group_by([.scenario, .workflows, .activities_per_workflow, .rate, .label])
  | map({
      sc: (.[0].scenario + " " + (.[0].workflows|tostring) + "x" + (.[0].activities_per_workflow|tostring)
           + (if .[0].rate > 0 then " @" + (.[0].rate|tostring) + "/s" else "" end)),
      label: .[0].label,
      n: length,
      wf: ([.[].workflows_per_s] | add / length * 10 | round / 10),
      act: ([.[].activities_per_s] | add / length | round),
      p50: ([.[].latency_ms_p50] | add / length | round),
      p99: ([.[].latency_ms_p99] | add / length | round),
      cpu: ([.[].worker.cpu_seconds] | add / length * 100 | round / 100),
      rss: ([.[].worker.rss_mb_max] | add / length | round),
      failed: ([.[].failed] | add)
    })
  | sort_by(.sc, .label)[]
  | "| \(.sc) | \(.label) | \(.n) | \(.wf) | \(.act) | \(.p50) | \(.p99) | \(.cpu) | \(.rss) | \(.failed) |"
' "$FILE"
