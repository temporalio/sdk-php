<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowA;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowATest extends TestCase
{
    #[Test]
    public function sendEmpty(
        #[Stub(type: 'Workflow')]
        WorkflowStubInterface $stub,
    ): void {
        $this->assertSame(42, $stub->getResult());
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Workflow")]
    public function handle()
    {
        return 42;
    }
}
