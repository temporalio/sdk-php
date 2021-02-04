<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Client\WorkflowProxy;

/**
 * Helper class used to convert typed workflow stubs to their untyped alternatives to gain access to cancel() and terminate() methods.
 */
class WorkflowStub
{
    /**
     * Get untyped workflow stub using provided workflow proxy or workflow stub instance.
     *
     * @param WorkflowStubInterface|object $workflow
     * @return WorkflowStubInterface
     */
    public static function fromWorkflow($workflow): WorkflowStubInterface
    {
        $workflowStub = null;
        if ($workflow instanceof WorkflowProxy) {
            $workflowStub = $workflow->__getUntypedStub();
        }

        if ($workflow instanceof WorkflowStubInterface) {
            $workflowStub = $workflow;
        }

        if ($workflowStub === null) {
            throw new InvalidArgumentException(
                \sprintf('Only workflow stubs can be started, %s given', \get_debug_type($workflow))
            );
        }

        return $workflowStub;
    }
}
