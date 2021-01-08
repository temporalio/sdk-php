<?php

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class QueryWorkflow
{
    private $counter = 0;

    #[Workflow\SignalMethod(name: "add")]
    public function add(int $value)
    {
        $this->counter += $value;
    }

    #[Workflow\QueryMethod(name: "get")]
    public function get(): int
    {
        return $this->counter;
    }

    #[WorkflowMethod(name: 'QueryWorkflow')]
    public function handler(): iterable
    {
        // collect signals during one second
        yield Workflow::timer(1);

        return $this->counter;
    }
}