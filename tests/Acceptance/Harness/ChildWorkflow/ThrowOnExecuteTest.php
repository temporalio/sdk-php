<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\ThrowsOnExecute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ThrowOnExecuteTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Harness_ChildWorkflow_ThrowsOnExecute')]WorkflowStubInterface $stub): void
    {
        try {
            $stub->getResult();
            throw new \Exception('Expected exception');
        } catch (WorkflowFailedException $e) {
            self::assertSame('Harness_ChildWorkflow_ThrowsOnExecute', $e->getWorkflowType());

            /** @var ChildWorkflowFailure $previous */
            $previous = $e->getPrevious();
            self::assertInstanceOf(ChildWorkflowFailure::class, $previous);
            self::assertSame('Harness_ChildWorkflow_ThrowsOnExecute_Child', $previous->getWorkflowType());

            /** @var ApplicationFailure $failure */
            $failure = $previous->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertStringContainsString('Test message', $failure->getOriginalMessage());
            self::assertSame('TestError', $failure->getType());
            self::assertTrue($failure->isNonRetryable());
            self::assertSame(['foo' => 'bar'], $failure->getDetails()->getValue(0, 'array'));
        }
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_ThrowsOnExecute')]
    public function run()
    {
        return yield Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
        )->run();
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_ThrowsOnExecute_Child')]
    public function run()
    {
        throw new ApplicationFailure('Test message', 'TestError', true, EncodedValues::fromValues([['foo' => 'bar']]));
    }
}
