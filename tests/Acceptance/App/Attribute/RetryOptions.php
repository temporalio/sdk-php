<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Attribute;

use Temporal\Common\RetryOptions as CommonOptions;

/**
 * @see \Temporal\Tests\Acceptance\App\Feature\WorkflowStubInjector
 * @see \Temporal\Common\RetryOptions
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class RetryOptions
{
    /**
     * @param int<0, max> $maximumAttempts
     */
    public function __construct(
        public ?string $initialInterval = CommonOptions::DEFAULT_INITIAL_INTERVAL,
        public float $backoffCoefficient = CommonOptions::DEFAULT_BACKOFF_COEFFICIENT,
        public ?string $maximumInterval = CommonOptions::DEFAULT_MAXIMUM_INTERVAL,
        public int $maximumAttempts = CommonOptions::DEFAULT_MAXIMUM_ATTEMPTS,
        public array $nonRetryableExceptions = CommonOptions::DEFAULT_NON_RETRYABLE_EXCEPTIONS,
    ) {}

    public function toRetryOptions(): CommonOptions
    {
        return CommonOptions::new()
            ->withMaximumInterval($this->maximumInterval)
            ->withInitialInterval($this->initialInterval)
            ->withBackoffCoefficient($this->backoffCoefficient)
            ->withMaximumAttempts($this->maximumAttempts)
            ->withNonRetryableExceptions($this->nonRetryableExceptions);
    }
}
