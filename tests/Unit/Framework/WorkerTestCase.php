<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework;

use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

use function PHPUnit\Framework\assertFalse;

/**
 * @internal
 */
final class WorkerTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;
    /** @var WorkerMock|WorkerInterface */
    private $worker;

    protected function setUp(): void
    {
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();

        parent::setUp();
    }

    public function testRunWorker(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SimpleWorkflow')]
                public function handler(): iterable
                {
                    $result = yield Workflow::awaitWithTimeout(5, fn() => false);
                    assertFalse($result);
                    return $result;
                }
            }
        );

        $this->worker->runWorkflow('SimpleWorkflow');
        $this->worker->expectTimer(5);
        $this->factory->run($this->worker);
    }
}
