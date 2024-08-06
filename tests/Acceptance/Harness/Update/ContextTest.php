<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\WorkflowUpdate\Context;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ContextTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_WorkflowUpdate_Context')]WorkflowStubInterface $stub,
    ): void {
        $handle = $stub->startUpdate(UpdateOptions::new('my_update')->withUpdateId('test-update-id'));

        $updated2 = $stub->startUpdate(UpdateOptions::new('my_update2')->withUpdateId('test-update-id-2'))->getResult();
        self::assertSame('test-update-id-2', $updated2);

        // Check ID from the first Update
        $updated = $handle->getResult();
        self::assertSame('test-update-id', $updated);

        self::assertNull($stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;
    private bool $upd2 = false;

    #[WorkflowMethod('Harness_WorkflowUpdate_Context')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);
        return Workflow::getUpdateContext()?->getUpdateId();
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        Workflow::getUpdateContext() === null and throw new \RuntimeException('Update context should not be null.');

        $updateId = Workflow::getUpdateContext()->getUpdateID();

        yield Workflow::await(fn() => $this->upd2);
        Workflow::getUpdateContext() === null and throw new \RuntimeException('Update context should not be null.');
        $updateId !== Workflow::getUpdateContext()->getUpdateID() and throw new \RuntimeException(
            'Update ID should not change.'
        );

        $this->done = true;
        return $updateId;
    }

    #[Workflow\UpdateMethod('my_update2')]
    public function myUpdate2()
    {
        Workflow::getUpdateContext() === null and throw new \RuntimeException('Update context should not be null.');

        $this->upd2 = true;
        return Workflow::getUpdateContext()->getUpdateID();
    }
}
