<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;

/**
 * @internal
 */
final class UpdateMethod
{
    /**
     * @param non-empty-string $name
     * @param \Closure(UpdateInput, Deferred): PromiseInterface $handler
     * @param null|\Closure(UpdateInput): void $validator
     */
    public function __construct(
        public readonly string $name,
        public readonly \Closure $handler,
        public readonly ?\Closure $validator,
        public readonly string $description = '',
    ) {}
}
