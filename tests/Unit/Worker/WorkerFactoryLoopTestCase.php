<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Internal\Workflow\Process\Awaiter;
use Temporal\WorkerFactory;

final class WorkerFactoryLoopTestCase extends TestCase
{
    public function testQueryAndFinallyLayersAreWithheldWhileAManagedFiberIsCurrent(): void
    {
        $drained = [];
        $factory = $this->factory();

        foreach ([LoopInterface::ON_QUERY, LoopInterface::ON_FINALLY, LoopInterface::ON_TICK] as $layer) {
            $factory->once($layer, static function () use (&$drained, $layer): void {
                $drained[] = $layer;
            });
        }

        $fiber = new \Fiber(static function () use ($factory): void {
            \Fiber::suspend();
            $factory->tick();
        });
        $fiber->start();

        Awaiter::register($fiber);

        try {
            $fiber->resume();
        } finally {
            Awaiter::unregister($fiber);
        }

        self::assertSame([LoopInterface::ON_TICK], $drained);
    }

    public function testEveryLayerIsDrainedOutsideAManagedFiber(): void
    {
        $drained = [];
        $factory = $this->factory();

        foreach ([LoopInterface::ON_QUERY, LoopInterface::ON_FINALLY, LoopInterface::ON_TICK] as $layer) {
            $factory->once($layer, static function () use (&$drained, $layer): void {
                $drained[] = $layer;
            });
        }

        $factory->tick();

        \sort($drained);
        $expected = [LoopInterface::ON_QUERY, LoopInterface::ON_FINALLY, LoopInterface::ON_TICK];
        \sort($expected);

        self::assertSame($expected, $drained);
    }

    private function factory(): WorkerFactory
    {
        return WorkerFactory::create(
            DataConverter::createDefault(),
            $this->createStub(RPCConnectionInterface::class),
        );
    }
}
