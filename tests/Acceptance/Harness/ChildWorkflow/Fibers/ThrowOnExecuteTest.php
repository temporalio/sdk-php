<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\ChildWorkflow\Fibers\ThrowsOnExecute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ThrowOnExecuteTest extends TestCase
{
    #[Test]
    public static function throwExceptionOnInit(
        #[Stub('Harness_ChildWorkflow_Fibers_ThrowsOnExecute')]
        WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult();
            throw new \Exception('Expected exception');
        } catch (WorkflowFailedException $e) {
            self::assertSame('Harness_ChildWorkflow_Fibers_ThrowsOnExecute', $e->getWorkflowType());

            /** @var ChildWorkflowFailure $previous */
            $previous = $e->getPrevious();
            self::assertInstanceOf(ChildWorkflowFailure::class, $previous);
            self::assertSame('Harness_ChildWorkflow_Fibers_ThrowsOnExecute_Child', $previous->getWorkflowType());

            /** @var ApplicationFailure $failure */
            $failure = $previous->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertStringContainsString('Test message', $failure->getOriginalMessage());
            self::assertSame('TestError', $failure->getType());
            self::assertTrue($failure->isNonRetryable());
            self::assertSame(['foo' => 'bar'], $failure->getDetails()->getValue(0, 'array'));
        }
    }

    #[Test]
    public static function throwExceptionAfterInit(
        #[Stub('Harness_ChildWorkflow_Fibers_ThrowsOnExecute', args: [true])]
        WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult();
            throw new \Exception('Expected exception');
        } catch (WorkflowFailedException $e) {
            self::assertSame('Harness_ChildWorkflow_Fibers_ThrowsOnExecute', $e->getWorkflowType());

            /** @var ChildWorkflowFailure $previous */
            $previous = $e->getPrevious();
            self::assertInstanceOf(ChildWorkflowFailure::class, $previous);
            self::assertSame('Harness_ChildWorkflow_Fibers_ThrowsOnExecute_ChildThrowOnInit', $previous->getWorkflowType());

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
    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_ThrowsOnExecute')]
    public function run(bool $onInit = false)
    {
        return Workflow::newChildWorkflowStub(
            $onInit ? ChildWorkflowThrowOnInit::class : ChildWorkflow::class,
        )->run();
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_ThrowsOnExecute_Child')]
    public function run()
    {
        1;
        throw new ApplicationFailure('Test message', 'TestError', true, EncodedValues::fromValues([['foo' => 'bar']]));
    }
}


#[WorkflowInterface]
class ChildWorkflowThrowOnInit
{
    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_ThrowsOnExecute_ChildThrowOnInit')]
    public function run()
    {
        throw new ApplicationFailure('Test message', 'TestError', true, EncodedValues::fromValues([['foo' => 'bar']]));
    }
}
