<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface()]
final class DummyWorkflow
{
    #[WorkflowMethod]
    public function doNothing(): void
    {
    }
}
