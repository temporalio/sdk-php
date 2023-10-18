<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowInbound;

use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-immutable
 */
class QueryInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $queryName,
        public readonly ValuesInterface $arguments,
    ) {
    }

    public function with(
        ValuesInterface $arguments = null,
    ): self {
        return new self(
            $this->queryName,
            $arguments ?? $this->arguments,
        );
    }
}
