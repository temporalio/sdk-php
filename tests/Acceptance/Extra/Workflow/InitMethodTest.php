<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\InitMethod;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class InitMethodTest extends TestCase
{
    #[Test]
    public function updateHandlersWithOneCall(
        #[Stub('Extra_Workflow_InitMethod', args: [
            42,
            'foo',
            ['bar' => 'baz'],
            new \DateTimeImmutable('2021-01-01'),
            new stdClass(),
        ])] WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult();

        self::assertSame([true, true, true, true, true], $result);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $init;

    public function __construct() {
        $this->init = Workflow::getInput()->getValues();
    }

    #[WorkflowMethod(name: "Extra_Workflow_InitMethod")]
    public function handle(
        int $arg1,
        string $arg2,
        array $arg3,
        \DateTimeImmutable $arg4,
        stdClass $arg5,
    ): array {
        return [
            $arg1 === $this->init[0],
            $arg2 === $this->init[1],
            $arg3 === $this->init[2],
            $arg4 === $this->init[3],
            $arg5 === $this->init[4],
        ];
    }
}
