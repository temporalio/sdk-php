<?php

declare(strict_types=1);

namespace Temporal\WorkflowFactory;

use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

final class EmptyWorkflowFactory implements WorkflowFactoryInterface
{
    public function create(string $workflowName): ?WorkflowPrototype
    {
        return null;
    }
}
