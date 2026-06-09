<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Nexus\Handler\Internal\MethodOperationHandler;

/**
 * Live binding of a {@see NexusServicePrototype} to its implementation,
 * with per-operation handlers keyed by wire operation name.
 */
final class NexusServiceInstance
{
    /**
     * @param array<string, MethodOperationHandler> $operationHandlers Keyed by wire operation name.
     */
    public function __construct(
        public readonly NexusServicePrototype $prototype,
        public readonly array $operationHandlers,
    ) {}
}
