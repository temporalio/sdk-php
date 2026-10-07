<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

use function PHPUnit\Framework\assertFalse;

/**
 * @internal
 */
final class WorkerTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;

    /** @var WorkerMock|WorkerInterface */
    private $worker;

    public function testRunWorker(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SimpleWorkflow')]
                public function handler(): iterable
                {
                    $result = yield Workflow::awaitWithTimeout(5, static fn() => false);
                    assertFalse($result);
                    return $result;
                }
            },
        );

        $this->worker->runWorkflow('SimpleWorkflow');
        $this->worker->expectTimer(5);
        $this->factory->run($this->worker);
    }

    public function testDynamicWorkflowReceivesActualTypeAndRawArguments(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'DynamicWorkflow', dynamic: true)]
                public function handler(ValuesInterface $arguments): array
                {
                    return [
                        Workflow::getInfo()->type->name,
                        $arguments->getValues(),
                    ];
                }
            },
        );

        $this->worker->runWorkflow('RuntimeDefinedWorkflow', 'alpha', 42);
        $this->worker->assertWorkflowReturns(['RuntimeDefinedWorkflow', ['alpha', 42]]);

        $this->factory->run($this->worker);
        self::assertCount(1, $this->worker->getWorkflows());
    }

    public function testNamedWorkflowTakesPrecedenceOverDynamicWorkflow(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'DynamicWorkflow', dynamic: true)]
                public function handler(ValuesInterface $arguments): string
                {
                    return 'dynamic';
                }
            },
        );
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'NamedWorkflow')]
                public function handler(): string
                {
                    return 'named';
                }
            },
        );

        $this->worker->runWorkflow('NamedWorkflow');
        $this->worker->assertWorkflowReturns('named');

        $this->factory->run($this->worker);
        self::assertCount(2, $this->worker->getWorkflows());
    }

    public function testEachWorkerCanRegisterOneDynamicWorkflow(): void
    {
        $secondWorker = $this->factory->newWorker('other-task-queue');

        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'DynamicWorkflow', dynamic: true)]
                public function handler(): void {}
            },
        );
        $secondWorker->registerWorkflowTypes(PerWorkerDynamicWorkflow::class);

        self::assertCount(1, $this->worker->getWorkflows());
        self::assertCount(1, $secondWorker->getWorkflows());
    }

    protected function setUp(): void
    {
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();

        parent::setUp();
    }
}

#[Workflow\WorkflowInterface]
final class PerWorkerDynamicWorkflow
{
    #[WorkflowMethod(name: 'DynamicWorkflow', dynamic: true)]
    public function handler(): void {}
}
