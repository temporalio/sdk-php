<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\WorkflowContext;

use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\WorkflowFactory\WorkflowFactoryInterface;

final class WorkflowFactoryTestCase extends UnitTestCase
{
    private WorkerFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = WorkerFactoryMock::create();

        parent::setUp();
    }

    public function testWorkflowFactoryCanCreateWorkflowInRuntime(): void
    {
        $workflowFactory = new class implements WorkflowFactoryInterface {
            public function create(string $workflowName): ?object
            {
                return new
                    /**
                     * Support for PHP7.4
                     * @Workflow\WorkflowInterface
                     */
                    #[Workflow\WorkflowInterface]
                    class {
                        /**
                         * Support for PHP7.4
                         * @Workflow\WorkflowMethod(name="SimpleWorkflow")
                         */
                        #[WorkflowMethod(name: 'SimpleWorkflow')]
                        public function handler()
                        {
                            return 'hello';
                        }
                    };
            }
        };

        /** @var WorkerMock|WorkerInterface $worker */
        $worker = $this->factory->newWorker(
            'default',
            null,
            null,
            $workflowFactory
        );

        $this->addToAssertionCount(1); // For workflow result assertion
        $worker->runWorkflow('SimpleWorkflow');
        $worker->assertWorkflowReturns('hello');
        $this->factory->run($worker);
    }
}
