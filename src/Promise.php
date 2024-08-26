<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use React\Promise\Exception\LengthException;
use React\Promise\PromiseInterface;
use Temporal\Internal\Promise\CancellationQueue;

use Temporal\Internal\Promise\Reasons;

use function React\Promise\race;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * @psalm-type PromiseMapCallback = callable(mixed $value): mixed
 * @psalm-type PromiseReduceCallback = callable(mixed $value): mixed
 */
final class Promise
{
    /**
     * Returns a promise that resolves when all items in `$promises` have resolved.
     *
     * The returned promise's resolution value will be an array containing
     * the resolution values of each item in `$promises`.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @return PromiseInterface
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return self::map($promises, static fn ($val): mixed => $val);
    }

    /**
     * Returns a promise that resolves when any item in `$promises` resolves.
     *
     * The returned promise's resolution value will be the resolution value of
     * the triggering item. If **all** items in `$promises` are rejected, the
     * returned promise will reject with an array of all rejection reasons.
     *
     * If `$promises` is empty, the returned promise will reject with a
     * {@see LengthException}.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @return PromiseInterface
     */
    public static function any(iterable $promises): PromiseInterface
    {
        return self::some([...$promises], 1)
            ->then(static fn (array $values): mixed => \array_shift($values));
    }

    /**
     * Returns a promise that resolves when `$count` items in `$promises` resolve.
     *
     * The returned promise's resolution value will be an array of length `$count`
     * containing the resolution values of the triggering items.
     *
     * If it becomes impossible for `$count` items to resolve (i.e., when
     * `(count($promises) - $count) + 1` items reject), the returned promise will
     * reject with an iterable exception of `(count($promises) - $howMany) + 1`
     * rejection reasons.
     *
     * If `$promises` contains fewer items than `$count`, the returned promise will
     * reject with a {@see LengthException}.
     *
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param int $count
     * @return PromiseInterface
     */
    public static function some(iterable $promises, int $count): PromiseInterface
    {
        $cancellationQueue = new CancellationQueue();
        $cancellationQueue->enqueue($promises);

        return new \React\Promise\Promise(
            static function (callable $resolve, callable $reject) use ($promises, $count, $cancellationQueue): void {
                resolve($promises)->then(
                    static function (iterable $array) use ($count, $cancellationQueue, $resolve, $reject): void {
                        if (!\is_array($array) || $count < 1) {
                            $resolve([]);
                            return;
                        }

                        $len = \count($array);

                        if ($len < $count) {
                            $reject(new LengthException(
                                \sprintf(
                                    'Input array must contain at least %d item%s but contains only %s item%s.',
                                    $count,
                                    1 === $count ? '' : 's',
                                    $len,
                                    1 === $len ? '' : 's'
                                )
                            ));
                            return;
                        }

                        $toResolve = $count;
                        $toReject = ($len - $toResolve) + 1;
                        $values = [];
                        $reasons = [];

                        foreach ($array as $i => $promiseOrValue) {
                            $fulfiller = static function (mixed $val) use ($i, &$values, &$toResolve, $toReject, $resolve): void {
                                if ($toResolve < 1 || $toReject < 1) {
                                    return;
                                }

                                $values[$i] = $val;

                                if (0 === --$toResolve) {
                                    $resolve($values);
                                }
                            };

                            $rejecter = static function (\Throwable $reason) use ($i, &$reasons, &$toReject, $toResolve, $reject): void {
                                if ($toResolve < 1 || $toReject < 1) {
                                    return;
                                }

                                $reasons[$i] = $reason;

                                if (0 === --$toReject) {
                                    $reject(new Reasons($reasons));
                                }
                            };

                            $cancellationQueue->enqueue($promiseOrValue);

                            resolve($promiseOrValue)->then($fulfiller, $rejecter);
                        }
                    },
                    $reject
                );
            },
            $cancellationQueue
        );
    }

    /**
     * Applies a callback function to each item in `$promises`.
     *
     * This function behaves like `array_map()`, but it can handle input containing
     * promises and/or values, and the `$callback` may return either a value or a promise.
     *
     * The `$callback` function receives each item as an argument, where the item is
     * a fully resolved value of a promise or a value in `$promises`.
     *
     * @psalm-param PromiseMapCallback $map
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param callable $map
     * @return PromiseInterface
     */
    public static function map(iterable $promises, callable $map): PromiseInterface
    {
        $cancellationQueue = new CancellationQueue();
        $cancellationQueue->enqueue($promises);

        return new \React\Promise\Promise(
            function (callable $resolve, callable $reject) use ($promises, $map, $cancellationQueue): void {
                resolve($promises)
                    ->then(static function (iterable $array) use ($map, $cancellationQueue, $resolve, $reject): void {
                        if (!\is_array($array) || !$array) {
                            $resolve([]);
                            return;
                        }

                        $toResolve = \count($array);
                        $values = [];

                        foreach ($array as $i => $promiseOrValue) {
                            $cancellationQueue->enqueue($promiseOrValue);
                            $values[$i] = null;

                            resolve($promiseOrValue)
                                ->then($map)
                                ->then(
                                    static function (mixed $mapped) use ($i, &$values, &$toResolve, $resolve): void {
                                        $values[$i] = $mapped;

                                        if (0 === --$toResolve) {
                                            $resolve($values);
                                        }
                                    },
                                    $reject,
                                );
                        }
                    }, $reject);
            },
            $cancellationQueue
        );
    }

