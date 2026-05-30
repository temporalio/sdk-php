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
 * Carries the public Temporal-side {@see $operation} info and the handler-side
 * {@see $current} ({@see OperationContext} with links/headers/deadline). The
 * {@see $environment} (WorkflowClient) and {@see $outboundPipeline} are internal
 * plumbing and should not be consumed by user code.
 */
final class NexusContext
{
    /**
     * @param null|Pipeline<\Temporal\Interceptor\NexusOperationOutboundCallsInterceptor, mixed> $outboundPipeline
     * @internal $environment and $outboundPipeline are framework plumbing.
     */
    public function __construct(
        public readonly ?NexusOperationContext $operation = null,
        public readonly ?NexusEnvironment $environment = null,
        public readonly ?OperationContext $current = null,
        public readonly ?OperationStartDetails $startDetails = null,
        public readonly ?OperationCancelDetails $cancelDetails = null,
        public readonly ?Pipeline $outboundPipeline = null,
    ) {}

    public function withOperation(?NexusOperationContext $operation): self
    {
        return new self($operation, $this->environment, $this->current, $this->startDetails, $this->cancelDetails, $this->outboundPipeline);
    }

    public function withEnvironment(?NexusEnvironment $environment): self
    {
        return new self($this->operation, $environment, $this->current, $this->startDetails, $this->cancelDetails, $this->outboundPipeline);
    }

    public function withCurrent(?OperationContext $current): self
    {
        return new self($this->operation, $this->environment, $current, $this->startDetails, $this->cancelDetails, $this->outboundPipeline);
    }

    public function withStartDetails(?OperationStartDetails $details): self
    {
        return new self($this->operation, $this->environment, $this->current, $details, $this->cancelDetails, $this->outboundPipeline);
    }

    public function withCancelDetails(?OperationCancelDetails $details): self
    {
        return new self($this->operation, $this->environment, $this->current, $this->startDetails, $details, $this->outboundPipeline);
    }
}
