<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\PayloadErrorLimit;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\WorkflowTaskFailedCause;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowExecutionStatus;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class PayloadErrorLimitTest extends TestCase
{
    #[Test]
    public function oversizedResultFailsWorkflowTask(
        #[Stub('Extra_Workflow_PayloadErrorLimit')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $cause = self::waitForWorkflowTaskFailureCause($client, $stub);

        self::assertSame(WorkflowTaskFailedCause::WORKFLOW_TASK_FAILED_CAUSE_PAYLOADS_TOO_LARGE, $cause);
        self::assertSame(WorkflowExecutionStatus::Running, $stub->describe()->info->status);
        $stub->terminate('test finished');
    }

    private static function waitForWorkflowTaskFailureCause(
        WorkflowClientInterface $client,
        WorkflowStubInterface $stub,
    ): int {
        $deadline = \microtime(true) + 10;
        do {
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                $failed = $event->getWorkflowTaskFailedEventAttributes();
                if ($failed !== null) {
                    return $failed->getCause();
                }
            }
            \usleep(100_000);
        } while (\microtime(true) < $deadline);

        self::fail('The Workflow Task did not fail');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_PayloadErrorLimit')]
    public function handle(): string
    {
        return \str_repeat('a', 3 * 1024 * 1024);
    }
}
