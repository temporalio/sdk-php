<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Client\WorkflowClientInterface;

/**
 * Temporal-side context exposed to a Nexus operation handler.
 * Set by NexusTaskHandler around each dispatch, read via {@see Nexus::getOperationContext()}.
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
