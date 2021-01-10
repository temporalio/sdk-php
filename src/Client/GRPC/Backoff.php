<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Client\GRPC;

/**
 * Retries call until it succeed.
 */
class Backoff
{
    /**
     * Invokes a function. In case of exception will attempt to retry call.
     *
     * @param callable $callable
     * @return mixed
     */
    public function invokeOrRetry(callable $callable)
    {
    }
}
