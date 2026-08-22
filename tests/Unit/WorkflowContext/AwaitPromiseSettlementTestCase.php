<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\WorkflowContext;

use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

use function React\Promise\reject;
use function React\Promise\resolve;

final class AwaitPromiseSettlementTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;
    /** @var WorkerMock|WorkerInterface */
    private $worker;
    private bool $flagBackup;

    protected function setUp(): void
    {
        $this->flagBackup = FeatureFlags::$settleAwaitOnFirstSettledCondition;
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        FeatureFlags::$settleAwaitOnFirstSettledCondition = $this->flagBackup;

        parent::tearDown();
    }

    public function testClosureFalseTimesOut(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    $result = Workflow::awaitWithTimeout(5, static fn(): bool => false);

                    return $result === false ? 'TIMEOUT' : 'MET';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('TIMEOUT');
        $this->factory->run($this->worker);
    }

    public function testClosureTrueUnblocks(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    $result = Workflow::awaitWithTimeout(5, static fn(): bool => true);

                    return $result === true ? 'MET' : 'TIMEOUT';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('MET');
        $this->factory->run($this->worker);
    }

    public function testFulfilledPromiseUnblocks(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    $result = Workflow::awaitWithTimeout(5, resolve(true));

                    return $result === true ? 'MET' : 'TIMEOUT';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('MET');
        $this->factory->run($this->worker);
    }

    public function testSingleRejectedPromisePropagates(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    try {
                        Workflow::await(reject(new \RuntimeException('boom')));
                    } catch (\Throwable) {
                        return 'THREW';
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('THREW');
        $this->factory->run($this->worker);
    }

    public function testEmptyAwaitFailsFast(): void
    {
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    try {
                        Workflow::await();
                    } catch (\Throwable) {
                        return 'THREW';
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('THREW');
        $this->factory->run($this->worker);
    }

    public function testRejectedConditionPropagatesWhenFlagEnabled(): void
    {
        FeatureFlags::$settleAwaitOnFirstSettledCondition = true;
        $this->addToAssertionCount(1);
        $this->registerRejectingAwaitWithTimeoutWorkflow();

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('THREW');
        $this->factory->run($this->worker);
    }

    public function testRejectedConditionIsIgnoredWhenFlagDisabled(): void
    {
        FeatureFlags::$settleAwaitOnFirstSettledCondition = false;
        $this->addToAssertionCount(1);
        $this->registerRejectingAwaitWithTimeoutWorkflow();

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('RESULT:false');
        $this->factory->run($this->worker);
    }

    public function testMultiConditionRejectPropagatesWhenFlagEnabled(): void
    {
        FeatureFlags::$settleAwaitOnFirstSettledCondition = true;
        $this->addToAssertionCount(1);
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    try {
                        Workflow::awaitWithTimeout(
                            5,
                            reject(new \RuntimeException('boom')),
                            static fn(): bool => false,
                        );
                    } catch (\Throwable) {
                        return 'THREW';
                    }

                    return 'NO_THROW';
                }
            }
        );

        $this->worker->runWorkflow('AwaitPromiseWorkflow');
        $this->worker->assertWorkflowReturns('THREW');
        $this->factory->run($this->worker);
    }

    private function registerRejectingAwaitWithTimeoutWorkflow(): void
    {
        $this->worker->registerWorkflowObject(
            new
            #[Workflow\WorkflowInterface]
            class {
                #[WorkflowMethod(name: 'AwaitPromiseWorkflow')]
                public function handler(): string
                {
                    try {
                        $result = Workflow::awaitWithTimeout(5, reject(new \RuntimeException('boom')));
                    } catch (\Throwable) {
                        return 'THREW';
                    }

                    return 'RESULT:' . \var_export($result, true);
                }
            }
        );
    }
}
