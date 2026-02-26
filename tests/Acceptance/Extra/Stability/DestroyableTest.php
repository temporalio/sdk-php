<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Stability\Destroyable;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

class DestroyableTest extends TestCase
{
    #[Test]
    public function destroyOnFinish(
        #[Stub('Extra_Stability_Destroyable')] WorkflowStubInterface $stub,
        ClientLogger $logger,
    ): void {
        $stub->getResult();

        \usleep(100_000); // wait for logs to be flushed

        self::assertTrue($logger->hasMessage('/Destroyable::destroy called/'));
    }
}

#[WorkflowInterface]
class TestWorkflow implements Destroyable
{
    private LoggerInterface $logger;

    #[WorkflowMethod('Extra_Stability_Destroyable')]
    public function handle(): string
    {
        $this->logger = LoggerFactory::createServerLogger(
            Workflow::getInfo()->taskQueue,
        );
        return 'result';
    }

    public function destroy(): void
    {
        Workflow::isReplaying();
        $this->logger->info('Destroyable::destroy called');
        unset($this->logger);
    }
}
