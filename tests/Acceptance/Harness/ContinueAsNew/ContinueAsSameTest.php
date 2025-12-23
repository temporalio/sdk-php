<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ContinueAsNew\ContinueAsSame;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * # Continues workflow execution
 */
class ContinueAsSameTest extends TestCase
{
    private const INPUT_DATA = 'InputData';
    private const MEMO_KEY = 'MemoKey';
    private const MEMO_VALUE = 'MemoValue';
    private const WORKFLOW_ID = 'TestID';

    #[Test]
    public static function check(
        #[Stub(
            type: 'Harness_ContinueAsNew_ContinueAsSame',
            workflowId: self::WORKFLOW_ID,
            args: [self::INPUT_DATA],
            memo: [self::MEMO_KEY => self::MEMO_VALUE],
        )]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame(self::INPUT_DATA, $stub->getResult());
        # Workflow ID does not change after continue as new
        self::assertSame(self::WORKFLOW_ID, $stub->getExecution()->getID());
        # Memos do not change after continue as new
        $description = $stub->describe();
        self::assertSame(5, $description->info->historyLength);
        self::assertSame([self::MEMO_KEY => self::MEMO_VALUE], $description->info->memo->getValues());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_ContinueAsNew_ContinueAsSame')]
    public function run(string $input): iterable
    {
        if (!empty(Workflow::getInfo()->continuedExecutionRunId)) {
            return $input;
        }

        return yield Workflow::continueAsNew(
            'Harness_ContinueAsNew_ContinueAsSame',
            args: [$input],
        );
    }
}
