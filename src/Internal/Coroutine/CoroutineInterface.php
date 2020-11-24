<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Coroutine;

/**
 * @template-covariant TKey
 * @template-covariant TValue
 * @template TSend
 * @template-covariant TReturn
 *
 * @template-extends \Iterator<TKey, TValue>
 */
interface CoroutineInterface extends \Iterator
{
    /**
     * @return TReturn Can return any type.
     */
    public function getReturn();

    /**
     * @param TSend $value
     * @return TValue Can return any type.
     */
    public function send($value);

    /**
     * @return TValue Can return any type.
     */
    public function throw(\Throwable $exception);
}
