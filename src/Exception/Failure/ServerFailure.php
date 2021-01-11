<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Failure;


class ServerFailure extends TemporalFailure
{
    /**
     * @var bool
     */
    private bool $nonRetryable;

    /**
     * @param string $message
     * @param bool $nonRetryable
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, bool $nonRetryable, \Throwable $previous = null)
    {
        parent::__construct(
            $message,
            $message,
            $previous
        );
        $this->nonRetryable = $nonRetryable;
    }

    /**
     * @return bool
     */
    public function isNonRetryable(): bool
    {
        return $this->nonRetryable;
    }
}
