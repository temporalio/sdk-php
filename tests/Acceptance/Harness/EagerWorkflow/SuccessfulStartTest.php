<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\EagerWorkflow\SuccessfulStart;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SuccessfulStartTest extends TestCase
{
    private CatchStartWorkflowExecutionResponseInterceptor $interceptor;

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    #[Test]
    public function start(
        #[Stub('Harness_EagerWorkflow_SuccessfulStart', eagerStart: true)]
        #[Client(timeout: 30, pipelineProvider: [self::class, 'pipelineProvider'])]
        WorkflowStubInterface $stub,
    ): void {
        // Check the result and the eager workflow proof
        self::assertSame('ok', $stub->getResult());
        self::assertNotNull($this->interceptor->lastResponse);
        self::assertNotNull($this->interceptor->lastResponse->getEagerWorkflowTask());
    }

    protected function setUp(): void
    {
        $this->interceptor = new CatchStartWorkflowExecutionResponseInterceptor();
        parent::setUp();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_EagerWorkflow_SuccessfulStart')]
    public function run(): string
    {
        return 'ok';
    }
}

/**
 * Catches {@see StartWorkflowExecutionResponse} from the gRPC calls.
 */
class CatchStartWorkflowExecutionResponseInterceptor implements GrpcClientInterceptor
{
    public ?StartWorkflowExecutionResponse $lastResponse = null;

    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        $result = $next($method, $arg, $ctx);
        $result instanceof StartWorkflowExecutionResponse and $this->lastResponse = $result;
        return $result;
    }
}
