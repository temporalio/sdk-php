<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Common\RetryOptions;

/**
 * RetryPolicy specifies how to retry an Activity if an error occurs.
 *
 * More details are available at https://docs.temporal.io/docs/concepts/activities
 * RetryPolicy is optional. If one is not specified a default RetryPolicy is provided by the server.
 *
 * The default RetryPolicy provided by the server specifies:
 * - InitialInterval of 1 second
 * - BackoffCoefficient of 2.0
 * - MaximumInterval of 100 x InitialInterval
 * - MaximumAttempts of 0 (unlimited)
 *
 * To disable retries set MaximumAttempts to 1.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class RetryPolicy
{
    public RetryOptions $options;

    public function __construct(RetryOptions|array $options)
    {
        if (\is_array($options)) {
            $options = new RetryOptions(
                initialInterval: $options['initialInterval'] ?? $options['initial_interval'] ?? null,
                backoffCoefficient: $options['backoffCoefficient'] ?? $options['backoff_coefficient'] ?? RetryOptions::DEFAULT_BACKOFF_COEFFICIENT,
                maximumInterval: $options['maximumInterval'] ?? $options['maximum_interval'] ?? null,
                maximumAttempts: $options['maximumAttempts'] ?? $options['maximum_attempts'] ?? RetryOptions::DEFAULT_MAXIMUM_ATTEMPTS,
                nonRetryableExceptions: $options['nonRetryableExceptions'] ?? $options['non_retryable_error_types'] ?? [],
            );
        }
        $this->options = $options;
    }

    /**
     * Named constructor for fluent interface.
     */
    public static function new(
        \DateInterval|string|int|null $initialInterval = null,
        float $backoffCoefficient = 2.0,
        \DateInterval|string|int|null $maximumInterval = null,
        int $maximumAttempts = 0,
        array $nonRetryableExceptions = [],
    ): self {
        return new self(
            new RetryOptions(
                initialInterval: $initialInterval,
                backoffCoefficient: $backoffCoefficient,
                maximumInterval: $maximumInterval,
                maximumAttempts: $maximumAttempts,
                nonRetryableExceptions: $nonRetryableExceptions,
            ),
        );
    }
}
