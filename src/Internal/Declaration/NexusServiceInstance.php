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
 * Live binding of a {@see NexusServicePrototype} to a concrete impl object,
 * with per-operation handlers pre-built and keyed by wire operation name.
 *
 * Built by
 * {@see \Temporal\Internal\Declaration\Instantiator\NexusServiceInstantiator}.
 *
 * Unlike {@see ActivityInstance} / {@see WorkflowInstance}, this class does
 * not extend {@see Instance}: a Nexus service has no single entry-point
 * handler — it dispatches across many operation methods, so the single
 * `MethodHandler` slot on the base does not apply.
 */
final class NexusServiceInstance
{
    /**
     * @param array<string, MethodOperationHandler> $operationHandlers Keyed by wire operation name.
     */
    public function __construct(
        public readonly NexusServicePrototype $prototype,
        public readonly object $context,
        public readonly array $operationHandlers,
    ) {}
}
