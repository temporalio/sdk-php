<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowInfo;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\RetryOptions;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowInfoTest extends TestCase
{
    #[Test]
    public static function rootWorkflowExecution(
        #[Stub('Extra_Workflow_WorkflowInfo', args: [MainWorkflow::ARG_ROOT_EXECUTION])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        self::assertSame([
            'ID' => $stub->getExecution()->getID(),
            'RunID' => $stub->getExecution()->getRunID(),
        ], $result);
    }

    #[Test]
    public static function retryPolicy(
        #[Stub('Extra_Workflow_WorkflowInfo', args: [MainWorkflow::ARG_RETRY_POLICY], retryOptions: new RetryOptions(
            backoffCoefficient: 3.0,
            maximumInterval: '2 minutes',
            maximumAttempts: 10,
        ))]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        self::assertEquals([
            "initial_interval" => ['seconds' => 1, 'nanos' => 0],
            "backoff_coefficient" => 3,
            "maximum_interval" => ['seconds' => 120, 'nanos' => 0],
            "maximum_attempts" => 10,
            "non_retryable_error_types" => [],
        ], $result);
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    public const ARG_RETRY_POLICY = 'retryPolicy';
    public const ARG_ROOT_EXECUTION = 'rootExecution';

    #[WorkflowMethod('Extra_Workflow_WorkflowInfo')]
    public function run($arg)
    {
        return yield match ($arg) {
            self::ARG_ROOT_EXECUTION => Workflow::newChildWorkflowStub(ChildWorkflow::class)->run(),
            self::ARG_RETRY_POLICY => Workflow::getCurrentContext()->getInfo()->retryPolicy,
        };
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Extra_Workflow_WorkflowInfo_Child')]
    public function run()
    {
        return yield Workflow::newChildWorkflowStub(ChildWorkflow2::class)->run();
    }
}

#[WorkflowInterface]
class ChildWorkflow2
{
    #[WorkflowMethod('Extra_Workflow_WorkflowInfo_Child2')]
    public function run()
    {
        return Workflow::getCurrentContext()->getInfo()->rootExecution;
    }
}
