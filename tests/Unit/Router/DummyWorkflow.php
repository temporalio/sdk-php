<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Router;

use Temporal\Workflow;

/**
 * Support for PHP7.4
 * @Temporal\Workflow\WorkflowInterface()
 */
#[Workflow\WorkflowInterface()]
final class DummyWorkflow
{
    #[Workflow\WorkflowMethod]
    public function doNothing(): void
    {
    }
}
