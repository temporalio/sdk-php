<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\RawValue;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\RawValue;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class RawValueTest extends TestCase
{
    private Interceptor $interceptor;

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    #[Test]
    public function check(
        #[Stub('Harness_DataConverter_RawValue')]
        #[Client(pipelineProvider: [self::class, 'pipelineProvider'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(RawValue::class);

        self::assertInstanceOf(RawValue::class, $result);
        self::assertInstanceOf(Payload::class, $result->getPayload());
        self::assertSame('hello world', $result->getPayload()->getData());

        # Check arguments
        self::assertNotNull($this->interceptor->startRequest);
        self::assertNotNull($this->interceptor->result);

        /** @var Payload $payload */
        $payload = $this->interceptor->startRequest->getInput()?->getPayloads()[0] ?? null;
        self::assertNotNull($payload);

        self::assertSame(EncodingKeys::METADATA_ENCODING_RAW_VALUE, $payload->getMetadata()['encoding']);

        // Check result value from interceptor
        /** @var Payload $resultPayload */
        $resultPayload = $this->interceptor->result->toPayloads()->getPayloads()[0];
        self::assertSame(EncodingKeys::METADATA_ENCODING_RAW_VALUE, $resultPayload->getMetadata()['encoding']);
    }

    protected function setUp(): void
    {
        $this->interceptor = new Interceptor();
        parent::setUp();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_DataConverter_RawValue')]
    public function run()
    {
        return yield new RawValue(new Payload(['data' => 'hello world']));
    }
}

class Interceptor implements GrpcClientInterceptor, WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public ?StartWorkflowExecutionRequest $startRequest = null;
    public ?EncodedValues $result = null;

    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        $arg instanceof StartWorkflowExecutionRequest and $this->startRequest = $arg;
        return $next($method, $arg, $ctx);
    }

    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $this->result = $next($input);
    }
}
