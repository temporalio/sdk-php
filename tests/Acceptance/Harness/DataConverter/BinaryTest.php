<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\Binary;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Bytes;
use Temporal\DataConverter\EncodedValues;
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

const CODEC_ENCODING = 'binary/plain';
\define(__NAMESPACE__ . '\EXPECTED_RESULT', (string)0xDEADBEEF);
\define(__NAMESPACE__ . '\INPUT', new Bytes(EXPECTED_RESULT));

class BinaryTest extends TestCase
{
    private Interceptor $interceptor;

    protected function setUp(): void
    {
        $this->interceptor = new Interceptor();
        parent::setUp();
    }

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    #[Test]
    public function check(
        #[Stub('Workflow', args: [INPUT])]
        #[Client(pipelineProvider: [self::class, 'pipelineProvider'])]
        WorkflowStubInterface $stub,
    ): void {
        /** @var Bytes $result */
        $result = $stub->getResult(Bytes::class);

        self::assertEquals(EXPECTED_RESULT, $result->getData());

        # Check arguments
        self::assertNotNull($this->interceptor->startRequest);
        self::assertNotNull($this->interceptor->result);

        /** @var Payload $payload */
        $payload = $this->interceptor->startRequest->getInput()?->getPayloads()[0] ?? null;
        self::assertNotNull($payload);

        self::assertSame(CODEC_ENCODING, $payload->getMetadata()['encoding']);

        // Check result value from interceptor
        /** @var Payload $resultPayload */
        $resultPayload = $this->interceptor->result->toPayloads()->getPayloads()[0];
        self::assertSame(CODEC_ENCODING, $resultPayload->getMetadata()['encoding']);
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run(Bytes $data)
    {
        return $data;
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
