<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\Exception\LengthException;
use React\Promise\PromiseInterface;

/**
 * @psalm-type PromiseMapCallback = callable(mixed $value): mixed
 * @psalm-type PromiseReduceCallback = callable(mixed $value): mixed
 */
interface PromiseAwareInterface
{
    /**
     * Returns a promise that will resolve only once all the items
     * in `$promises` have resolved. The resolution value of the returned
     * promise will be an array containing the resolution values of each of the
     * items in `$promises`.
     *
     * @param PromiseAwareInterface[] $promises
     * @return PromiseInterface
     */
    public function all(iterable $promises): PromiseInterface;

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
     * @param PromiseAwareInterface[] $promises
     * @return PromiseInterface
     */
    public function any(iterable $promises): PromiseInterface;

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
     * @param PromiseInterface[] $promises
     * @param int $count
     * @return PromiseInterface
     */
    public function some(iterable $promises, int $count): PromiseInterface;

    /**
     * Traditional map function, similar to `array_map()`, but allows input to
     * contain promises and/or values, and `$callback` may return either a
     * value or a promise.
     *
     * The map function receives each item as argument, where item is a fully
     * resolved value of a promise or value in `$promises`.
     *
     * @psalm-param PromiseMapCallback $map
     * @param PromiseInterface[] $promises
     * @param callable $map
     * @return PromiseInterface
     */
    public function map(iterable $promises, callable $map): PromiseInterface;

    /**
     * Traditional reduce function, similar to `array_reduce()`, but input may
     * contain promises and/or values, and `$reduce` may return either a value
     * or a promise, *and* `$initial` may be a promise or a value for the
     * starting value.
     *
     * @psalm-param PromiseReduceCallback $reduce
     * @param PromiseInterface[] $promises
     * @param callable $reduce
     * @param mixed $initial
     * @return PromiseInterface
     */
    public function reduce(iterable $promises, callable $reduce, $initial = null): PromiseInterface;
}
