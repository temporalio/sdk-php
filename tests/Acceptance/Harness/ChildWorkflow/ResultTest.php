<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\WorkflowUpdate\Result;

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
    public static function check(#[Stub('MainWorkflow')] WorkflowStubInterface $stub): void
    {
        self::assertSame('Test', $stub->getResult());
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('MainWorkflow')]
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
    #[WorkflowMethod('ChildWorkflow')]
    public function run(string $input)
    {
        return $input;
    }
}
