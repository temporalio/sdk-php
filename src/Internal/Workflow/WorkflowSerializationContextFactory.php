<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\DataConverter\WorkflowSerializationContext;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInfo;

final class WorkflowSerializationContextFactory
{
    public static function fromInfo(WorkflowInfo $info): WorkflowSerializationContext
    {
        return new WorkflowSerializationContext($info->namespace, $info->execution->getID());
    }

    public static function fromTarget(string $namespace, string $workflowId): WorkflowSerializationContext
    {
        if ($namespace === '') {
            $namespace = Workflow::getCurrentContext()->getInfo()->namespace;
        }

        return new WorkflowSerializationContext($namespace, $workflowId);
    }
}
