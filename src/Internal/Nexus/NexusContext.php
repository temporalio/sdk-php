<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\NexusOperationContext;

/**
 * {@see $startDetails} and {@see $cancelDetails} are mutually exclusive.
 */
final class NexusContext
{
    /**
     * @param null|Pipeline<\Temporal\Interceptor\NexusOperationOutboundCallsInterceptor, mixed> $outboundPipeline
     */
    public function __construct(
        public readonly OperationContext $current,
        public readonly ?NexusOperationContext $operation = null,
        public readonly ?WorkflowClientInterface $workflowClient = null,
        public readonly ?OperationStartDetails $startDetails = null,
        public readonly ?OperationCancelDetails $cancelDetails = null,
        public readonly ?Pipeline $outboundPipeline = null,
    ) {}
}
