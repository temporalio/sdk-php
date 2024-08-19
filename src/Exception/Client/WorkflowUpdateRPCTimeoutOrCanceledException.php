<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

/**
 * Occurs when an update call times out or is cancelled.
 *
 * @note this is not related to any general concept of timing out or cancelling a running update,
 *       this is only related to the client call itself.
 */
class WorkflowUpdateRPCTimeoutOrCanceledException extends TimeoutException {
    public static function fromTimeoutException(TimeoutException $exception): self
    {
        return new self(
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getPrevious(),
        );
    }
}
