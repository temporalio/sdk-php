<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Nexus\Exception\HandlerException;
use Temporal\Nexus\Exception\OperationException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\Handler\OperationStartResult;
use Temporal\Nexus\NexusOperationContext;

/**
 * @internal
 */
interface HandlerInterface
{
    /**
     * @return OperationStartResult<ValuesInterface>
     *
     * @throws OperationException
     * @throws HandlerException
     */
    public function startOperation(
        OperationContext $context,
        OperationStartDetails $details,
        ValuesInterface $input,
        ?WorkflowClientInterface $workflowClient,
        NexusOperationContext $operationContext,
    ): OperationStartResult;

    /**
     * Idempotent per Nexus spec: repeat or already-terminal cancels must return successfully;
     * throw {@see HandlerException} only for genuine routing/permission/transport errors.
     *
     * @throws HandlerException
     */
    public function cancelOperation(
        OperationContext $context,
        OperationCancelDetails $details,
        ?WorkflowClientInterface $workflowClient,
        NexusOperationContext $operationContext,
    ): void;
}
