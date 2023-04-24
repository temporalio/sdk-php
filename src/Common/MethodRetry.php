<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * Specifies a retry policy for a workflow or activity method. This annotation
 * applies only to activity or workflow interface methods. For workflows
 * currently used only for child workflow retries. Not required. When not used
 * either retries don't happen or they are configured through correspondent
 * options. If {@see RetryOptions} are present on {@see ActivityOptions} or
 * {@see ChildWorkflowOptions} the fields that are not default take precedence
 * over parameters of this attribute/annotation.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-import-type ExceptionsList from RetryOptions
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class MethodRetry extends RetryOptions
{
    /**
     * @param DateIntervalValue|null $initialInterval
     * @param DateIntervalValue|null $maximumInterval
     * @param int<0, max> $maximumAttempts
     * @param float $backoffCoefficient
     * @param ExceptionsList $nonRetryableExceptions
     */
    public function __construct(
        $initialInterval = self::DEFAULT_INITIAL_INTERVAL,
        $maximumInterval = self::DEFAULT_MAXIMUM_INTERVAL,
        int $maximumAttempts = self::DEFAULT_MAXIMUM_ATTEMPTS,
        float $backoffCoefficient = self::DEFAULT_BACKOFF_COEFFICIENT,
        array $nonRetryableExceptions = self::DEFAULT_NON_RETRYABLE_EXCEPTIONS
    ) {
        parent::__construct();

        $this->initialInterval = DateInterval::parseOrNull($initialInterval);
        $this->maximumInterval = DateInterval::parseOrNull($maximumInterval);
        $this->maximumAttempts = $maximumAttempts;
        $this->backoffCoefficient = $backoffCoefficient;
        $this->nonRetryableExceptions = $nonRetryableExceptions;
    }
}
