<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Client\WorkflowClientInterface;

/**
 * Temporal-side context for a Nexus operation handler invocation.
 *
 * Mirrors Java `io.temporal.nexus.NexusOperationContext`. The handler reads
 * `taskQueue` to resolve worker-local defaults, `namespace` to encode an
 * operation token (see {@see \Temporal\Internal\Nexus\WorkflowRunOperationToken}),
 * and `workflowClient` to start workflows / cancel them on the operation's
 * behalf.
 *
 * Set/cleared by {@see \Temporal\Internal\Nexus\NexusTaskHandler} around each
 * operation dispatch. Access through {@see Nexus::getOperationContext()}.
 *
 * @since Nexus support
 */
final class NexusOperationContext
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $taskQueue,
        public readonly WorkflowClientInterface $workflowClient,
    ) {}
}
