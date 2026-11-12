<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\WorkflowRunOperation;

/**
 * @internal
 * @implements OperationHandlerInterface<mixed, mixed>
 */
final class MethodOperationHandler implements OperationHandlerInterface
{
    public function __construct(
        private readonly object $instance,
        private readonly \ReflectionMethod $startMethod,
        private readonly NexusOperationPrototype $operation,
    ) {}

    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        $args = $this->startMethod->getNumberOfParameters() === 0 ? [] : [$param];
        $result = $this->startMethod->invoke($this->instance, ...$args);

        if ($this->operation->async) {
            return OperationStartResult::async(WorkflowRunStarter::start($result, $details));
        }

        return OperationStartResult::sync($result);
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        WorkflowRunOperation::cancel($details->operationToken);
    }
}
