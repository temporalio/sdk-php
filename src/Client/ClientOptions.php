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
     * @var string
     */
    public string $namespace = self::DEFAULT_NAMESPACE;

    /**
     * @var string
     */
    public string $identity;

    /**
     * @var int
     */
    #[ExpectedValues(valuesFromClass: QueryRejectCondition::class)]
    public int $queryRejectionCondition = QueryRejectCondition::QUERY_REJECT_CONDITION_NONE;

    /**
     * ClientOptions constructor.
     */
    public function __construct()
    {
        $this->identity = \sprintf('%d@%s', \getmypid(), \gethostname());
    }

    /**
     * @param string $namespace
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
     * @param string $identity
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
        int $condition
    ): self {
        assert(Assert::enum($condition, QueryRejectCondition::class));

        $self = clone $this;

        $self->queryRejectionCondition = $condition;

        return $self;
    }
}
