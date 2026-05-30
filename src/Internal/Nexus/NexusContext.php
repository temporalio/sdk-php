<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\NexusOperationContext;

/**
 * Composite per-dispatch Nexus context, returned by {@see \Temporal\Nexus\Nexus::getCurrentContext()}.
 *
 * {@see $current} (the handler-side {@see OperationContext} with links/headers/deadline) is the
 * one invariant of a dispatch — it is always present. The remaining fields are conditional:
 * {@see $operation}/{@see $environment} are null until the worker environment is bound;
 * {@see $startDetails} and {@see $cancelDetails} are mutually exclusive (a dispatch is a start
 * XOR a cancel); {@see $outboundPipeline} is null when no interceptor pipeline applies. The
 * {@see $environment} (WorkflowClient) and {@see $outboundPipeline} are internal plumbing and
 * should not be consumed by user code.
 */
final class NexusContext
{
    /**
     * @param null|Pipeline<\Temporal\Interceptor\NexusOperationOutboundCallsInterceptor, mixed> $outboundPipeline
     * @internal $environment and $outboundPipeline are framework plumbing.
     */
    public function __construct(
        public readonly OperationContext $current,
        public readonly ?NexusOperationContext $operation = null,
        public readonly ?NexusEnvironment $environment = null,
        public readonly ?OperationStartDetails $startDetails = null,
        public readonly ?OperationCancelDetails $cancelDetails = null,
        public readonly ?Pipeline $outboundPipeline = null,
    ) {}
}
