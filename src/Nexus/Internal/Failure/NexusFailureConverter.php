<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Internal\Failure;

use Temporal\Api\Enums\V1\NexusHandlerErrorRetryBehavior;
use Temporal\Nexus\Exception\RetryBehavior;

/**
 * @internal
 */
final class NexusFailureConverter
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function mapRetryBehavior(RetryBehavior $behavior): int
    {
        return match ($behavior) {
            RetryBehavior::Retryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_RETRYABLE,
            RetryBehavior::NonRetryable => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_NON_RETRYABLE,
            RetryBehavior::Unspecified => NexusHandlerErrorRetryBehavior::NEXUS_HANDLER_ERROR_RETRY_BEHAVIOR_UNSPECIFIED,
        };
    }
}
