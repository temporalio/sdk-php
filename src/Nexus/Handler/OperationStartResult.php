<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\OperationInfo;

/**
 * Sealed: every instance is {@see SyncOperationStartResult} or
 * {@see AsyncOperationStartResult}. Use {@see self::sync()} / {@see self::async()}.
 *
 * @template R
 */
abstract readonly class OperationStartResult
{
    /**
     * @internal
     */
    protected function __construct() {}

    /**
     * @template T
     * @param T|null $value
     * @return SyncOperationStartResult<T>
     */
    public static function sync(mixed $value = null): SyncOperationStartResult
    {
        return new SyncOperationStartResult($value);
    }

    public static function async(OperationInfo $info): AsyncOperationStartResult
    {
        return new AsyncOperationStartResult($info);
    }
}