    /**
     * Applies a callback function to each item in `$promises`, reducing them to a single value.
     *
     * This function behaves like `array_reduce()`, but it can handle input containing
     * promises and/or values. The `$reduce` callback may return either a value or a promise,
     * and `$initial` may be a promise or a value for the starting value.
     *
     * @psalm-param PromiseReduceCallback $reduce
     * @param iterable<int, PromiseInterface|mixed> $promises
     * @param callable(mixed $current, mixed $carry, int $current, positive-int $items): mixed $reduce
     * @param mixed $initial
     * @return PromiseInterface
     */
    public static function reduce(iterable $promises, callable $reduce, $initial = null): PromiseInterface
    {
        $cancellationQueue = new CancellationQueue();
        $cancellationQueue->enqueue($promises);

        return new \React\Promise\Promise(
            function (callable $resolve, callable $reject) use ($promises, $reduce, $initial, $cancellationQueue): void {
                resolve($promises)
                    ->then(
                        static function (iterable $array) use (
                            $reduce,
                            $initial,
                            $cancellationQueue,
                            $resolve,
                            $reject,
                        ): void {
                            if (!\is_array($array)) {
                                $array = [];
                            }

                            $total = \count($array);
                            $i = 0;

                            // Wrap the supplied $reduce with one that handles promises and then
                            // delegates to the supplied.
                            $wrappedReduceFunc = static function (PromiseInterface $current, mixed $val) use (
                                $reduce,
                                $cancellationQueue,
                                $total,
                                &$i
                            ): PromiseInterface {
                                $cancellationQueue->enqueue($val);

                                return $current
                                    ->then(static function (mixed $c) use ($reduce, $total, &$i, $val): PromiseInterface {
                                        return resolve($val)
                                            ->then(static function (mixed $value) use ($reduce, $total, &$i, $c): mixed {
                                                return $reduce($c, $value, $i++, $total);
                                            });
                                    });
                            };

                            $cancellationQueue->enqueue($initial);

                            \array_reduce($array, $wrappedReduceFunc, resolve($initial))
                                ->then($resolve, $reject);
                        },
                        $reject,
                    );
            },
            $cancellationQueue
        );
    }

    /**
     * Creates a fulfilled promise for the supplied `$promiseOrValue`.
     *
     * If `$promiseOrValue` is a value, it will be the resolution value of the
     * returned promise.
     *
     * If `$promiseOrValue` is a thenable (any object that provides a `then()` method),
     * a trusted promise that follows the state of the thenable is returned.
     *
     * If `$promiseOrValue` is a promise, it will be returned as is.
     *
     * @param $promiseOrValue
     * @return PromiseInterface
     */
    public static function resolve($promiseOrValue = null): PromiseInterface
    {
        return resolve($promiseOrValue);
    }

    /**
     * Creates a rejected promise for the supplied `$promiseOrValue`.
     *
     * If `$promiseOrValue` is a value, it will be the rejection value of the
     * returned promise.
     *
     * If `$promiseOrValue` is a promise, its completion value will be the rejected
     * value of the returned promise.
     *
     * This can be useful in situations where you need to reject a promise without
     * throwing an exception. For example, it allows you to propagate a rejection with
     * the value of another promise.
     *
     * @param $promiseOrValue
     * @return PromiseInterface<never>
     */
    public static function reject($promiseOrValue = null): PromiseInterface
    {
        return reject($promiseOrValue);
    }

    /**
     * Initiates a competitive race that allows one winner. Returns a promise which is
     * resolved in the same way the first settled promise resolves.
     *
     * The returned promise will become **infinitely pending** if  `$promisesOrValues`
     * contains 0 items.
     *
     * @template T
     * @param iterable<PromiseInterface<T>|T> $promisesOrValues
     * @return PromiseInterface<T>
     */
    public static function race(iterable $promisesOrValues): PromiseInterface
    {
        return race([...$promisesOrValues]);
    }
}
