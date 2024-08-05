<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\TimeoutDueToNoActiveWorkers;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class TimeoutDueToNoActiveWorkersTest extends TestCase
{
    #[Test]
    public static function check(
        #[Client(timeout: 30)]
        #[Stub('Harness_Query_TimeoutDueToNoActiveWorkers')]
        WorkflowStubInterface $stub,
        RRStarter $runner,
    ): void {
        # Stop worker
        $runner->stop();

        try {
            $stub->query('simple_query')?->getValue(0);
            throw new \Exception('Query must fail due to no active workers');
        } catch (WorkflowServiceException $e) {
            // Can be cancelled or deadline exceeded depending on whether client or
            // server hit timeout first in a racy way
            $status = $e->getPrevious()?->getCode();
            self::assertContains($status, [
                StatusCode::CANCELLED,
                StatusCode::DEADLINE_EXCEEDED, // Deadline Exceeded
                StatusCode::FAILED_PRECONDITION, // no poller seen for task queue recently
            ], 'Error code must be DEADLINE_EXCEEDED or CANCELLED. Got ' . \print_r($status, true));
        } finally {
            # Restart the worker and finish the wf
            $runner->start();
            $stub->signal('finish');
            $stub->getResult();
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $beDone = false;

    #[WorkflowMethod('Harness_Query_TimeoutDueToNoActiveWorkers')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('simple_query')]
    public function simpleQuery(): bool
    {
        return true;
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
