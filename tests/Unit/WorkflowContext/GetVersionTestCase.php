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

final class GetVersionTestCase extends UnitTestCase
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

    public function testVersionIsRetrieved(): void
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
                 * @Workflow\WorkflowMethod(name="VersionWorkflow")
                 */
                #[WorkflowMethod(name: 'VersionWorkflow')]
                public function handler(): iterable
                {
                    $version = yield Workflow::getVersion(
                        'test',
                        Workflow::DEFAULT_VERSION,
                        Workflow::DEFAULT_VERSION,
                    );

                    if ($version === Workflow::DEFAULT_VERSION) {
                        return 'OK';
                    }

                    return 'ERROR';
                }
            }
        );

        $this->worker->runWorkflow('VersionWorkflow');
        $this->worker->assertWorkflowReturns('OK');
        $this->factory->run($this->worker);
    }
}
