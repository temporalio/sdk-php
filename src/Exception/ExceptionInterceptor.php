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
class ExceptionInterceptor implements ExceptionInterceptorInterface
{
    /**
     * @var array
     */
    private array $retryableErrors;

    /**
     * @param array $retryableErrors
     */
    public function __construct(array $retryableErrors)
    {
        $this->retryableErrors = $retryableErrors;
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    public function isRetryable(\Throwable $e): bool
    {
        foreach ($this->retryableErrors as $retryableError) {
            if ($e instanceof $retryableError) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return static
     */
    public static function createDefault(): self
    {
        return new self([\Error::class]);
    }
}
