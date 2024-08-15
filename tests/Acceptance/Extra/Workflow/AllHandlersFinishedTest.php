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
    public function updateHandlersWithManyCalls(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        for ($i = 1; $i <= 9; ++$i) {
            /** @see TestWorkflow::addFromUpdate() */
            $stub->startUpdate('await', "key-$i");
        }

        for ($i = 1; $i <= 9; ++$i) {
            /** @see TestWorkflow::resolveFromUpdate */
            $stub->startUpdate('resolve', "key-$i", "resolved-$i");
        }

        // Should be completed after the previous operation
        $result = $stub->getResult(timeout: 1);

        $this->assertSame(
            [
                'key-1' => 'resolved-1',
                'key-2' => 'resolved-2',
                'key-3' => 'resolved-3',
                'key-4' => 'resolved-4',
                'key-5' => 'resolved-5',
                'key-6' => 'resolved-6',
                'key-7' => 'resolved-7',
                'key-8' => 'resolved-8',
                'key-9' => 'resolved-9',
            ],
            (array) $result,
            'Workflow result contains resolved values',
        );
    }

    #[Test]
    public function signalHandlersWithOneCall(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        /** @see TestWorkflow::addFromSignal() */
        $stub->signal('await', 'key');

        /** @see TestWorkflow::resolveFromSignal() */
        $stub->signal('resolve', "key", "resolved");

        $result = $stub->getResult(timeout: 1);

        $this->assertSame(['key' => 'resolved'], (array) $result, 'Workflow result contains resolved value');
    }

    #[Test]
    public function signalHandlersWithManyCalls(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        for ($i = 0; $i < 20; $i++) {
            /** @see TestWorkflow::addFromSignal() */
            $stub->signal('await', "key-$i");
        }

        for ($i = 0; $i < 20; $i++) {
            /** @see TestWorkflow::resolveFromSignal() */
            $stub->signal('resolve', "key-$i", "resolved-$i");
        }

        $result = $stub->getResult(timeout: 1);

        $this->assertSame(
            [
                'key-0' => 'resolved-0',
                'key-1' => 'resolved-1',
                'key-2' => 'resolved-2',
                'key-3' => 'resolved-3',
                'key-4' => 'resolved-4',
                'key-5' => 'resolved-5',
                'key-6' => 'resolved-6',
                'key-7' => 'resolved-7',
                'key-8' => 'resolved-8',
                'key-9' => 'resolved-9',
                'key-10' => 'resolved-10',
                'key-11' => 'resolved-11',
                'key-12' => 'resolved-12',
                'key-13' => 'resolved-13',
                'key-14' => 'resolved-14',
                'key-15' => 'resolved-15',
                'key-16' => 'resolved-16',
                'key-17' => 'resolved-17',
                'key-18' => 'resolved-18',
                'key-19' => 'resolved-19',
            ],
            (array) $result,
            'Workflow result contains resolved values',
        );
    }

    #[Test]
    public function warnUnfinishedSignals(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        $this->markTestSkipped("Can't check the log yet");

        for ($i = 0; $i < 8; $i++) {
            /** @see TestWorkflow::addFromSignal() */
            $stub->signal('await', "key-$i");
        }
        /** @see TestWorkflow::resolveFromSignal() */
        $stub->signal('resolve', 'foo');

        // Finish the workflow
        $stub->signal('exit');
        $stub->getResult(timeout: 1);

        // todo Check that `await` signal with count was mentioned in the logs
    }

    #[Test]
    public function warnUnfinishedUpdates(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
    ): void {
        $this->markTestSkipped("Can't check the log yet");

        for ($i = 0; $i < 8; $i++) {
            /** @see TestWorkflow::addFromSignal() */
            $stub->startUpdate('await', "key-$i");
        }
        /** @see TestWorkflow::resolveFromSignal() */
        $stub->startUpdate('resolve', 'foo');

        // Finish the workflow
        $stub->signal('exit');
        $stub->getResult(timeout: 1);

        // todo Check that `await` updates was mentioned in the logs
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $awaits = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_AllHandlersFinished")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => \count($this->awaits) > 0 && Workflow::allHandlersFinished(),
            fn(): bool => $this->exit,
        );
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
    #[Workflow\UpdateMethod(name: 'resolve', unfinishedPolicy: Workflow\HandlerUnfinishedPolicy::Abandon)]
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
    #[Workflow\SignalMethod(name: 'resolve', unfinishedPolicy: Workflow\HandlerUnfinishedPolicy::Abandon)]
    public function resolveFromSignal(string $name, mixed $value)
    {
        yield Workflow::await(fn(): bool => \array_key_exists($name, $this->awaits));
        $this->awaits[$name] = $value;
    }

    #[Workflow\SignalMethod()]
    public function exit(): void
    {
        $this->exit = true;
    }
}
