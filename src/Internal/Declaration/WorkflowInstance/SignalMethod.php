<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Temporal\Internal\Declaration\MethodHandler;

/**
 * @internal
 */
final class SignalMethod
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        public readonly MethodHandler $handler,
        public readonly string $description = '',
    ) {}
}
