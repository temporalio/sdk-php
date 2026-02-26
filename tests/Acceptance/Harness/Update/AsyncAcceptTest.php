<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\AsyncAccepted;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\UpdateMethod;

class AsyncAcceptTest extends TestCase
{
    #[Test]
    public function check(
        #[Stub('Harness_Update_AsyncAccepted')]WorkflowStubInterface $stub,
    ): void {
        $updateId = Uuid::uuid4()->toString();
        # Issue async update
        $handle = $stub->startUpdate(
            UpdateOptions::new('my_update', LifecycleStage::StageAccepted)
                ->withUpdateId($updateId),
            true,
        );

        $this->assertHandleIsBlocked($handle);
        // Create a separate handle to the same update
        $otherHandle = $stub->getUpdateHandle($updateId);
        $this->assertHandleIsBlocked($otherHandle);

        # Unblock last update
        $stub->signal('unblock');
        self::assertSame(123, $handle->getResult());
        self::assertSame(123, $otherHandle->getResult());

        # issue an async update that should throw
        $updateId = Uuid::uuid4()->toString();
        try {
            $stub->startUpdate(
                UpdateOptions::new('my_update', LifecycleStage::StageCompleted)
                    ->withUpdateId($updateId),
                false,
            );
            throw new \RuntimeException('Expected ApplicationFailure.');
        } catch (WorkflowUpdateException $e) {
            self::assertStringContainsString('Dying on purpose', $e->getPrevious()->getMessage());
            self::assertSame($e->getUpdateId(), $updateId);
            self::assertSame($e->getUpdateName(), 'my_update');
        }
    }

    private function assertHandleIsBlocked(UpdateHandle $handle): void
    {
        try {
            // Check there is no result
            $handle->getEncodedValues(1.5);
            throw new \RuntimeException('Expected Timeout Exception.');
        } catch (TimeoutException) {
            // Expected
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;
    private bool $blocked = true;

    #[WorkflowMethod('Harness_Update_AsyncAccepted')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);
        return 'Hello, World!';
    }

    #[SignalMethod('finish')]
    public function finish()
    {
        $this->done = true;
    }

    #[SignalMethod('unblock')]
    public function unblock()
    {
        $this->blocked = false;
    }

    #[UpdateMethod('my_update')]
    public function myUpdate(bool $block)
    {
        if ($block) {
            yield Workflow::await(fn(): bool => !$this->blocked);
            $this->blocked = true;
            return 123;
        }

        throw new ApplicationFailure('Dying on purpose', 'my_update', true);
    }
}
