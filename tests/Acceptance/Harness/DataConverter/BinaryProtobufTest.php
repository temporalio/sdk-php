<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\BinaryProtobuf;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Common\V1\DataBlob;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ProtoConverter;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Tests\Acceptance\App\Attribute\Client;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * # Binary Protobuf Payload Encoding
 *
 * Test that binary protobuf payload encoding works well.
 */
class BinaryProtobufTest extends TestCase
{
    private const EXPECTED_RESULT = 0xDEADBEEF;

    private GrpcCallInterceptor $interceptor;

    #[Test]
    public function check(
        #[Stub('Harness_DataConverter_BinaryProtobuf', args: [new DataBlob(['data' => self::EXPECTED_RESULT])])]
        #[Client(
            pipelineProvider: [self::class, 'pipelineProvider'],
            payloadConverters: [ProtoConverter::class],
        )]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(DataBlob::class);

        # Check that binary protobuf message was decoded in the Workflow and sent back.
        # But we don't check the result Payload encoding, because we can't configure different Payload encoders
        # on the server side for different Harness features.
        # There `json/protobuf` converter is used for protobuf messages by default on the server side.
        self::assertEquals(self::EXPECTED_RESULT, $result->getData());

        # Check arguments
        self::assertNotNull($this->interceptor->startRequest);
        /** @var Payload $payload */
        $payload = $this->interceptor->startRequest->getInput()?->getPayloads()[0] ?? null;
        self::assertNotNull($payload);

        self::assertSame('binary/protobuf', $payload->getMetadata()['encoding']);
        self::assertSame('temporal.api.common.v1.DataBlob', $payload->getMetadata()['messageType']);
    }

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    protected function setUp(): void
    {
        $this->interceptor = new GrpcCallInterceptor();
        parent::setUp();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_DataConverter_BinaryProtobuf')]
    public function run(DataBlob $data): DataBlob
    {
        return $data;
    }
}

/**
 * Catches {@see StartWorkflowExecutionRequest} from the gRPC calls.
 */
class GrpcCallInterceptor implements GrpcClientInterceptor
{
    public ?StartWorkflowExecutionRequest $startRequest = null;

    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        $arg instanceof StartWorkflowExecutionRequest and $this->startRequest = $arg;
        return $next($method, $arg, $ctx);
    }
}
