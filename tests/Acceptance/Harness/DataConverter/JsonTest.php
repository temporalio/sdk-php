<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\DataConverter\Json;

use PHPUnit\Framework\Attributes\Test;
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

\define(__NAMESPACE__ . '\EXPECTED_RESULT', (object) ['spec' => true]);

/**
 * # JSON Payload Encoding
 *
 * Test that regular PHP structures like plain objects are encoded as JSON payloads.
 */
class JsonTest extends TestCase
{
    private ResultInterceptor $interceptor;

    public function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([$this->interceptor]);
    }

    #[Test]
    public function check(
        #[Stub('Harness_DataConverter_Json', args: [EXPECTED_RESULT])]
        #[Client(pipelineProvider: [self::class, 'pipelineProvider'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult();

        self::assertEquals(EXPECTED_RESULT, $result);

        $result = $this->interceptor->result;
        self::assertNotNull($result);

        $payloads = $result->toPayloads();
        /** @var \Temporal\Api\Common\V1\Payload $payload */
        $payload = $payloads->getPayloads()[0];

        self::assertSame('json/plain', $payload->getMetadata()['encoding']);
        self::assertSame('{"spec":true}', $payload->getData());
    }

    protected function setUp(): void
    {
        $this->interceptor = new ResultInterceptor();
        parent::setUp();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_DataConverter_Json')]
    public function run(object $data)
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
