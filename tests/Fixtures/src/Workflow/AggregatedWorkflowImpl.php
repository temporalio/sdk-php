<?php

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;

class AggregatedWorkflowImpl implements AggregatedWorkflow
{
    private array $values = [];

    public function addValue(
        string $value
    ) {
        $this->values[] = $value;
    }

    public function run(
        int $count
    ) {
        yield Workflow::await(fn() => count($this->values) === $count);

        return $this->values;
    }
}
