<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\JsonProtobuf;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Common\V1\DataBlob;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
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

const EXPECTED_RESULT = 0xDEADBEEF;
\define(__NAMESPACE__ . '\INPUT', (new DataBlob())->setData(EXPECTED_RESULT));

class JsonProtobufTest extends TestCase
{
    private ResultInterceptor $interceptor;

    protected function setUp(): void
    {
        $this->interceptor = new ResultInterceptor();
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
        /** @var DataBlob $result */
        $result = $stub->getResult(DataBlob::class);

        self::assertEquals(EXPECTED_RESULT, $result->getData());

        $result = $this->interceptor->result;
        self::assertNotNull($result);

        $payloads = $result->toPayloads();
        /** @var \Temporal\Api\Common\V1\Payload $payload */
        $payload = $payloads->getPayloads()[0];

        self::assertSame('json/protobuf', $payload->getMetadata()['encoding']);
        self::assertSame('temporal.api.common.v1.DataBlob', $payload->getMetadata()['messageType']);
        self::assertSame('{"data":"MzczNTkyODU1OQ=="}', $payload->getData());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run(DataBlob $data)
    {
        return $data;
    }
}

/**
 * Catches raw Workflow result.
 */
class ResultInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public ?EncodedValues $result = null;

    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $this->result = $next($input);
    }
}
