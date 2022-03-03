<?php

declare(strict_types=1);


namespace Temporal\WorkflowFactory;


use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;

interface WorkflowFactoryInterface
{
    public function create(string $workflowName): ?WorkflowPrototype;
}
