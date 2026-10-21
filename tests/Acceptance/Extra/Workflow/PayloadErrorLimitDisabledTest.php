<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\PayloadErrorLimitDisabled;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\WorkflowTaskFailedCause;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowExecutionStatus;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(options: [WorkerFactory::class, 'options'])]
class PayloadErrorLimitDisabledTest extends TestCase
{
    #[Test]
    public function serverTerminatesWorkflow(
        #[Stub('Extra_Workflow_PayloadErrorLimitDisabled')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        try {
            $stub->getResult(timeout: 10);
            self::fail('The Workflow must not complete');
        } catch (WorkflowFailedException) {
        }

        self::assertSame(WorkflowExecutionStatus::Terminated, $stub->describe()->info->status);
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            self::assertNotSame(
                WorkflowTaskFailedCause::WORKFLOW_TASK_FAILED_CAUSE_PAYLOADS_TOO_LARGE,
                $event->getWorkflowTaskFailedEventAttributes()?->getCause(),
            );
        }
    }
}

class WorkerFactory
{
    public static function options(): WorkerOptions
    {
        return WorkerOptions::new()->withDisablePayloadErrorLimit();
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_PayloadErrorLimitDisabled')]
    public function handle(): string
    {
        return \str_repeat('a', 3 * 1024 * 1024);
    }
}
