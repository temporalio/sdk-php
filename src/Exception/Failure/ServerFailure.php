<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

class ServerFailure extends TemporalFailure
{
    private bool $nonRetryable;

    public function __construct(string $message, bool $nonRetryable, ?\Throwable $previous = null)
    {
        parent::__construct(
            $message,
            $message,
            $previous,
        );
        $this->nonRetryable = $nonRetryable;
    }

    public function isNonRetryable(): bool
    {
        return $this->nonRetryable;
    }
}
