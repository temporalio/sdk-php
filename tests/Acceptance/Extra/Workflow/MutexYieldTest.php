<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\MutexYield;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class MutexYieldTest extends TestCase
{
    #[Test]
    public function runWithUnblockUnblock(
        #[Stub('Extra_Workflow_MutexYield')]
        WorkflowStubInterface $stub,
    ): void {
        $historyLength = $stub->describe()->info->historyLength;
        $stub->signal('unlock');

        // Wait the signal to be processed
        $deadline = \microtime(true) + 5;
        do {
            $description = $stub->describe();

            if (\microtime(true) > $deadline) {
                $this->fail('Signal was not processed');
            }
            // Signal + 3 Workflow Tasks
        } while ($description->info->historyLength < 4 + $historyLength);

        $stub->signal('unlock');
        $result = $stub->getResult();

        $this->assertFalse($result[0]);
        $this->assertFalse($result[1]);
    }

    #[Test]
    public function runWithUnblockExit(
        #[Stub('Extra_Workflow_MutexYield')]
        WorkflowStubInterface $stub,
    ): void {
        $historyLength = $stub->describe()->info->historyLength;
        $stub->signal('unlock');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertFalse($result[0]);
        $this->assertTrue($result[1]);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private Workflow\Mutex $mutex;
    private bool $exit = false;

    public function __construct()
    {
        $this->mutex = new Workflow\Mutex();
        $this->mutex->lock();
    }

    #[WorkflowMethod(name: "Extra_Workflow_MutexYield")]
    #[Workflow\ReturnType(Type::TYPE_ARRAY)]
    public function handle(): \Generator
    {
        yield $this->mutex;
        $yieldLocked = $this->mutex->isLocked();

        $this->mutex->lock();

        yield Workflow::await(
            $this->mutex,
            fn() => $this->exit,
        );
        $awaitLocked = $this->mutex->isLocked();

        return [$yieldLocked, $awaitLocked];
    }

    #[Workflow\SignalMethod]
    public function unlock(): void
    {
        $this->mutex->unlock();
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
