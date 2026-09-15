<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\HasWorkflowSerializationContext;
use Temporal\DataConverter\PayloadConverterInterface;
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\SerializationContextAwareInterface;
use Temporal\DataConverter\Type;
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Client\WorkflowStarter;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * A payload converter that stamps the signature of its serialization context
 * onto every payload it encodes, so a test can pin down which context a payload
 * was actually converted with.
 */
final class ContextStampingConverter implements PayloadConverterInterface, SerializationContextAwareInterface
{
    public const KEY = 'ctx-signature';

    private string $signature = 'none';

    public function getEncodingType(): string
    {
        return 'test/ctx';
    }

    public function getSerializationContext(): ?SerializationContext
    {
        return null;
    }

    public function withSerializationContext(?SerializationContext $context): static
    {
        $clone = clone $this;
        $clone->signature = match (true) {
            $context instanceof ActivitySerializationContext => 'act:' . $context->namespace . ':' . $context->activityType,
            $context instanceof HasWorkflowSerializationContext => 'wf:' . $context->getNamespace() . ':' . $context->getWorkflowId(),
            default => 'none',
        };

        return $clone;
    }

    public function toPayload($value): ?Payload
    {
        return (new Payload())
            ->setData((string) \json_encode($value))
            ->setMetadata(['encoding' => $this->getEncodingType(), self::KEY => $this->signature]);
    }

    public function fromPayload(Payload $payload, $type): mixed
    {
        return \json_decode($payload->getData(), true);
    }
}

final class SerializationContextClientTestCase extends TestCase
{
    private const NAMESPACE = 'test-ns';

    public function testStartWorkflowMemoIsEncodedWithWorkflowContext(): void
    {
        $captured = null;
        $service = $this->createMock(ServiceClientInterface::class);
        $service->method('StartWorkflowExecution')->willReturnCallback(
            static function (StartWorkflowExecutionRequest $request) use (&$captured): StartWorkflowExecutionResponse {
                $captured = $request;
                return (new StartWorkflowExecutionResponse())->setRunId('run-1');
            },
        );

        $starter = new WorkflowStarter(
            serviceClient: $service,
            converter: new DataConverter(new ContextStampingConverter()),
            clientOptions: (new ClientOptions())->withNamespace(self::NAMESPACE),
            interceptors: Pipeline::prepare([]),
        );

        $options = (new WorkflowOptions())
            ->withWorkflowId('wf-1')
            ->withMemo(['note' => 'memo-value']);

        $starter->start('MyWorkflow', $options, ['arg']);

        $expected = 'wf:' . self::NAMESPACE . ':wf-1';

        // Control: the input carries the workflow context.
        self::assertSame(
            $expected,
            $captured->getInput()->getPayloads()[0]->getMetadata()[ContextStampingConverter::KEY],
        );

        // The memo must carry the same workflow context.
        self::assertSame(
            $expected,
            $captured->getMemo()->getFields()['note']->getMetadata()[ContextStampingConverter::KEY],
        );
    }

    /**
     * The async completion client applies the activity context it is given, so
     * the out-of-band result carries the activity signature.
     */
    public function testAsyncActivityCompletionResultIsEncodedWithActivityContext(): void
    {
        $captured = null;
        $service = $this->createMock(ServiceClientInterface::class);
        $service->method('RespondActivityTaskCompletedById')->willReturnCallback(
            static function (RespondActivityTaskCompletedByIdRequest $request) use (&$captured): RespondActivityTaskCompletedByIdResponse {
                $captured = $request;
                return new RespondActivityTaskCompletedByIdResponse();
            },
        );

        $client = new ActivityCompletionClient(
            $service,
            (new ClientOptions())->withNamespace(self::NAMESPACE),
            new DataConverter(new ContextStampingConverter()),
        );

        $client
            ->withContext(new ActivitySerializationContext(
                namespace: self::NAMESPACE,
                activityType: 'MyActivity',
                taskQueue: 'tq',
            ))
            ->complete('wf-1', 'run-1', 'act-1', 'the-result');

        self::assertSame(
            'act:' . self::NAMESPACE . ':MyActivity',
            $captured->getResult()->getPayloads()[0]->getMetadata()[ContextStampingConverter::KEY],
        );
    }
}
