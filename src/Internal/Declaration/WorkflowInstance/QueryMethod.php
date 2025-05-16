<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Temporal\Interceptor\WorkflowInbound\QueryInput;

/**
 * @internal
 */
final class QueryMethod
{
    /**
     * @param non-empty-string $name
     * @param \Closure(QueryInput): mixed $handler
     * @param string $description
     */
    public function __construct(
        public readonly string $name,
        public readonly \Closure $handler,
        public readonly string $description = '',
    ) {}
}
