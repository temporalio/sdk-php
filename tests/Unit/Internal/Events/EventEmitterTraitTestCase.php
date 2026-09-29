<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Events;

use PHPUnit\Framework\TestCase;
use Temporal\Internal\Events\EventEmitterTrait;

final class EventEmitterTraitTestCase extends TestCase
{
    public function testCallbacksRunInRegistrationOrderAndOnlyOnce(): void
    {
        $emitter = $this->emitter();
        $seen = [];

        foreach (['a', 'b', 'c'] as $name) {
            $emitter->once('tick', static function () use ($name, &$seen): void {
                $seen[] = $name;
            });
        }

        $emitter->emit('tick');
        $emitter->emit('tick');

        self::assertSame(['a', 'b', 'c'], $seen);
    }

    public function testACallbackRegisteredWhileTheEventIsEmittedRunsInTheSameEmit(): void
    {
        $emitter = $this->emitter();
        $seen = [];

        $emitter->once('tick', static function () use ($emitter, &$seen): void {
            $seen[] = 'first';
            $emitter->once('tick', static function () use (&$seen): void {
                $seen[] = 'late';
            });
        });

        $emitter->emit('tick');

        self::assertSame(['first', 'late'], $seen);
    }

    public function testACallbackRegisteredAfterANestedEmitIsNotDropped(): void
    {
        $emitter = $this->emitter();
        $seen = [];

        $emitter->once('tick', static function () use ($emitter, &$seen): void {
            $seen[] = 'outer';
            $emitter->emit('tick');
            $emitter->once('tick', static function () use (&$seen): void {
                $seen[] = 'after nested';
            });
        });
        $emitter->once('tick', static function () use (&$seen): void {
            $seen[] = 'nested';
        });

        $emitter->emit('tick');

        self::assertSame(['outer', 'nested', 'after nested'], $seen);
    }

    public function testEmittingAnUnknownEventIsANoOp(): void
    {
        $this->emitter()->emit('nothing', ['argument']);

        self::assertTrue(true);
    }

    public function testArgumentsArePassedToEveryCallback(): void
    {
        $emitter = $this->emitter();
        $seen = [];

        $emitter->once('tick', static function (int $a, string $b) use (&$seen): void {
            $seen[] = [$a, $b];
        });
        $emitter->once('tick', static function (int $a, string $b) use (&$seen): void {
            $seen[] = [$a, $b];
        });

        $emitter->emit('tick', [42, 'x']);

        self::assertSame([[42, 'x'], [42, 'x']], $seen);
    }

    public function testDrainingALargeQueueIsLinear(): void
    {
        $emitter = $this->emitter();
        $count = 60_000;
        $ran = 0;

        for ($i = 0; $i < $count; ++$i) {
            $emitter->once('tick', static function () use (&$ran): void {
                ++$ran;
            });
        }

        $started = \microtime(true);
        $emitter->emit('tick');
        $elapsed = \microtime(true) - $started;

        self::assertSame($count, $ran);
        self::assertLessThan(
            1.0,
            $elapsed,
            \sprintf('Draining %d callbacks took %.2f s; the queue is not drained in linear time.', $count, $elapsed),
        );
    }

    private function emitter(): object
    {
        return new class {
            use EventEmitterTrait;
        };
    }
}
