<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Temporal\Tests\Acceptance\App\Logger\TranscriptRun;
use Temporal\Tests\Acceptance\App\Logger\TranscriptStore;
use Temporal\Worker\Logger\StderrLogger;

$stderr = new StderrLogger();
$store = TranscriptStore::create(stderr: $stderr);

$listMode = false;
$selector = null;
foreach (\array_slice($argv, 1) as $arg) {
    if ($arg === '--list' || $arg === 'list') {
        $listMode = true;
        continue;
    }
    if ($arg === '--last' || $arg === 'last') {
        $selector = null;
        continue;
    }
    if (\str_starts_with($arg, '-')) {
        $stderr->error('unknown flag', ['flag' => $arg]);
        exit(2);
    }
    $selector = $arg;
}

if ($listMode) {
    exit(printRuns($store, $stderr));
}

$run = $selector === null ? $store->latestRun() : $store->findRun($selector);
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
