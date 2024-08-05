<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Basic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class BasicTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('HarnessWorkflow_Update_Basic')]WorkflowStubInterface $stub,
    ): void {
        $updated = $stub->update('my_update')->getValue(0);
        self::assertSame('Updated', $updated);
        self::assertSame('Hello, world!', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;

    #[WorkflowMethod('HarnessWorkflow_Update_Basic')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);
        return 'Hello, world!';
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate()
    {
        $this->done = true;
        return 'Updated';
    }
}
