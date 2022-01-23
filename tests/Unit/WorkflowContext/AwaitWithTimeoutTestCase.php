<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\WorkflowContext;

use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Tests\Unit\UnitTestCase;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

final class AwaitWithTimeoutTestCase extends UnitTestCase
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

    public function testAwaitWithTimeoutReturnsFalseIfTimeoutWasOff(): void
    {
        $this->worker->registerWorkflowObject(
            new
            /**
             * Support for PHP7.4
             * @Workflow\WorkflowInterface
             */
            #[Workflow\WorkflowInterface]
            class {
                /**
                 * Support for PHP7.4
                 * @Workflow\WorkflowMethod(name="AwaitWorkflow")
                 */
                #[WorkflowMethod(name: 'AwaitWorkflow')]
                public function handler(): iterable
                {
                    $result = yield Workflow::awaitWithTimeout(5, fn() => false);
                    assertFalse($result);
                    return 'OK';
                }
            }
        );

        $this->worker->runWorkflow('AwaitWorkflow');
        $this->worker->assertWorkflowReturns('OK');
        $this->factory->run($this->worker);
    }

    public function testAwaitWithTimeoutStartsTimerWithConditionIsNotMet(): void
    {
        // We don't have native PHPUnit assertions in this scenario
        $this->expectNotToPerformAssertions();

        $this->worker->registerWorkflowObject(
            new
            /**
             * Support for PHP7.4
             * @Workflow\WorkflowInterface
             */
            #[Workflow\WorkflowInterface]
            class {
                /**
                 * Support for PHP7.4
                 * @Workflow\WorkflowMethod(name="AwaitWorkflow")
                 */
                #[WorkflowMethod(name: 'AwaitWorkflow')]
                public function handler(): iterable
                {
                    yield Workflow::awaitWithTimeout(5, fn() => false);
                    return 'OK';
                }
            }
        );

        $this->worker->runWorkflow('AwaitWorkflow');
        $this->worker->expectTimer(5);
        $this->worker->assertWorkflowReturns('OK');
        $this->factory->run($this->worker);
    }

    public function testAwaitWithTimeoutReturnsTrueWithMetCondition(): void
    {
        $this->worker->registerWorkflowObject(
            new
            /**
             * Support for PHP7.4
             * @Workflow\WorkflowInterface
             */
            #[Workflow\WorkflowInterface]
            class {
                /**
                 * Support for PHP7.4
                 * @Workflow\WorkflowMethod(name="AwaitWorkflow")
                 */
                #[WorkflowMethod(name: 'AwaitWorkflow')]
                public function handler(): iterable
                {
                    $result = yield Workflow::awaitWithTimeout(5, fn() => true);
                    assertTrue($result);
                    return 'OK';
                }
            }
        );

        $this->worker->runWorkflow('AwaitWorkflow');
        $this->worker->assertWorkflowReturns('OK');
        $this->factory->run($this->worker);
    }

    public function testTimerIsCanceledOnceConditionIsMet(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            /**
             * Support for PHP7.4
             * @Workflow\WorkflowInterface
             */
            #[Workflow\WorkflowInterface]
            class {
                /**
                 * Support for PHP7.4
                 * @Workflow\WorkflowMethod(name="AwaitWorkflow")
                 */
                #[WorkflowMethod(name: 'AwaitWorkflow')]
                public function handler(): iterable
                {
                    $this->doCancel = false;

                    yield Workflow::awaitWithTimeout(
                        50,
                        function() {
                            return $this->doCancel;
                        }
                    );

                    if ($this->doCancel) {
                        return 'CANCEL';
                    }

                    return 'OK';
                }

                /**
                 * Support for PHP7.4
                 * @Workflow\SignalMethod()
                 */
                #[Workflow\SignalMethod]
                public function cancel(): void
                {
                    $this->doCancel = true;
                }
            }
        );

        $this->worker->runWorkflow('AwaitWorkflow');
        $this->worker->sendSignal('AwaitWorkflow', 'cancel');
        $this->worker->assertWorkflowReturns('CANCEL');

        $this->factory->run($this->worker);
    }
}
