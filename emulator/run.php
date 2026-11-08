<?php

declare(strict_types=1);

namespace Emu;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Emu.php';
require __DIR__ . '/Flow.php';

$options = \getopt('', ['strategy:', 'shape:', 'json']);
$strategies = isset($options['strategy']) ? [$options['strategy']] : [Emu::EXCEPTION, Emu::NONE, Emu::FIX];

if (isset($options['shape'])) {
    \gc_disable();
    Emu::$strategy = $strategies[0];
    echo \json_encode(evict($options['shape']), \JSON_THROW_ON_ERROR);
    exit(0);
}

$failed = false;
foreach ($strategies as $strategy) {
    echo "\nstrategy: {$strategy}\n";
    \printf("%-20s %-6s %-7s %-6s %-5s %s\n", 'shape', 'cycles', 'alive', 'next', 'ok', 'eviction log');
    foreach (Flow::SHAPES as $shape) {
        $output = \shell_exec(\sprintf(
            '%s %s --strategy=%s --shape=%s 2>&1',
            \PHP_BINARY,
            \escapeshellarg(__FILE__),
            \escapeshellarg($strategy),
            \escapeshellarg($shape),
        ));
        $result = \json_decode((string) $output, true);
        if (!\is_array($result)) {
            \printf("%-20s CRASH %s\n", $shape, \trim((string) $output));
            $failed = true;
            continue;
        }

        $ok = $result['cycles'] === 0 && !$result['alive'] && $result['next'] && $result['problems'] === [];
        $failed = $failed || ($strategy === Emu::FIX && !$ok);
        \printf(
            "%-20s %-6d %-7s %-6s %-5s %s\n",
            $shape,
            $result['cycles'],
            $result['alive'] ? 'yes' : 'no',
            $result['next'] ? 'ok' : 'BROKEN',
            $ok ? 'ok' : 'FAIL',
            \implode(' | ', [...$result['problems'], ...$result['log']]),
        );
    }
}

exit($failed ? 1 : 0);

function evict(string $shape): array
{
    $worker = new Worker();
    $instance = new Flow($shape);
    $alive = \WeakReference::create($instance);

    $commands = $worker->batch(static fn(Worker $w) => $w->start('run-1', $instance, $instance->handler(...)));
    if ($shape === 'cross_batch') {
        $worker->batch(static fn(Worker $w) => $w->respond('run-1', $commands[0]));
    }
    if ($shape === 'signal') {
        $worker->batch(static fn(Worker $w) => $w->signal('run-1', $instance->signalHandler(...)));
    }
    unset($instance);

    $problems = [];
    Emu::$evicting = true;
    try {
        $worker->batch(static fn(Worker $w) => $w->evict('run-1'));
    } catch (\Throwable $e) {
        $problems[] = 'eviction threw ' . $e::class . ': ' . $e->getMessage();
    }
    Emu::$evicting = false;
    Ctx::$current = null;

    $cycles = \gc_collect_cycles();
    $log = \array_map(
        static fn(array $entry): string => \implode(':', \array_slice($entry, 1)),
        \array_values(\array_filter(Emu::$log, static fn(array $entry): bool => $entry[0] === 'evict')),
    );

    foreach (Emu::$log as $entry) {
        if ($entry[0] === 'evict' && $entry[1] === 'user') {
            $problems[] = 'user code ran';
            break;
        }
    }
    foreach (Emu::$log as $entry) {
        if ($entry[0] === 'evict' && \in_array($entry[1], ['finally', 'destroy'], true) && \end($entry) !== 'run-1') {
            $problems[] = 'no context';
            break;
        }
    }

    $finally = [];
    foreach (Emu::$log as $entry) {
        if ($entry[0] === 'evict' && $entry[1] === 'finally') {
            $finally[] = $entry[2];
        }
    }
    if ($finally !== (Flow::FINALLY_ORDER[$shape] ?? ['main'])) {
        $problems[] = 'finally order';
    }
    $last = \end(Emu::$log);
    if ($last === false || $last[1] !== 'destroy') {
        $problems[] = 'destroy is not last';
    }

    return [
        'cycles' => $cycles,
        'alive' => $alive->get() !== null,
        'next' => next_run($worker),
        'problems' => $problems,
        'log' => $log,
    ];
}

function next_run(Worker $worker): bool
{
    try {
        $instance = new Flow('timer');
        $commands = $worker->batch(static fn(Worker $w) => $w->start('run-2', $instance, $instance->handler(...)));
        if (\count($commands) !== 1 || $commands[0]->name !== 'timer') {
            return false;
        }

        $commands = $worker->batch(static fn(Worker $w) => $w->respond('run-2', $commands[0]));

        return \count($commands) === 1 && $commands[0]->name === 'complete';
    } catch (\Throwable) {
        return false;
    }
}
