<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\InitMethod;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\InitMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class InitMethodTest extends TestCase
{
    #[Test]
    public function simpleCase(
        #[Stub(
            type: 'Extra_Workflow_InitMethod',
            args: [new Input('John Doe', 30)],
        )] WorkflowStubInterface $stub,
    ): void {
        $this->assertTrue($stub->getResult());
    }

    #[Test]
    public function emptyConstructor(
        #[Stub(
            type: 'Extra_Workflow_InitMethod__empty_constructor',
            args: [new Input('John Doe', 30)],
        )] WorkflowStubInterface $stub,
    ): void {
        $this->assertTrue($stub->getResult());
    }

    #[Test]
    public function differentConstructorParams(
        #[Stub(
            type: 'Extra_Workflow_InitMethod__different_constructor_params',
            executionTimeout: '2 seconds',
            args: [new Input('John Doe', 30)],
        )] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult();
        } catch (WorkflowFailedException $failure) {
            self:self::assertInstanceOf(TimeoutFailure::class, $failure->getPrevious());
        }
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $initInput;

    #[InitMethod]
    public function __construct(Input $input)
    {
        $this->initInput = \func_get_args();
    }

    #[WorkflowMethod(name: "Extra_Workflow_InitMethod")]
    public function handle(Input $input)
    {
        return $this->initInput === \func_get_args();
    }
}

#[WorkflowInterface]
class TestWorkflowEmptyConstructor
{
    private array $initInput;

    #[InitMethod]
    public function __construct()
    {
        $this->initInput = \func_get_args();
    }

    #[WorkflowMethod(name: "Extra_Workflow_InitMethod__empty_constructor")]
    public function handle(Input $input)
    {
        return $this->initInput === \func_get_args();
    }
}

#[WorkflowInterface]
class TestWorkflowDifferentConstructorParams
{
    private array $initInput;

    #[InitMethod]
    public function __construct(\stdClass $input)
    {
        $this->initInput = \func_get_args();
    }

    #[WorkflowMethod(name: "Extra_Workflow_InitMethod__different_constructor_params")]
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
