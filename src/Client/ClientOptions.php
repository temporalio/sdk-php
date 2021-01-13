<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Api\Enums\V1\QueryRejectCondition;

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
    public int $queryRejectionCondition = QueryRejectCondition::QUERY_REJECT_CONDITION_NONE;

    /**
     * ClientOptions constructor.
     */
    public function __construct()
    {
        $this->identity = \vsprintf(
            '%d@%s',
            [
                \getmypid(),
                \gethostname(),
            ]
        );
    }

    /**
     * @param string $namespace
     * @return $this
     */
    public function withNamespace(string $namespace): self
    {
        return immutable(fn() => $this->namespace = $namespace);
    }

    /**
     * @param string $identity
     * @return $this
     */
    public function withIdentity(string $identity): self
    {
        return immutable(fn() => $this->identity = $identity);
    }

    /**
     * @param int $condition
     * @return $this
     */
    public function withQueryRejectionCondition(int $condition): self
    {
        return immutable(fn() => $this->queryRejectionCondition = $condition);
    }
}
