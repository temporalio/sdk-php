<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Temporal\Exception\OutOfContextException;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

final class WorkflowContextLeakTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;
    private WorkerMock $worker;

    public function testTheWorkflowContextIsGoneAfterAnActivation(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'ContextLeakWorkflow')]
                public function handler(): iterable
                {
                    yield Workflow::timer(1);

                    return 'done';
                }
            }
        );

        $this->worker->runWorkflow('ContextLeakWorkflow');
        $this->worker->expectTimer(1);
        $this->worker->assertWorkflowReturns('done');
        $this->factory->run($this->worker);

        $this->expectException(OutOfContextException::class);
        Workflow::getInfo();
    }

    public function testTheWorkflowContextIsGoneAfterAnActivationThrows(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                private bool $signalled = false;

                #[WorkflowMethod(name: 'ThrowingConditionWorkflow')]
                public function handler(): iterable
                {
                    yield Workflow::await(function (): bool {
                        $this->signalled and throw new \LogicException('condition failed');

                        return false;
                    });
                }

                #[Workflow\SignalMethod(name: 'go')]
                public function go(): void
                {
                    $this->signalled = true;
                }
            }
        );

        $this->worker->runWorkflow('ThrowingConditionWorkflow');
        $this->worker->sendSignal('ThrowingConditionWorkflow', 'go');

        try {
            $this->factory->run($this->worker);
        } catch (\Throwable) {
        }

        $this->expectException(OutOfContextException::class);
        Workflow::getInfo();
    }

    protected function setUp(): void
    {
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);

        parent::tearDown();
    }
}
