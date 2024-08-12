<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\AllHandlersFinished;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class AllHandlersFinishedTest extends TestCase
{
    #[Test]
    public function updateHandlersWithOneCall(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::addFromUpdate */
        $handle = $stub->startUpdate('await', 'key');

        /** @see TestWorkflow::resolveFromUpdate */
        $resolver = $stub->startUpdate('resolve', "key", "resolved");

        // Should be completed after the previous operation
        $result = $stub->getResult(timeout: 1);

        $this->assertSame(['key' => 'resolved'], (array) $result, 'Workflow result contains resolved value');
        $this->assertFalse($handle->hasResult());
        $this->assertFalse($resolver->hasResult(), 'Resolver should not have result because of wait policy');
        // Fetch signal's result
        $this->assertSame('resolved', $handle->getResult());
        $this->assertTrue($handle->hasResult());
    }

    #[Test]
    public function signalHandlersWithOneCall(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::addFromSignal() */
        $stub->signal('await', 'key');

        /** @see TestWorkflow::resolveFromSignal() */
        $stub->signal('resolve', "key", "resolved");

        // Should be completed after the previous operation
        $result = $stub->getResult(timeout: 1);

        $this->assertSame(['key' => 'resolved'], (array) $result, 'Workflow result contains resolved value');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $awaits = [];

    #[WorkflowMethod(name: "Extra_Workflow_AllHandlersFinished")]
    public function handle()
    {
        yield Workflow::await(fn() => \count($this->awaits) > 0 && Workflow::allHandlersFinished());
        return $this->awaits;
    }

    /**
     * @param non-empty-string $name
     */
    #[Workflow\UpdateMethod(name: 'await')]
    public function addFromUpdate(string $name): mixed
    {
        $this->awaits[$name] ??= null;
        yield Workflow::await(fn() => $this->awaits[$name] !== null);
        return $this->awaits[$name];
    }

    /**
     * @param non-empty-string $name
     * @return PromiseInterface<mixed>
     */
    #[Workflow\UpdateMethod(name: 'resolve')]
    public function resolveFromUpdate(string $name, mixed $value): mixed
    {
        return $this->awaits[$name] = $value;
    }

    /**
     * @param non-empty-string $name
     */
    #[Workflow\SignalMethod(name: 'await')]
    public function addFromSignal(string $name)
    {
        $this->awaits[$name] ??= null;
        yield Workflow::await(fn() => $this->awaits[$name] !== null);
    }

    /**
     * @param non-empty-string $name
     */
    #[Workflow\SignalMethod(name: 'resolve')]
    public function resolveFromSignal(string $name, mixed $value): void
    {
        $this->awaits[$name] = $value;
    }
}
