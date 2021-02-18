<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

/**
 * Exception interceptor provides the ability to let workflow know if exception should be treated as fatal (stops execution)
 * or must only fail the task execution (with consecutive retry).
 */
interface ExceptionInterceptorInterface
{
    /**
     * @param \Throwable $e
     * @return bool
     */
    public function isRetryable(\Throwable $e): bool;
}
