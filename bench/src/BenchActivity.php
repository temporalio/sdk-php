<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Revolt\EventLoop;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'BenchActivity.')]
final class BenchActivity
{
    #[ActivityMethod('echo')]
    public function echo(string $payload): string
    {
        return $payload;
    }

    #[ActivityMethod('io')]
    public function io(int $milliseconds): int
    {
        if (\Fiber::getCurrent() === null) {
            \usleep($milliseconds * 1000);

            return $milliseconds;
        }

        $suspension = EventLoop::getSuspension();
        EventLoop::delay($milliseconds / 1000, $suspension->resume(...));
        $suspension->suspend();

        return $milliseconds;
    }
}
