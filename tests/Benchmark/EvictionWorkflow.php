<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use React\Promise\Deferred;
use Temporal\Promise;
use Temporal\Workflow;

use function React\Promise\all;

#[Workflow\WorkflowInterface]
final class EvictionWorkflow
{
    #[Workflow\WorkflowMethod(name: 'EvictionWorkflow')]
    public function handler(string $shape): iterable
    {
        $timer = static fn(): mixed => Workflow::timer(100);

        yield match ($shape) {
            'timer' => $timer(),
            'temporal_all' => Promise::all([$timer(), $timer(), $timer()]),
            'react_all' => all([$timer(), $timer(), $timer()]),
            'scopes' => Promise::all([
                Workflow::async(static fn(): \Generator => yield $timer()),
                Workflow::async(static fn(): \Generator => yield Workflow::async(static fn(): \Generator => yield $timer())),
                $timer(),
            ]),
            'then_chain' => $timer()->then(static fn($v) => $v)->then(static fn($v) => $v),
            'user_deferred' => (new Deferred(static function (): void {}))->promise(),
            'signal' => Workflow::await(static fn(): bool => false),
        };
    }

    #[Workflow\SignalMethod(name: 'wait')]
    public function wait(): iterable
    {
        yield Workflow::timer(100);
    }
}
