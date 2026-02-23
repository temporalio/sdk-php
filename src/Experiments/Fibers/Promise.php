<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * Fiber-based Promise facade.
 *
 * Mirrors {@see \Temporal\Promise} but auto-suspends the current Fiber
 * for combinators (all, any, some, race, map, reduce).
 *
 * @experimental
 */
final class Promise
{
    /**
     * @param iterable<int, PromiseInterface|mixed> $promises
     */
    public static function all(iterable $promises): mixed
    {
        return FiberHelper::await(\Temporal\Promise::all($promises));
    }

    /**
     * @param iterable<int, PromiseInterface|mixed> $promises
     */
    public static function any(iterable $promises): mixed
    {
        return FiberHelper::await(\Temporal\Promise::any($promises));
    }

    /**
     * @param iterable<int, PromiseInterface|mixed> $promises
     */
    public static function some(iterable $promises, int $count): mixed
    {
        return FiberHelper::await(\Temporal\Promise::some($promises, $count));
    }

    /**
     * @template T
     * @param iterable<PromiseInterface<T>|T> $promisesOrValues
     */
    public static function race(iterable $promisesOrValues): mixed
    {
        return FiberHelper::await(\Temporal\Promise::race($promisesOrValues));
    }

    /**
     * @param iterable<int, PromiseInterface|mixed> $promises
     */
    public static function map(iterable $promises, callable $map): mixed
    {
        return FiberHelper::await(\Temporal\Promise::map($promises, $map));
    }

    /**
     * @param iterable<int, PromiseInterface|mixed> $promises
     */
    public static function reduce(iterable $promises, callable $reduce, mixed $initial = null): mixed
    {
        return FiberHelper::await(\Temporal\Promise::reduce($promises, $reduce, $initial));
    }

    /**
     * @template T
     * @param PromiseInterface<T>|T $promiseOrValue
     * @return PromiseInterface<T>
     */
    public static function resolve(mixed $promiseOrValue = null): PromiseInterface
    {
        return \Temporal\Promise::resolve($promiseOrValue);
    }

    /**
     * @return PromiseInterface<never>
     */
    public static function reject(mixed $reason): PromiseInterface
    {
        return \Temporal\Promise::reject($reason);
    }
}
