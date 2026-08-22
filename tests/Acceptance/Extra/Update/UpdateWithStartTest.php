<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\UpdateWithStart;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UpdateWithStartTest extends TestCase
{
    #[Test]
    public function runInGoodWay(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        /** @see TestWorkflow::add */
        $handle = $client->updateWithStart($stub, 'await', ['key']);

        // Complete workflow
        /** @see TestWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => null], (array)$result);
        $this->assertFalse($handle->hasResult());
    }

    #[Test]
    public function returnsTypedUpdateResult(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        $options = UpdateOptions::new('echo', LifecycleStage::StageCompleted)
            ->withResultType(UpdateResult::class);

        /** @see TestWorkflow::echo */
        $handle = $client->updateWithStart($stub, $options, ['hello']);

        $result = $handle->getResult();
        $this->assertInstanceOf(UpdateResult::class, $result);
        $this->assertSame('hello', $result->name);
        $this->assertSame(5, $result->length);

        $stub->signal('exit');
        $stub->getResult();
    }

    #[Test]
    public function rejectsFirstExecutionRunId(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        $options = UpdateOptions::new('await', LifecycleStage::StageAccepted)
            ->withFirstExecutionRunId(Uuid::uuid7()->__toString());

        $this->expectException(WorkflowServiceException::class);
        $this->expectExceptionMessage('FirstExecutionRunId is not allowed');
        $client->updateWithStart($stub, $options, ['key']);
    }

    #[Test]
    public function failWithBadUpdateName(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );

        try {
            $client->updateWithStart($stub, 'await1234', ['key']);
            $this->fail('Update must fail');
        } catch (WorkflowUpdateException $e) {
            $this->assertStringContainsString('await1234', $e->getPrevious()->getMessage());
        } finally {
            try {
                $stub->getResult();
                $this->fail('Workflow must fail');
            } catch (WorkflowFailedException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function failOnReuseExistingWorkflowId(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::uuid7()->__toString();
        $stub1 = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue)->withWorkflowId($id),
        );
        $stub2 = $client->newUntypedWorkflowStub(
            'Extra_Update_UpdateWithStart',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue)->withWorkflowId($id),
        );

        // Run first
        /** @see TestWorkflow::add */
        $client->updateWithStart($stub1, 'await', ['key']);
        try {
            $this->expectException(WorkflowExecutionAlreadyStartedException::class);
            // Run second
            $client->updateWithStart($stub2, 'await', ['key']);
        } finally {
            $stub1->signal('exit');
        }
    }

    #[Test]
    public function useExistingReturnsRunningExecution(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::uuid7()->__toString();

        $first = $client->newUntypedWorkflowStub(
            'Extra_Update_UseExisting',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue)->withWorkflowId($id),
        );
        $client->start($first);
        $firstRunId = $first->getExecution()->getRunID();

        $second = $client->newUntypedWorkflowStub(
            'Extra_Update_UseExisting',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowId($id)
                ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting),
        );

        try {
            $client->start($second);

            $this->assertSame($id, $second->getExecution()->getID());
            $this->assertSame(
                $firstRunId,
                $second->getExecution()->getRunID(),
                'UseExisting must resolve to the already-running execution instead of throwing',
            );
        } finally {
            $first->signal('exit');
        }
    }

    #[Test]
    public function failPolicyThrowsOnRunningWorkflowId(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::uuid7()->__toString();

        $first = $client->newUntypedWorkflowStub(
            'Extra_Update_UseExisting',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue)->withWorkflowId($id),
        );
        $client->start($first);

        $second = $client->newUntypedWorkflowStub(
            'Extra_Update_UseExisting',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowId($id)
                ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::Fail),
        );

        try {
            $this->expectException(WorkflowExecutionAlreadyStartedException::class);
            $client->start($second);
        } finally {
            $first->signal('exit');
        }
    }

    #[Test]
    public function useExistingStartsFreshWhenNoneRunning(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $id = Uuid::uuid7()->__toString();

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Update_UseExisting',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withWorkflowId($id)
                ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::UseExisting),
        );

        try {
            $client->start($stub);

            $this->assertSame($id, $stub->getExecution()->getID());
            $this->assertNotSame('', $stub->getExecution()->getRunID());
        } finally {
            $stub->signal('exit');
        }
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $awaits = [];
    private bool $updateStarted = false;
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Update_UpdateWithStart")]
    public function handle()
    {
        $this->updateStarted or throw new \RuntimeException('Not started with update');
        yield Workflow::await(fn() => $this->exit);
        return $this->awaits;
    }

    /**
     * @param non-empty-string $name
     * @return mixed
     */
    #[Workflow\UpdateMethod(name: 'await')]
    public function add(string $name): mixed
    {
        $this->updateStarted = true;
        $this->awaits[$name] ??= null;
        yield Workflow::await(fn() => $this->awaits[$name] !== null);
        return $this->awaits[$name];
    }

    #[Workflow\UpdateValidatorMethod(forUpdate: 'await')]
    public function validateAdd(string $name): void
    {
        empty($name) and throw new \InvalidArgumentException('Name must not be empty');
    }

    #[Workflow\UpdateMethod(name: 'echo')]
    public function echo(string $name): UpdateResult
    {
        $this->updateStarted = true;
        return new UpdateResult($name, \strlen($name));
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}

class UpdateResult
{
    public function __construct(
        public string $name = '',
        public int $length = 0,
    ) {}
}

#[WorkflowInterface]
class UseExistingWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: 'Extra_Update_UseExisting')]
    public function handle(): \Generator
    {
        yield Workflow::await(fn(): bool => $this->exit);
        return 'done';
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
