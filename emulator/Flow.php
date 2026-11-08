<?php

declare(strict_types=1);

namespace Emu;

use React\Promise\Deferred;

use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\race;

final class Flow implements Destroyable
{
    public const SHAPES = [
        'timer', 'await', 'then_chain', 'fork', 'temporal_all', 'temporal_any', 'temporal_race', 'temporal_nested',
        'react_all', 'react_any', 'react_race', 'react_nested', 'react_fork', 'async', 'async_on_cancel',
        'detached', 'nested_yield', 'nested_yield_from', 'signal', 'user_deferred', 'user_deferred_react',
        'finally_command', 'finally_throws', 'finally_resolves', 'sibling_resolves', 'async_nested', 'cross_batch',
    ];

    public const FINALLY_ORDER = [
        'async' => ['child', 'main'],
        'async_on_cancel' => ['child', 'main'],
        'detached' => ['detached', 'main'],
        'nested_yield' => ['inner', 'outer'],
        'nested_yield_from' => ['inner', 'outer'],
        'signal' => ['signal', 'main'],
        'finally_command' => ['command:Emu\\IllegalState'],
        'finally_resolves' => ['waiter', 'main'],
        'sibling_resolves' => ['closer', 'waiter', 'main'],
        'async_nested' => ['grandchild', 'child', 'main'],
        'cross_batch' => ['child', 'late', 'main'],
    ];

    public function __construct(
        private readonly string $shape,
    ) {}

    public function handler(): \Generator
    {
        $user = static fn(string $name): \Closure => static function () use ($name): void {
            Emu::record('user', $name);
        };
        $canceller = static function (): void {
            Emu::record('user', 'canceller', self::runId());
        };
        $never = static fn(): bool => false;

        yield from match ($this->shape) {
            'timer' => $this->guarded('main', W::timer(100)),
            'await' => $this->guarded('main', W::await($never)),
            'then_chain' => $this->guarded('main', W::timer(100)->then($user('then'))),
            'fork' => $this->fork($user),
            'temporal_all' => $this->guarded('main', TPromise::all([W::timer(100), W::timer(200)])),
            'temporal_any' => $this->guarded('main', TPromise::any([W::timer(100), W::await($never)])),
            'temporal_race' => $this->guarded('main', TPromise::race([W::timer(100), W::await($never)])),
            'temporal_nested' => $this->guarded('main', TPromise::all([
                TPromise::race([W::timer(100), W::timer(200)]),
                TPromise::any([W::timer(300), W::await($never)]),
            ])),
            'react_all' => $this->guarded('main', all([W::timer(100), W::timer(200)])),
            'react_any' => $this->guarded('main', any([W::timer(100), W::await($never)])),
            'react_race' => $this->guarded('main', race([W::timer(100), W::await($never)])),
            'react_nested' => $this->guarded('main', all([race([W::timer(100), W::timer(200)]), W::timer(300)])),
            'react_fork' => $this->reactFork($user),
            'async' => $this->async($never),
            'async_on_cancel' => $this->asyncOnCancel($user),
            'detached' => $this->detached($never),
            'nested_yield' => $this->nestedYield(),
            'nested_yield_from' => $this->nestedYieldFrom(),
            'signal' => $this->guarded('main', W::await($never)),
            'user_deferred' => $this->guarded('main', TPromise::all([(new Deferred($canceller))->promise(), W::timer(100)])),
            'user_deferred_react' => $this->guarded('main', all([(new Deferred($canceller))->promise(), W::timer(100)])),
            'finally_command' => $this->finallyCommand(),
            'finally_throws' => $this->finallyThrows(),
            'finally_resolves' => $this->finallyResolves(),
            'sibling_resolves' => $this->siblingResolves($never),
            'async_nested' => $this->asyncNested($never),
            'cross_batch' => $this->crossBatch($never),
        };
    }

    public function signalHandler(): \Generator
    {
        yield from $this->guarded('signal', W::timer(100));
    }

    public function destroy(): void
    {
        Emu::record('destroy', self::runId());
    }

    private static function runId(): string
    {
        try {
            return W::runId();
        } catch (\Throwable $e) {
            return $e::class;
        }
    }

