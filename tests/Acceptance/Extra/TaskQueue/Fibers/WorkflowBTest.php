<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Fibers\WorkflowB;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowBTest extends TestCase
{
    #[Test]
    public function sendEmpty(
        #[Stub(type: 'Workflow')]
        WorkflowStubInterface $stub,
    ): void {
        $this->assertSame(24, $stub->getResult());
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Workflow")]
    public function handle()
    {
        return 24;
    }
}
