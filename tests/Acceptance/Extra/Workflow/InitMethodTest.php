<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\InitMethod;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\InitMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class InitMethodTest extends TestCase
{
    #[Test]
    public function updateHandlersWithOneCall(
        #[Stub(
            type: 'Extra_Workflow_InitMethod',
            args: [new Input('John Doe', 30)],
        )] WorkflowStubInterface $stub,
    ): void {
        $this->assertTrue($stub->getResult(timeout: 5));
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $initInput;

    #[InitMethod]
    public function __construct(Input $input) {
        $this->initInput = \func_get_args();
    }

    #[WorkflowMethod(name: "Extra_Workflow_InitMethod")]
    public function handle(Input $input)
    {
        return $this->initInput === \func_get_args();
    }
}

class Input
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}
