<?php

declare(strict_types=1);

namespace React\Promise;

/**
 * @note This is a stub interface for better IDE support.
 *
 * @yield T
 * @template-covariant T
 */
interface PromiseInterface
{
    /**
     * Transforms a promise's value by applying a function to the promise's fulfillment
     * or rejection value. Returns a new promise for the transformed result.
     *
     * The `then()` method registers new fulfilled and rejection handlers with a promise
     * (all parameters are optional):
     *
     *  * `$onFulfilled` will be invoked once the promise is fulfilled and passed
     *     the result as the first argument.
     *  * `$onRejected` will be invoked once the promise is rejected and passed the
     *     reason as the first argument.
     *  * `$onProgress` (deprecated) will be invoked whenever the producer of the promise
     *     triggers progress notifications and passed a single argument (whatever it
     *     wants) to indicate progress.
     *
     * It returns a new promise that will fulfill with the return value of either
     * `$onFulfilled` or `$onRejected`, whichever is called, or will reject with
     * the thrown exception if either throws.
     *
     * A promise makes the following guarantees about handlers registered in
     * the same call to `then()`:
     *
     *  1. Only one of `$onFulfilled` or `$onRejected` will be called,
     *      never both.
     *  2. `$onFulfilled` and `$onRejected` will never be called more
     *      than once.
     *  3. `$onProgress` (deprecated) may be called multiple times.
     *
     * @template TFulfilled
     * @template TRejected
     * @param ?(callable((T is void ? null : T)): (PromiseInterface<TFulfilled>|TFulfilled)) $onFulfilled
     * @param ?(callable(\Throwable): (PromiseInterface<TRejected>|TRejected)) $onRejected
     * @return PromiseInterface<($onRejected is null ? ($onFulfilled is null ? T : TFulfilled) : ($onFulfilled is null ? T|TRejected : TFulfilled|TRejected))>
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null, ?callable $onProgress = null);
}