    private function guarded(string $name, mixed $promise): \Generator
    {
        try {
            yield $promise;
        } catch (\Throwable $e) {
            Emu::record('user', 'catch:' . $name, $e::class);
        } finally {
            Emu::record('finally', $name, self::runId());
        }
    }

    private function fork(\Closure $user): \Generator
    {
        $timer = W::timer(100);
        $timer->then($user('fork'));
        yield from $this->guarded('main', $timer->then($user('then')));
    }

    private function reactFork(\Closure $user): \Generator
    {
        $all = all([W::timer(100), W::timer(200)]);
        $all->then($user('fork'));
        yield from $this->guarded('main', $all);
    }

    private function async(\Closure $never): \Generator
    {
        W::async(fn(): \Generator => yield from $this->guarded('child', W::timer(100)));
        yield from $this->guarded('main', W::await($never));
    }

    private function asyncOnCancel(\Closure $user): \Generator
    {
        $scope = W::async(fn(): \Generator => yield from $this->guarded('child', W::timer(100)));
        $scope->onCancel($user('onCancel'));
        yield from $this->guarded('main', TPromise::all([$scope, W::timer(100)]));
    }

    private function detached(\Closure $never): \Generator
    {
        W::asyncDetached(fn(): \Generator => yield from $this->guarded('detached', W::timer(100)));
        yield from $this->guarded('main', W::await($never));
    }

    private function nestedYield(): \Generator
    {
        try {
            yield $this->guarded('inner', W::timer(100));
        } finally {
            Emu::record('finally', 'outer', self::runId());
        }
    }

    private function nestedYieldFrom(): \Generator
    {
        try {
            yield from $this->guarded('inner', W::timer(100));
        } finally {
            Emu::record('finally', 'outer', self::runId());
        }
    }

    private function finallyCommand(): \Generator
    {
        try {
            yield W::timer(100);
        } finally {
            try {
                W::timer(1);
                Emu::record('finally', 'command-sent', self::runId());
            } catch (\Throwable $e) {
                Emu::record('finally', 'command:' . $e::class, self::runId());
            }
        }
    }

    private function finallyResolves(): \Generator
    {
        $deferred = new Deferred();
        W::async(fn(): \Generator => yield from $this->guarded('waiter', $deferred->promise()));
        try {
            yield W::timer(100);
        } finally {
            Emu::record('finally', 'main', self::runId());
            $deferred->resolve(1);
        }
    }

    private function siblingResolves(\Closure $never): \Generator
    {
        $deferred = new Deferred();
        W::async(static function () use ($deferred): \Generator {
            try {
                yield $deferred->promise();
                Emu::record('user', 'resumed');
            } finally {
                Emu::record('finally', 'waiter', self::runId());
            }
        });
        W::async(static function () use ($deferred): \Generator {
            try {
                yield W::timer(100);
            } finally {
                Emu::record('finally', 'closer', self::runId());
                $deferred->resolve(1);
            }
        });
        yield from $this->guarded('main', W::await($never));
    }

    private function asyncNested(\Closure $never): \Generator
    {
        W::async(function (): \Generator {
            W::async(fn(): \Generator => yield from $this->guarded('grandchild', W::timer(100)));
            yield from $this->guarded('child', W::timer(100));
        });
        yield from $this->guarded('main', W::await($never));
    }

    private function crossBatch(\Closure $never): \Generator
    {
        $timer = W::timer(100);
        $first = new Deferred();
        $second = new Deferred();
        W::async(fn(): \Generator => yield from $this->guarded('late', $second->promise()));
        W::async(static function () use ($first, $second, $never): \Generator {
            try {
                yield $first->promise();
                W::timer(70);
                $second->resolve(1);
                yield W::await($never);
            } finally {
                Emu::record('finally', 'child', self::runId());
            }
        });
        yield $timer;
        W::timer(50);
        $first->resolve(1);
        yield from $this->guarded('main', W::await($never));
    }

    private function finallyThrows(): \Generator
    {
        try {
            yield W::timer(100);
        } finally {
            Emu::record('finally', 'main', self::runId());
            throw new \RuntimeException('finally fails');
        }
    }
}
