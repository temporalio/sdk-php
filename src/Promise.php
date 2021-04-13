<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use React\Promise\PromiseInterface;

use function React\Promise\all;
use function React\Promise\any;
use function React\Promise\map;
use function React\Promise\reduce;
use function React\Promise\some;

/**
 * @psalm-type PromiseMapCallback = callable(mixed $value): mixed
 * @psalm-type PromiseReduceCallback = callable(mixed $value): mixed
 */
final class Promise
{
    /**
     * Returns a promise that will resolve only once all the items
     * in `$promises` have resolved. The resolution value of the returned
     * promise will be an array containing the resolution values of each of the
     * items in `$promises`.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @return PromiseInterface
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    /**
     * Returns a promise that will resolve when any one of the items in
     * `$promises` resolves. The resolution value of the returned promise will
     * be the resolution value of the triggering item.
     *
     * The returned promise will only reject if *all* items in `$promises` are
     * rejected. The rejection value will be an array of all rejection reasons.
     *
     * The returned promise will also reject with a {@see LengthException} if
     * `$promises` contains 0 items.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @return PromiseInterface
     */
    public static function any(iterable $promises): PromiseInterface
    {
        return any([...$promises]);
    }

    /**
     * Returns a promise that will resolve when `$count` of the supplied items
     * in `$promises` resolve. The resolution value of the returned promise
     * will be an array of length `$count` containing the resolution values
     * of the triggering items.
     *
     * The returned promise will reject if it becomes impossible for `$count`
     * items to resolve (that is, when `(count($promises) - $count) + 1` items
     * reject). The rejection value will be an array of
     * `(count($promises) - $howMany) + 1` rejection reasons.
     *
     * The returned promise will also reject with a {@see LengthException} if
     * `$promises` contains less items than `$count`.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param int $count
     * @return PromiseInterface
     */
    public static function some(iterable $promises, int $count): PromiseInterface
    {
        return some([...$promises], $count);
    }

    /**
     * Traditional map function, similar to `array_map()`, but allows input to
     * contain promises and/or values, and `$callback` may return either a
     * value or a promise.
     *
     * The map function receives each item as argument, where item is a fully
     * resolved value of a promise or value in `$promises`.
     *
     * @psalm-param PromiseMapCallback $map
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param callable $map
     * @return PromiseInterface
     */
    public static function map(iterable $promises, callable $map): PromiseInterface
    {
        return map([...$promises], $map);
    }

    /**
     * Traditional reduce function, similar to `array_reduce()`, but input may
     * contain promises and/or values, and `$reduce` may return either a value
     * or a promise, *and* `$initial` may be a promise or a value for the
     * starting value.
     *
     * @psalm-param PromiseReduceCallback $reduce
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param callable $reduce
     * @param mixed $initial
     * @return PromiseInterface
     */
    public static function reduce(iterable $promises, callable $reduce, $initial = null): PromiseInterface
    {
        return reduce([...$promises], $reduce, $initial);
    }
}
