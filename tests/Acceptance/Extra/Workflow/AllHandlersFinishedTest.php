<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\AllHandlersFinished;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
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
        $result = $stub->getResult();

        $this->assertSame(['key' => 'resolved'], (array) $result, 'Workflow result contains resolved value');
        $this->assertFalse($handle->hasResult());

        // Since Temporal CLI 1.2.0, the result is available immediately after the operation
        $this->assertTrue($resolver->hasResult());
        $this->assertSame('resolved', $resolver->getResult());

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
        $result = $stub->getResult();

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

        $result = $stub->getResult();

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

        $result = $stub->getResult();

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
        ClientLogger $logger,
        Feature $feature,
    ): void {
        /** @see TestWorkflow::resolveFromSignal() */
        $stub->signal('resolve', 'foo', 42);
        $stub->signal('resolve', 'bar', 42);

        for ($i = 0; $i < 8; $i++) {
            /** @see TestWorkflow::addFromSignal() */
            $stub->signal('await', "key-$i");
        }

        // Finish the workflow
        $stub->signal('exit');
        $stub->getResult();

        // Check logs
        $records = $logger->getRecords();
        self::assertCount(1, $records);
        $record = $records[0];
        self::assertStringContainsString(
            'Workflow `Extra_Workflow_AllHandlersFinished` finished while signal handlers are still running.',
            $record->message,
        );
        self::assertStringContainsString('`await` x8', $record->message);
        self::assertSame('warning', $record->level);
        // Compare context
        self::assertSame($stub->getExecution()->getID(), $record->context['workflow_id']);
        self::assertSame($stub->getExecution()->getRunID(), $record->context['run_id']);
        self::assertSame('Extra_Workflow_AllHandlersFinished', $record->context['workflow_type']);
        self::assertSame($feature->taskQueue, $record->context['task_queue']);
    }

    #[Test]
    public function warnUnfinishedUpdates(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
        ClientLogger $logger,
        Feature $feature,
    ): void {
        /** @var list<UpdateHandle> $updates */
        $updates = [];
        for ($i = 0; $i < 8; $i++) {
            /** @see TestWorkflow::addFromUpdate() */
            $updates[] = $stub->startUpdate('await', "key-$i");
        }
        /** @see TestWorkflow::resolveFromUpdate() */
        $stub->startUpdate('resolve', 'foo', 42);

        // Finish the workflow
        $stub->signal('exit');
        $stub->getResult();

        // Check logs
        $records = $logger->getRecords();
        self::assertCount(1, $records);
        $record = $records[0];
        self::assertStringContainsString(
            'Workflow `Extra_Workflow_AllHandlersFinished` finished while update handlers are still running.',
            $record->message,
        );
        foreach ($updates as $update) {
            self::assertStringContainsString('`await` id:' . $update->getId(), $record->message);
        }
        self::assertSame('warning', $record->level);
        // Compare context
        self::assertSame($stub->getExecution()->getID(), $record->context['workflow_id']);
        self::assertSame($stub->getExecution()->getRunID(), $record->context['run_id']);
        self::assertSame('Extra_Workflow_AllHandlersFinished', $record->context['workflow_type']);
        self::assertSame($feature->taskQueue, $record->context['task_queue']);
    }

    #[Test]
    public function warnUnfinishedOnCancel(
        #[Stub('Extra_Workflow_AllHandlersFinished')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        /** @see TestWorkflow::addFromSignal() */
        $stub->signal('await', "key-sig");

        /** @see TestWorkflow::addFromUpdate() */
        $stub->startUpdate('await', "key-upd");

        // Make sure that the previous update was started before cancellation
        $stub->update('resolve', "ping", "pong");

        // Finish the workflow
        $stub->cancel();

        try {
            $stub->getResult();
            $this->fail('Cancellation exception must be thrown');
        } catch (WorkflowFailedException) {
            // Expected
        }

        // Check logs
        $records = $logger->getRecords();
        self::assertCount(2, $records);
        self::assertStringContainsString(
            'Workflow `Extra_Workflow_AllHandlersFinished` cancelled while update handlers are still running.',
            $records[0]->message,
        );
        self::assertStringContainsString(
            'Workflow `Extra_Workflow_AllHandlersFinished` cancelled while signal handlers are still running.',
            $records[1]->message,
        );
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
