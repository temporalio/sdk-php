<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Interceptors\Encoder;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [WorkerServices::class, 'pipelineProvider'])]
class EncoderTest extends TestCase
{
    #[Test]
    public function decodeModifyEncode(
        #[Stub('Extra_Interceptors_Encoder', args: ['hello world'])] WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('array');

        self::assertArrayHasKey('input', $result);
        self::assertEquals('hello world', $result['input']);

        self::assertArrayHasKey('modified', $result);
        self::assertEquals('true', $result['modified']);
    }
}

class WorkerServices
{
    public static function pipelineProvider(): PipelineProvider
    {
        return new SimplePipelineProvider([
            new ActivityInboundInterceptor(),
        ]);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Interceptors_Encoder")]
    public function handle(string $input)
    {
        $newInput = ['input' => $input];
        $encodedInput = CoolEncoder::encode($newInput);

        $encodedResult = yield Workflow::executeActivity(
            'Extra_Interceptors_Encoder.handler',
            [$encodedInput],
            Activity\ActivityOptions::new()->withScheduleToCloseTimeout('10 seconds'),
        );
        $decodedResult = CoolEncoder::decode($encodedResult);

        return $decodedResult;
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Interceptors_Encoder.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function handler(array $result): array
    {
        return [...$result, 'modified' => 'true'];
    }
}

final class ActivityInboundInterceptor implements \Temporal\Interceptor\ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $encodedInputValue = $input->arguments->getValue(0);
        $decodedInputValue = CoolEncoder::decode($encodedInputValue);

        $newInput = $input->with(arguments: EncodedValues::fromValues([$decodedInputValue]));
        $rawResult = $next($newInput);
        $encodedResult = CoolEncoder::encode($rawResult);

        return $encodedResult;
    }
}

final class CoolEncoder
{
    public static function encode(array $value): string
    {
        return \json_encode($value);
    }

    public static function decode(string $value): array
    {
        return \json_decode($value, true);
    }
}
