<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\CancelExternalWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Transport\Request\CancelExternalWorkflow;
use Temporal\Internal\Workflow\ExternalWorkflowStub;
use Temporal\Tests\Fixtures\PipelineProvider;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
#[CoversClass(ExternalWorkflowStub::class)]
#[UsesClass(CancelExternalWorkflow::class)]
#[UsesClass(CancelExternalWorkflowInput::class)]
final class ExternalWorkflowStubTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);
        parent::tearDown();
    }

    #[Test]
    public function cancelSendsReason(): void
    {
        $request = $this->captureCancelRequest('operator asked');

        self::assertSame('operator asked', $request->getReason());
        self::assertSame('operator asked', $request->getOptions()['reason']);
    }

    #[Test]
    public function cancelWithoutReasonSendsNull(): void
    {
        $request = $this->captureCancelRequest(null);

        self::assertNull($request->getReason());
        self::assertNull($request->getOptions()['reason']);
    }

    private function captureCancelRequest(?string $reason): CancelExternalWorkflow
    {
        $captured = null;
        $context = $this->createMock(WorkflowContextInterface::class);
        $context
            ->method('request')
            ->willReturnCallback(function (CancelExternalWorkflow $request) use (&$captured): PromiseInterface {
                $captured = $request;

                return $this->createMock(PromiseInterface::class);
            });

        Workflow::setCurrentContext($context);

        $stub = new ExternalWorkflowStub(
            new WorkflowExecution('workflow-id', 'run-id'),
            (new PipelineProvider([]))->getPipeline(WorkflowOutboundCallsInterceptor::class),
        );
        $stub->cancel($reason);

        self::assertInstanceOf(CancelExternalWorkflow::class, $captured);

        return $captured;
    }
}
