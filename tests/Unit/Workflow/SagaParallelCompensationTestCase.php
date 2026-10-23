<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Workflow;

use Temporal\Exception\CompensationException;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\Saga;
use Temporal\Workflow\WorkflowMethod;

final class SagaParallelCompensationTestCase extends AbstractUnit
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

    public function testParallelCompensationHappyPathCompletes(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(true);
                    $log = [];
                    $saga->addCompensation(static function () use (&$log): void {
                        $log[] = 'A';
                    });
                    $saga->addCompensation(static function () use (&$log): void {
                        $log[] = 'B';
                    });

                    yield $saga->compensate();

                    \sort($log);

                    return \implode('|', $log);
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('A|B');
        $this->factory->run($this->worker);
    }

    public function testParallelCompensationPropagatesRawErrorForRetry(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(true);
                    $saga->addCompensation(static function (): void {
                        throw new \TypeError('deployment bug');
                    });

                    try {
                        yield $saga->compensate();
                    } catch (\Throwable $e) {
                        return $e::class . '|' . $e->getMessage();
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('TypeError|deployment bug');
        $this->factory->run($this->worker);
    }

    public function testSequentialRunsCompensationsInReverseOrder(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(false);

                    $order = [];
                    $saga->addCompensation(static function () use (&$order): void {
                        $order[] = 'first-added';
                    });
                    $saga->addCompensation(static function () use (&$order): void {
                        $order[] = 'second-added';
                    });

                    yield $saga->compensate();

                    return \implode(',', $order);
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('second-added,first-added');
        $this->factory->run($this->worker);
    }

    public function testSequentialStopsAtFirstFailureAndThrowsRaw(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(false);

                    $ran = [];
                    $saga->addCompensation(static function () use (&$ran): void {
                        $ran[] = 'first-added';
                    });
                    $saga->addCompensation(static function (): void {
                        throw new \RuntimeException('boom-last');
                    });

                    try {
                        yield $saga->compensate();
                    } catch (\Throwable $e) {
                        return $e::class . '|' . $e->getMessage() . '|ran=' . \implode(',', $ran);
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('RuntimeException|boom-last|ran=');
        $this->factory->run($this->worker);
    }

    public function testCompensateReturnValueProbe(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(true);
                    $saga->addCompensation(static fn(): string => 'A');
                    $saga->addCompensation(static fn(): string => 'B');

                    $result = yield $saga->compensate();

                    return 'RET:' . \var_export($result, true);
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('RET:NULL');
        $this->factory->run($this->worker);
    }

    public function testParallelCompensationReportsEveryFailure(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'SagaParallelWorkflow')]
                public function handler(): iterable
                {
                    $saga = new Saga();
                    $saga->setParallelCompensation(true);
                    $saga->addCompensation(static function (): void {
                        throw new \RuntimeException('comp-A');
                    });
                    $saga->addCompensation(static function (): void {
                        throw new \RuntimeException('comp-B');
                    });

                    try {
                        yield $saga->compensate();
                    } catch (\Throwable $e) {
                        $seen = [$e->getMessage()];
                        if ($e instanceof CompensationException) {
                            foreach ($e->getSuppressed() as $suppressed) {
                                $seen[] = $suppressed->getMessage();
                            }
                        }
                        \sort($seen);

                        return \implode('|', \array_unique($seen));
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('SagaParallelWorkflow');
        $this->worker->assertWorkflowReturns('comp-A|comp-B');
        $this->factory->run($this->worker);
    }
}
