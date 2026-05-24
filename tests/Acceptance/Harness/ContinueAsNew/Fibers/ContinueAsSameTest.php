<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ContinueAsNew\Fibers\ContinueAsSame;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

\define(__NAMESPACE__ . '\\INPUT_DATA', 'InputData');
\define(__NAMESPACE__ . '\\MEMO_KEY', 'MemoKey');
\define(__NAMESPACE__ . '\\MEMO_VALUE', 'MemoValue');
\define(__NAMESPACE__ . '\\WORKFLOW_ID', 'TestID');

class ContinueAsSameTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub(
            type: 'Harness_ContinueAsNew_Fibers_ContinueAsSame',
            workflowId: WORKFLOW_ID,
            args: [INPUT_DATA],
            memo: [MEMO_KEY => MEMO_VALUE],
        )]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame(INPUT_DATA, $stub->getResult());
        # Workflow ID does not change after continue as new
        self::assertSame(WORKFLOW_ID, $stub->getExecution()->getID());
        # Memos do not change after continue as new
        $description = $stub->describe();
        self::assertSame([MEMO_KEY => MEMO_VALUE], $description->info->memo->getValues());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_ContinueAsNew_Fibers_ContinueAsSame')]
    public function run(string $input)
    {
        if (!empty(Workflow::getInfo()->continuedExecutionRunId)) {
            return $input;
        }

        return Workflow::continueAsNew(
            'Harness_ContinueAsNew_Fibers_ContinueAsSame',
            args: [$input],
        );
    }
}
