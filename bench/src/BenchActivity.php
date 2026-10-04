<?php

declare(strict_types=1);

namespace Temporal\Bench;

use Revolt\EventLoop;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'BenchActivity.')]
final class BenchActivity
{
    private const KV_STORAGE = 'bench';

    private static ?StorageInterface $storage = null;

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

    #[ActivityMethod('kv')]
    public function kv(int $operations): int
    {
        $storage = self::$storage ??= (new Factory(RPC::create(benchEnv('RR_RPC'))))->select(self::KV_STORAGE);
        for ($i = 0; $i < $operations; ++$i) {
            $storage->set("key-$i", $i);
            $storage->get("key-$i");
        }

        return $operations;
    }
}
