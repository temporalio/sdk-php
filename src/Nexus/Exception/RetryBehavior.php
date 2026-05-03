<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

/**
 * Allows handlers to explicitly set the retry behavior of a {@see HandlerException}.
 */
enum RetryBehavior: string
{
    case Unspecified = 'UNSPECIFIED';
    case Retryable = 'RETRYABLE';
    case NonRetryable = 'NON_RETRYABLE';
}
