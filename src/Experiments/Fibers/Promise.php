<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * Fiber-aware mirror of {@see \Temporal\Promise}; combinators auto-suspend the Fiber.
 *
 * @experimental
 */
final class Promise
{
    private function __construct() {}

    /**
     * @param iterable<int, PromiseInterface> $promises
     * @return list<mixed>
     */
    public static function all(iterable $promises): array
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Promise::all($promises));
    }

    /**
     * @param iterable<int, PromiseInterface> $promises
     */
    public static function any(iterable $promises): mixed
    {
        return FiberHelper::await(\Temporal\Promise::any($promises));
    }

    /**
     * @param iterable<int, PromiseInterface> $promises
     * @return list<mixed>
     */
    public static function some(iterable $promises, int $count): array
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Promise::some($promises, $count));
    }

    /**
     * @template T
     * @param iterable<PromiseInterface<T>|T> $promisesOrValues
     * @return T
     */
    public static function race(iterable $promisesOrValues): mixed
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Promise::race($promisesOrValues));
    }

    /**
     * @param iterable<int, PromiseInterface> $promises
     * @return list<mixed>
     */
    public static function map(iterable $promises, callable $map): array
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Promise::map($promises, $map));
    }

    /**
     * @param iterable<int, PromiseInterface> $promises
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
