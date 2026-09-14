<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\Common\PayloadLimitOptions;
use Temporal\Internal\Assert;

/**
 * @psalm-type QueryRejectionConditionType = QueryRejectCondition::QUERY_REJECT_CONDITION_*
 */
class ClientOptions
{
    /**
     * @var string
     */
    public const DEFAULT_NAMESPACE = 'default';

    /**
     * @var non-empty-string
     */
    public string $namespace = self::DEFAULT_NAMESPACE;

    public string $identity;

    #[ExpectedValues(valuesFromClass: QueryRejectCondition::class)]
    public int $queryRejectionCondition = QueryRejectCondition::QUERY_REJECT_CONDITION_NONE;

    /**
     * Payload size limits at which the client logs a warning about the requests it sends.
     *
     * NULL means the default limits, see {@see PayloadLimitOptions::new()}. The warnings are on
     * by default and go to STDERR unless a logger is passed to the {@see \Temporal\Client\WorkflowClient};
     * pass {@see PayloadLimitOptions::disabled()} to turn them off.
     *
     * @experimental This API is experimental and may change in the future.
     */
    public ?PayloadLimitOptions $payloadLimits = null;

    /**
     * ClientOptions constructor.
     */
    public function __construct()
    {
        $this->identity = \sprintf('%d@%s', (string) \getmypid(), (string) \gethostname());
    }

    /**
     * Payload size limits at which the client logs a warning about the requests it sends.
     *
     * The limits of the Worker are configured separately, see
     * {@see \Temporal\Worker\WorkerOptions::withPayloadLimits()}.
     *
     * @param null|PayloadLimitOptions $options NULL restores the default limits,
     *        {@see PayloadLimitOptions::disabled()} turns the warnings off.
     *
     * @experimental This API is experimental and may change in the future.
     */
    #[Pure]
    public function withPayloadLimits(?PayloadLimitOptions $options): self
    {
        $self = clone $this;

        $self->payloadLimits = $options;

        return $self;
    }

    /**
     * @param non-empty-string $namespace
     * @return $this
     */
    #[Pure]
    public function withNamespace(string $namespace): self
    {
        $self = clone $this;

        $self->namespace = $namespace;

        return $self;
    }

    /**
     * @return $this
     */
    #[Pure]
    public function withIdentity(string $identity): self
    {
        $self = clone $this;

        $self->identity = $identity;

        return $self;
    }

    /**
     * @param QueryRejectionConditionType $condition
     * @return $this
     *
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withQueryRejectionCondition(
        #[ExpectedValues(valuesFromClass: QueryRejectCondition::class)]
        int $condition,
    ): self {
        \assert(Assert::enum($condition, QueryRejectCondition::class));

        $self = clone $this;

        $self->queryRejectionCondition = $condition;

        return $self;
    }
}
