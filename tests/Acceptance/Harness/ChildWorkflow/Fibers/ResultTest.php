<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\Fibers\Result;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ResultTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_ChildWorkflow_Fibers_Result')]WorkflowStubInterface $stub): void
    {
        self::assertSame('Test', $stub->getResult());
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_Result')]
    public function run()
    {
        return Workflow::newChildWorkflowStub(ChildWorkflow::class)
            ->run('Test');
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_Result_Child')]
    public function run(string $input)
    {
        return $input;
    }
}
