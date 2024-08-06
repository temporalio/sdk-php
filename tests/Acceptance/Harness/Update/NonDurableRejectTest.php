<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\NonDurableReject;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class NonDurableRejectTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Update_NonDurableReject')]WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        for ($i = 0; $i < 5; $i++) {
            try {
                $stub->update('my_update', -1);
                throw new \RuntimeException('Expected exception');
            } catch (WorkflowUpdateException) {
                # Expected
            }

            $stub->update('my_update', 1);
        }

        self::assertSame(5, $stub->getResult());

        # Verify no rejections were written to history since we failed in the validator
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            $event->hasWorkflowExecutionUpdateRejectedEventAttributes() and throw new \RuntimeException('Unexpected rejection event');
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $counter = 0;

    #[WorkflowMethod('Harness_Update_NonDurableReject')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->counter === 5);
        return $this->counter;
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate(int $arg): int
    {
        $this->counter += $arg;
        return $this->counter;
    }

    #[Workflow\UpdateValidatorMethod('my_update')]
    public function validateMyUpdate(int $arg): void
    {
        $arg < 0 and throw new \InvalidArgumentException('I *HATE* negative numbers!');
    }
}
