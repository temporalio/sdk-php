<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\Result;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ResultTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_ChildWorkflow_Result')]WorkflowStubInterface $stub): void
    {
        self::assertSame('Test', $stub->getResult());
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Result')]
    public function run()
    {
        return yield Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            // TODO: remove after https://github.com/temporalio/sdk-php/issues/451 is fixed
            Workflow\ChildWorkflowOptions::new()->withTaskQueue(Workflow::getInfo()->taskQueue),
        )->run('Test');
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Result_Child')]
    public function run(string $input)
    {
        return $input;
    }
}
