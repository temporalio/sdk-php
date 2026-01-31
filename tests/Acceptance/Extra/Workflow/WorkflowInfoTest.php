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
        #[Stub('Extra_Workflow_WorkflowInfo', args: [[MainWorkflow::ARG_ROOT_EXECUTION]])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        self::assertSame([
            'id' => $stub->getExecution()->getID(),
            'runID' => $stub->getExecution()->getRunID(),
        ], $result['rootExecution']);
    }

    #[Test]
    public static function continueAsNewExecution(
        #[Stub('Extra_Workflow_WorkflowInfo', args: [[
            MainWorkflow::ARG_CONTINUE_AS_NEW,
            MainWorkflow::ARG_DUMP,
        ]])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        self::assertNotEmpty($result['continuedExecutionRunId']);
        self::assertSame($result['firstExecutionRunId'], $result['continuedExecutionRunId']);
        self::assertNotSame($result['firstExecutionRunId'], $result['originalExecutionRunId']);
    }

    #[Test]
    public static function continueAsNewExecutionChild(
        #[Stub('Extra_Workflow_WorkflowInfo', args: [[
            MainWorkflow::ARG_CONTINUE_AS_NEW,
            MainWorkflow::ARG_RUN_MAIN_AS_CHILD,
            MainWorkflow::ARG_DUMP,
        ]])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        /**
         * There is no information about continued execution in child workflows.
         */
        self::assertEmpty($result['continuedExecutionRunId']);
        self::assertIsString($result['continuedExecutionRunId']);
        self::assertSame($result['firstExecutionRunId'], $result['originalExecutionRunId']);
    }

    #[Test]
    public static function retryOptions(
        #[Stub(
            'Extra_Workflow_WorkflowInfo',
            args: [[MainWorkflow::ARG_RETRY_OPTIONS]],
            retryOptions: new RetryOptions(
                backoffCoefficient: 3.0,
                maximumInterval: '2 minutes',
                maximumAttempts: 10,
            ),
        )]
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
    public const ARG_RETRY_OPTIONS = 'retryPolicy';
    public const ARG_ROOT_EXECUTION = 'rootExecution';
    public const ARG_CONTINUE_AS_NEW = 'continueAsNew';
    public const ARG_DUMP = 'dump';
    public const ARG_RUN_MAIN_AS_CHILD = 'runMainAsChild';

    #[WorkflowMethod('Extra_Workflow_WorkflowInfo')]
    public function run(array $actions)
    {
        $action = \array_shift($actions);
        return yield match ($action) {
            self::ARG_ROOT_EXECUTION => Workflow::newChildWorkflowStub(ChildWorkflow::class)->run(),
            self::ARG_RETRY_OPTIONS => Workflow::getInfo()->retryOptions,
            self::ARG_CONTINUE_AS_NEW => Workflow::continueAsNew('Extra_Workflow_WorkflowInfo', args: [$actions]),
            self::ARG_RUN_MAIN_AS_CHILD => Workflow::newChildWorkflowStub(MainWorkflow::class)->run($actions),
            self::ARG_DUMP => Helper::dumpWorkflow(),
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
        return Helper::dumpWorkflow();
    }
}

class Helper
{
    public static function dumpWorkflow(): array
    {
        $workflowInfo = Workflow::getInfo();
        return [
            'rootExecution' => [
                'id' => $workflowInfo->rootExecution?->getID(),
                'runID' => $workflowInfo->rootExecution?->getRunID(),
            ],
            'firstExecutionRunId' => $workflowInfo->firstExecutionRunId,
            'continuedExecutionRunId' => $workflowInfo->continuedExecutionRunId,
            'originalExecutionRunId' => $workflowInfo->originalExecutionRunId,
        ];
    }
}
