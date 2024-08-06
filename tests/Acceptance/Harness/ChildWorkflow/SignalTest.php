<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\Signal;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SignalTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_ChildWorkflow_Signal')]WorkflowStubInterface $stub): void
    {
        self::assertSame('unblock', $stub->getResult());
    }
}

/**
 * A Workflow that starts a Child Workflow, unblocks it, and returns the result of the child workflow.
 */
#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Signal')]
    public function run()
    {
        $workflow = Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            // TODO: remove after https://github.com/temporalio/sdk-php/issues/451 is fixed
            Workflow\ChildWorkflowOptions::new()->withTaskQueue(Workflow::getInfo()->taskQueue),
        );
        $handle = $workflow->run();
        yield $workflow->signal('unblock');
        return yield $handle;
    }
}

/**
 * A workflow that waits for a signal and returns the data received.
 */
#[WorkflowInterface]
class ChildWorkflow
{
    private ?string $message = null;

    #[WorkflowMethod('Harness_ChildWorkflow_Signal_Child')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->message !== null);
        return $this->message;
    }

    /**
     * @return PromiseInterface<null>
     */
    #[SignalMethod('signal')]
    public function signal(string $message): void
    {
        $this->message = $message;
    }
}
