<?php

declare(strict_types=1);

/**
 * Exit codes:
 *  0 — success (run merged or list printed).
 *  1 — no runs found / requested run absent / run has no transcript files.
 *  2 — usage error (unknown flag, conflicting flags, repeated flag, --list/--last combined).
 */

require __DIR__ . '/../../vendor/autoload.php';

use Temporal\Tests\Acceptance\App\Logger\TranscriptStore;
use Temporal\Worker\Logger\StderrLogger;

$stderr = new StderrLogger();
$store = TranscriptStore::create(stderr: $stderr);

$listMode = false;
$lastMode = false;
$selector = null;
foreach (\array_slice($argv, 1) as $arg) {
    if ($arg === '--list' || $arg === 'list') {
        if ($listMode) {
            $stderr->error('repeated flag', ['flag' => '--list']);
            exit(2);
        }
        $listMode = true;
        continue;
    }
    if ($arg === '--last' || $arg === 'last') {
        if ($lastMode) {
            $stderr->error('repeated flag', ['flag' => '--last']);
            exit(2);
        }
        $lastMode = true;
        continue;
    }
    if (\str_starts_with($arg, '-')) {
        $stderr->error('unknown flag', ['flag' => $arg]);
        exit(2);
    }
    if ($selector !== null) {
        $stderr->error('only one positional selector accepted', ['previous' => $selector, 'new' => $arg]);
        exit(2);
    }
    $selector = $arg;
}

if ($listMode && $lastMode) {
    $stderr->error('--list and --last are mutually exclusive');
    exit(2);
}
if ($listMode && $selector !== null) {
    $stderr->error('--list does not accept a selector', ['selector' => $selector]);
    exit(2);
}
if ($lastMode && $selector !== null) {
    $stderr->error('--last does not accept a selector', ['selector' => $selector]);
    exit(2);
}

if ($listMode) {
    exit(printRuns($store, $stderr));
}

try {
    $run = $selector === null ? $store->latestRun() : $store->findRun($selector);
} catch (\InvalidArgumentException $invalidSelector) {
    $stderr->error('invalid selector', ['selector' => $selector, 'message' => $invalidSelector->getMessage()]);
    exit(2);
}
if ($run === null) {
    $stderr->error(
        $selector === null ? 'no transcript runs found' : 'transcript run not found',
        ['base_directory' => $store->baseDirectory, 'selector' => $selector],
    );
    $stderr->info('try `composer transcripts:list` to see known runs');
    exit(1);
}

if ($run->files() === []) {
    $stderr->error('no transcript files in run', ['directory' => $run->directory]);
    exit(1);
}

\fwrite(\STDOUT, $run->merge() . "\n");
exit(0);

function printRuns(TranscriptStore $store, StderrLogger $stderr): int
{
    $runs = $store->listRuns();
    if ($runs === []) {
        $stderr->error('no transcript runs found', ['base_directory' => $store->baseDirectory]);
        return 1;
    }
    \fwrite(\STDOUT, "Known transcript runs (newest first):\n");
    foreach ($runs as $run) {
        \fwrite(\STDOUT, \sprintf(
            "  %s  %s  %d files  %d bytes\n",
            $run->id,
            $run->mtime === null ? 'unknown' : \date('Y-m-d H:i:s', $run->mtime),
            \count($run->files()),
            $run->totalBytes(),
        ));
    }
    return 0;
}
